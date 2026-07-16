<?php

namespace App\Jobs;

use App\Models\Connector;
use App\Models\IngestRun;
use App\Models\Workspace;
use App\Services\Connectors\DocumentIngester;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IngestConnectorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $connectorId,
        public readonly string $mode = IngestRun::MODE_INCREMENTAL,
    ) {
    }

    /**
     * Serialize ingests per connector so a scheduled sync, a queue retry, and a
     * manual reindex can never run concurrently and double-insert documents.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->connectorId))->expireAfter(900)->dontRelease()];
    }

    public function handle(DocumentIngester $ingester): void
    {
        /** @var Workspace|null $workspace */
        $workspace = Workspace::query()->find($this->workspaceId);
        /** @var Connector|null $connector */
        $connector = Connector::query()->find($this->connectorId);
        if ($workspace === null || $connector === null) {
            return;
        }
        if ($connector->status === Connector::STATUS_PAUSED || $connector->isInBackoff()) {
            return;
        }

        // A full reindex flags the workspace as degraded for the duration so
        // consumers can surface the in-flight window; always clear it.
        $isFull = $this->mode === IngestRun::MODE_FULL;
        if ($isFull) {
            $workspace->forceFill(['degraded_until' => now()->addMinutes(15)])->save();
        }

        try {
            $ingester->run($workspace, $connector, $this->mode);
        } finally {
            if ($isFull) {
                DB::table('workspaces')->where('id', $workspace->id)->update(['degraded_until' => null]);
            }
        }
    }
}
