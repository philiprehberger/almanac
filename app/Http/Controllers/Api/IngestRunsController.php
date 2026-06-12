<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IngestRun;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngestRunsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        $runs = IngestRun::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $runs->map(fn (IngestRun $r) => [
                'id' => $r->id,
                'connector_id' => $r->connector_id,
                'mode' => $r->mode,
                'status' => $r->status,
                'docs_added' => $r->docs_added,
                'docs_updated' => $r->docs_updated,
                'docs_removed' => $r->docs_removed,
                'docs_failed' => $r->docs_failed,
                'last_error' => $r->last_error,
                'started_at' => $r->started_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
