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
                $subject = $req->subject_user_external_id;

                // Find affected queries: conversations whose caller_label matches,
                // and queries whose principal_set contains the subject.
                $affected = DB::table('queries')
                    ->where('workspace_id', $req->workspace_id)
                    ->whereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements(principal_set) ps WHERE ps->>'id' = ?)", [$subject])
                    ->pluck('id')
                    ->all();

                $convoIds = DB::table('conversations')
                    ->where('workspace_id', $req->workspace_id)
                    ->where('caller_label', $subject)
                    ->pluck('id')
                    ->all();

                if ($convoIds !== []) {
                    $extra = DB::table('queries')
                        ->whereIn('conversation_id', $convoIds)
                        ->pluck('id')
                        ->all();
                    $affected = array_values(array_unique(array_merge($affected, $extra)));
                }

                if ($affected !== []) {
                    DB::table('feedback')->whereIn('query_id', $affected)->delete();
                    DB::table('unanswered_questions')->whereIn('query_id', $affected)->delete();
                    DB::table('prompt_injection_signals')->whereIn('query_id', $affected)->delete();
                    DB::table('queries')->whereIn('id', $affected)->delete();
                }
                if ($convoIds !== []) {
                    DB::table('conversations')->whereIn('id', $convoIds)->delete();
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
}
