<?php

namespace App\Services\Connectors;

use App\Models\Connector;
use App\Models\DocAcl;
use App\Models\Document;
use App\Models\IngestRun;
use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Services\Embed\EmbedQueueDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentIngester
{
    public function __construct(
        private readonly ConnectorAdapterFactory $factory,
        private readonly EmbedQueueDispatcher $embedDispatcher,
    ) {
    }

    public function run(Workspace $workspace, Connector $connector, string $mode = IngestRun::MODE_INCREMENTAL): IngestRun
    {
        $run = IngestRun::query()->withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'connector_id' => $connector->id,
            'mode' => $mode,
            'status' => IngestRun::STATUS_RUNNING,
            'started_at' => Carbon::now(),
        ]);

        $adapter = $this->factory->forConnector($connector);

        $added = 0;
        $updated = 0;
        $removed = 0;
        $failed = 0;

        try {
            foreach ($adapter->fetch($connector, $mode) as $fetched) {
                if ($fetched->deleted) {
                    $removed += $this->softDelete($workspace, $connector, $fetched->externalId);
                    continue;
                }
                $outcome = $this->upsert($workspace, $connector, $fetched);
                if ($outcome === 'unsupported' || $outcome === 'failed') {
                    $failed++;
                } elseif ($outcome === 'inserted') {
                    $added++;
                } elseif ($outcome === 'updated') {
                    $updated++;
                }
            }

            $connector->forceFill([
                'last_sync_at' => Carbon::now(),
                'consecutive_failures' => 0,
                'backoff_until' => null,
                'status' => Connector::STATUS_ACTIVE,
            ])->save();

            $run->forceFill([
                'status' => IngestRun::STATUS_COMPLETED,
                'docs_added' => $added,
                'docs_updated' => $updated,
                'docs_removed' => $removed,
                'docs_failed' => $failed,
                'completed_at' => Carbon::now(),
            ])->save();

            return $run;
        } catch (\Throwable $e) {
            $connector->forceFill([
                'consecutive_failures' => $connector->consecutive_failures + 1,
                'status' => Connector::STATUS_ERROR,
                'backoff_until' => Carbon::now()->addSeconds($connector->nextBackoffSeconds()),
            ])->save();

            $run->forceFill([
                'status' => IngestRun::STATUS_FAILED,
                'docs_added' => $added,
                'docs_updated' => $updated,
                'docs_removed' => $removed,
                'docs_failed' => $failed,
                'last_error' => $e->getMessage(),
                'completed_at' => Carbon::now(),
            ])->save();

            throw $e;
        }
    }

    private function upsert(Workspace $workspace, Connector $connector, FetchedDocument $f): string
    {
        return DB::transaction(function () use ($workspace, $connector, $f) {
            /** @var Document|null $existing */
            $existing = Document::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->where('connector_id', $connector->id)
                ->where('external_id', $f->externalId)
                ->first();

            $unsupported = $f->kind === 'unsupported';

            $payload = [
                'workspace_id' => $workspace->id,
                'connector_id' => $connector->id,
                'external_id' => $f->externalId,
                'title' => $f->title,
                'kind' => $unsupported ? Document::KIND_TXT : $f->kind,
                'source_url' => $f->sourceUrl,
                'etag' => $f->etag,
                'modified_at' => $f->modifiedAt,
                'embed_status' => $unsupported
                    ? Document::EMBED_UNSUPPORTED
                    : Document::EMBED_PENDING,
                'failure_reason' => $f->failureReason,
                'deleted_at' => null,
            ];

            if ($existing !== null) {
                if ($existing->etag === $f->etag && $existing->embed_status === Document::EMBED_EMBEDDED) {
                    return 'unchanged';
                }
                $existing->forceFill($payload)->save();
                $this->syncAcls($workspace, $existing, $f->acls);
                if (! $unsupported) {
                    $this->embedDispatcher->dispatch($workspace, $existing, $f->body);
                }
                return 'updated';
            }

            $doc = Document::query()->withoutGlobalScopes()->create($payload);
            $this->syncAcls($workspace, $doc, $f->acls);
            if (! $unsupported) {
                $this->embedDispatcher->dispatch($workspace, $doc, $f->body);
            }
            return 'inserted';
        });
    }

    private function softDelete(Workspace $workspace, Connector $connector, string $externalId): int
    {
        $doc = Document::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('connector_id', $connector->id)
            ->where('external_id', $externalId)
            ->first();
        if ($doc === null) {
            return 0;
        }
        $doc->forceFill(['deleted_at' => Carbon::now()])->save();
        // Cascade chunk + ACL cleanup so deleted docs immediately stop
        // contributing to retrieval.
        DB::table('doc_chunks')->where('document_id', $doc->id)->delete();
        DB::table('doc_acls')->where('document_id', $doc->id)->delete();
        return 1;
    }

    private function syncAcls(Workspace $workspace, Document $document, array $acls): void
    {
        DB::table('doc_acls')->where('document_id', $document->id)->delete();
        $rows = [];
        foreach ($acls as $a) {
            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'workspace_id' => $workspace->id,
                'document_id' => $document->id,
                'principal_kind' => $a['principal_kind'],
                'principal_external_id' => $a['principal_external_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows !== []) {
            DB::table('doc_acls')->insert($rows);
        }
    }
}
