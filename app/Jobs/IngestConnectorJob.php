<?php

namespace App\Jobs;

use App\Models\Connector;
use App\Models\IngestRun;
use App\Models\Workspace;
use App\Services\Connectors\DocumentIngester;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestConnectorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $connectorId,
        public readonly string $mode = IngestRun::MODE_INCREMENTAL,
    ) {
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
        $ingester->run($workspace, $connector, $this->mode);
    }
}
