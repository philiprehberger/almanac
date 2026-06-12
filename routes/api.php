<?php

use App\Http\Controllers\Api\ApiKeysController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConnectorsController;
use App\Http\Controllers\Api\ConversationsController;
use App\Http\Controllers\Api\CostController;
use App\Http\Controllers\Api\DeletionRequestsController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\GapReportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IngestRunsController;
use App\Http\Controllers\Api\PromptInjectionSignalsController;
use App\Http\Controllers\Api\SourcesController;
use App\Http\Controllers\Api\WorkspacesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('healthz', HealthController::class)->name('v1.healthz');

    Route::middleware(['api.key', 'workspace.rate-limit'])->group(function () {
        // Chat (scope: chat_only and above)
        Route::post('chat', ChatController::class)->name('v1.chat');
        Route::get('conversations/{id}', [ConversationsController::class, 'show'])->name('v1.conversations.show');
        Route::get('sources/{docId}', [SourcesController::class, 'show'])->name('v1.sources.show');

        // Feedback (scope: chat_and_feedback and above)
        Route::middleware(['api.key:chat_and_feedback'])->group(function () {
            Route::post('feedback', FeedbackController::class)->name('v1.feedback');
        });

        // Workspace context
        Route::get('workspaces/current', [WorkspacesController::class, 'current'])->name('v1.workspaces.current');

        // Admin-read scope and above
        Route::middleware(['api.key:admin_read'])->group(function () {
            Route::get('connectors', [ConnectorsController::class, 'index'])->name('v1.connectors.index');
            Route::get('connectors/{id}', [ConnectorsController::class, 'show'])->name('v1.connectors.show');
            Route::get('ingest-runs', IngestRunsController::class)->name('v1.ingest-runs');
            Route::get('gap-report', [GapReportController::class, 'index'])->name('v1.gap-report.index');
            Route::get('audit-log', AuditController::class)->name('v1.audit-log');
            Route::get('prompt-injection-signals', [PromptInjectionSignalsController::class, 'index'])->name('v1.prompt-injection-signals');
            Route::get('cost', CostController::class)->name('v1.cost');
            Route::get('deletion-requests/{id}', [DeletionRequestsController::class, 'show'])->name('v1.deletion-requests.show');
        });

        // Admin-write scope only
        Route::middleware(['api.key:admin_write'])->group(function () {
            Route::post('connectors', [ConnectorsController::class, 'store'])->name('v1.connectors.store');
            Route::patch('connectors/{id}', [ConnectorsController::class, 'update'])->name('v1.connectors.update');
            Route::delete('connectors/{id}', [ConnectorsController::class, 'destroy'])->name('v1.connectors.destroy');
            Route::post('connectors/{id}/reindex', [ConnectorsController::class, 'reindex'])->name('v1.connectors.reindex');
            Route::post('connectors/{id}/pause', [ConnectorsController::class, 'pause'])->name('v1.connectors.pause');
            Route::post('connectors/{id}/resume', [ConnectorsController::class, 'resume'])->name('v1.connectors.resume');

            Route::post('gap-clusters/{id}/mark-addressed', [GapReportController::class, 'markAddressed'])->name('v1.gap-clusters.mark-addressed');

            Route::get('api-keys', [ApiKeysController::class, 'index'])->name('v1.api-keys.index');
            Route::post('api-keys', [ApiKeysController::class, 'store'])->name('v1.api-keys.store');
            Route::delete('api-keys/{id}', [ApiKeysController::class, 'destroy'])->name('v1.api-keys.destroy');

            Route::post('deletion-requests', [DeletionRequestsController::class, 'store'])->name('v1.deletion-requests.store');
        });
    });
});
