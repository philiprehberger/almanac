<?php

namespace App\Jobs;

use App\Models\DeletionRequest;
use App\Models\Scopes\WorkspaceScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessDeletionRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $deletionRequestId,
    ) {
    }

    public function handle(): void
    {
        /** @var DeletionRequest|null $req */
        $req = DeletionRequest::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->find($this->deletionRequestId);

        if ($req === null) {
            return;
        }

        $req->forceFill(['status' => DeletionRequest::STATUS_RUNNING])->save();

        try {
            DB::transaction(function () use ($req) {
                $workspaceId = $req->workspace_id;
                $subject = $req->subject_user_external_id;

                // A query belongs to the subject when its principal_set names the
                // subject, or it occurred in a conversation labeled for them.
                $subjectQueryIds = function ($sub) use ($workspaceId, $subject) {
                    $sub->select('id')->from('queries')
                        ->where('workspace_id', $workspaceId)
                        ->where(function ($w) use ($workspaceId, $subject) {
                            $w->whereRaw(
                                "EXISTS (SELECT 1 FROM jsonb_array_elements(principal_set) ps WHERE ps->>'id' = ?)",
                                [$subject]
                            )->orWhereIn('conversation_id', function ($c) use ($workspaceId, $subject) {
                                $c->select('id')->from('conversations')
                                    ->where('workspace_id', $workspaceId)
                                    ->where('caller_label', $subject);
                            });
                        });
                };

                // Snapshot the affected ids for the audit trail (capped so the
                // stored array can't grow without bound).
                $affected = DB::table('queries')
                    ->whereIn('id', $subjectQueryIds)
                    ->limit(10_000)
                    ->pluck('id')
                    ->all();

                // Child rows first (FK order), then the queries themselves —
                // all via sub-selects so we never bind an unbounded id list.
                DB::table('feedback')->whereIn('query_id', $subjectQueryIds)->delete();
                DB::table('unanswered_questions')->whereIn('query_id', $subjectQueryIds)->delete();
                DB::table('prompt_injection_signals')->whereIn('query_id', $subjectQueryIds)->delete();
                DB::table('queries')->whereIn('id', $subjectQueryIds)->delete();

                DB::table('conversations')
                    ->where('workspace_id', $workspaceId)
                    ->where('caller_label', $subject)
                    ->delete();

                if ($req->scope === DeletionRequest::SCOPE_ALL) {
                    $this->purgeSubjectContent($workspaceId, $subject);
                }

                $req->forceFill([
                    'status' => DeletionRequest::STATUS_COMPLETE,
                    'affected_query_ids' => $affected,
                    'completed_at' => now(),
                ])->save();
            });
        } catch (\Throwable $e) {
            $req->forceFill([
                'status' => DeletionRequest::STATUS_FAILED,
                'last_error' => $e->getMessage(),
                'completed_at' => now(),
            ])->save();
            throw $e;
        }
    }

    /**
     * `scope=all` erasure: remove the subject's identity linkage and any
     * documents that are private to the subject — i.e. every ACL row on the
     * document names only this subject — together with their chunks and vectors.
     * Workspace-/public-readable documents are shared content and are left in
     * place.
     */
    private function purgeSubjectContent(string $workspaceId, string $subject): void
    {
        DB::table('identity_mappings')
            ->where('workspace_id', $workspaceId)
            ->where('source_principal_id', $subject)
            ->delete();

        $privateDocIds = DB::table('documents as d')
            ->where('d.workspace_id', $workspaceId)
            ->whereExists(function ($e) use ($subject) {
                $e->selectRaw('1')->from('doc_acls as a')
                    ->whereColumn('a.document_id', 'd.id')
                    ->where('a.principal_external_id', $subject);
            })
            ->whereNotExists(function ($e) use ($subject) {
                $e->selectRaw('1')->from('doc_acls as a2')
                    ->whereColumn('a2.document_id', 'd.id')
                    ->where('a2.principal_external_id', '!=', $subject);
            })
            ->pluck('d.id')
            ->all();

        if ($privateDocIds === []) {
            return;
        }

        DB::table('doc_chunks')->whereIn('document_id', $privateDocIds)->delete();
        DB::table('doc_acls')->whereIn('document_id', $privateDocIds)->delete();
        DB::table('documents')->whereIn('id', $privateDocIds)->delete();
    }
}
