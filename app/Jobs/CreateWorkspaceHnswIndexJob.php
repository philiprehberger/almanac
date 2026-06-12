<?php

namespace App\Jobs;

use App\Models\Workspace;
use App\Services\Workspaces\HnswIndexManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateWorkspaceHnswIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $workspaceId)
    {
    }

    public function handle(HnswIndexManager $manager): void
    {
        $workspace = Workspace::query()->find($this->workspaceId);
        if ($workspace === null) {
            return;
        }
        $manager->ensure($workspace);
    }
}
