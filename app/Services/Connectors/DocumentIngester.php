<?php

namespace App\Services\Connectors;

use App\Models\Connector;
use App\Models\DocAcl;
use App\Models\Document;
use App\Models\IngestRun;
use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Services\Embed\EmbedQueueDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
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

        $seen = [];

        try {
            foreach ($adapter->fetch($connector, $mode) as $fetched) {
                if ($fetched->deleted) {
                    $removed += $this->softDelete($workspace, $connector, $fetched->externalId);
                    continue;
                }
                $seen[$fetched->externalId] = true;
                $outcome = $this->upsert($workspace, $connector, $fetched);
                if ($outcome === 'unsupported' || $outcome === 'failed') {
                    $failed++;
                } elseif ($outcome === 'inserted') {
                    $added++;
                } elseif ($outcome === 'updated') {
                    $updated++;
                }
            }

            // A full sync is authoritative: any local document the source no
            // longer returned has been deleted upstream. Reconcile even when the
            // adapter never emits an explicit deletion tombstone.
            if ($mode === IngestRun::MODE_FULL) {
                $removed += $this->reconcileDeletions($workspace, $connector, $seen);
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
                    // Content is unchanged, but source-side permissions may have
                    // changed. Re-sync ACLs so a revocation takes effect without
                    // forcing a (costly) re-embed.
                    $this->syncAcls($workspace, $existing, $f->acls);
                    return 'unchanged';
                }
                $existing->forceFill($payload)->save();
                $this->syncAcls($workspace, $existing, $f->acls);
                if (! $unsupported) {
                    $this->embedDispatcher->dispatch($workspace, $existing, $f->body);
                }
                return 'updated';
            }

            try {
                $doc = Document::query()->withoutGlobalScopes()->create($payload);
            } catch (UniqueConstraintViolationException) {
                // A concurrent run inserted the same (connector_id, external_id)
                // first. Re-fetch and fall through to an update instead of
                // creating a duplicate.
                $doc = Document::query()
                    ->withoutGlobalScope(WorkspaceScope::class)
                    ->where('connector_id', $connector->id)
                    ->where('external_id', $f->externalId)
                    ->firstOrFail();
                $doc->forceFill($payload)->save();
                $this->syncAcls($workspace, $doc, $f->acls);
                if (! $unsupported) {
                    $this->embedDispatcher->dispatch($workspace, $doc, $f->body);
                }
                return 'updated';
            }

            $this->syncAcls($workspace, $doc, $f->acls);
            if (! $unsupported) {
                $this->embedDispatcher->dispatch($workspace, $doc, $f->body);
            }
            return 'inserted';
        });
    }

    /**
     * Soft-delete local documents for this connector that a full sync did not
     * return (i.e. removed at the source), cleaning their chunks and ACLs.
     *
     * @param  array<string, true>  $seenExternalIds
     */
    private function reconcileDeletions(Workspace $workspace, Connector $connector, array $seenExternalIds): int
    {
        $removed = 0;
        Document::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('connector_id', $connector->id)
            ->whereNull('deleted_at')
            ->select(['id', 'external_id'])
            ->orderBy('id')
            ->chunkById(500, function ($docs) use (&$removed, $seenExternalIds) {
                foreach ($docs as $doc) {
                    if (isset($seenExternalIds[$doc->external_id])) {
                        continue;
                    }
                    DB::transaction(function () use ($doc) {
                        $doc->forceFill(['deleted_at' => Carbon::now()])->save();
                        DB::table('doc_chunks')->where('document_id', $doc->id)->delete();
                        DB::table('doc_acls')->where('document_id', $doc->id)->delete();
                    });
                    $removed++;
                }
            });

        return $removed;
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
        $desired = [];
        foreach ($acls as $a) {
            $desired[$a['principal_kind'].'|'.$a['principal_external_id']] = $a;
        }

        $current = DB::table('doc_acls')
            ->where('document_id', $document->id)
            ->get(['principal_kind', 'principal_external_id']);
        $currentKeys = [];
        foreach ($current as $c) {
            $currentKeys[$c->principal_kind.'|'.$c->principal_external_id] = true;
        }

        // No change → leave the rows untouched (this path runs for every
        // unchanged document on every incremental sync).
        if (count($desired) === count($currentKeys)
            && array_diff(array_keys($desired), array_keys($currentKeys)) === []) {
            return;
        }

        DB::table('doc_acls')->where('document_id', $document->id)->delete();
        $rows = [];
        foreach ($desired as $a) {
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
