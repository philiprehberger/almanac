<?php

use App\Jobs\ClusterUnansweredQuestionsJob;
use App\Jobs\IngestConnectorJob;
use App\Jobs\PruneExpiredConversationsJob;
use App\Models\Connector;
use App\Models\Workspace;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Per-source incremental re-sync. Each active connector polls its source
// every 10 minutes; backoff + paused connectors are skipped inside the job.
Schedule::call(function () {
    Connector::query()
        ->where('status', Connector::STATUS_ACTIVE)
        ->chunkById(100, function ($connectors) {
            foreach ($connectors as $c) {
                if ($c->backoff_until !== null && $c->backoff_until->isFuture()) {
                    continue;
                }
                IngestConnectorJob::dispatch($c->workspace_id, $c->id);
            }
        });
})->everyTenMinutes()->name('almanac:ingest-tick')->withoutOverlapping();

// Nightly: cluster unanswered_questions for every workspace with enough volume.
Schedule::call(function () {
    Workspace::query()->chunkById(100, function ($workspaces) {
        foreach ($workspaces as $w) {
            ClusterUnansweredQuestionsJob::dispatch($w->id);
        }
    });
})->dailyAt('02:00')->name('almanac:cluster-gap-report');

// Daily: prune expired conversations + cascade.
Schedule::job(new PruneExpiredConversationsJob())
    ->dailyAt('03:00')
    ->name('almanac:prune-expired-conversations');
