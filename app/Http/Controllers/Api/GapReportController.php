<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GapCluster;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GapReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $unansweredCount = UnansweredQuestion::query()
            ->where('workspace_id', $workspace->id)
            ->count();

        $clusters = GapCluster::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('member_count')
            ->limit(50)
            ->get();

        // Below the threshold, expose raw queries.
        if ($unansweredCount < 50 || $clusters->isEmpty()) {
            $raw = UnansweredQuestion::query()
                ->with('parentQuery:id,query_text,created_at')
                ->where('workspace_id', $workspace->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
            return response()->json([
                'mode' => 'raw',
                'unanswered_count' => $unansweredCount,
                'raw' => $raw->map(fn ($u) => [
                    'id' => $u->id,
                    'reason' => $u->reason,
                    'query_text' => $u->parentQuery?->query_text,
                    'created_at' => $u->created_at?->toIso8601String(),
                ])->all(),
            ]);
        }

        return response()->json([
            'mode' => 'clustered',
            'unanswered_count' => $unansweredCount,
            'clusters' => $clusters->map(fn (GapCluster $c) => [
                'id' => $c->id,
                'centroid_text' => $c->centroid_text,
                'member_count' => $c->member_count,
                'addressed_at' => $c->addressed_at?->toIso8601String(),
                'last_recomputed_at' => $c->last_recomputed_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function markAddressed(Request $request, string $id): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        $cluster = GapCluster::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($id);
        $cluster->forceFill(['addressed_at' => now()])->save();
        return response()->json(['id' => $cluster->id, 'addressed_at' => $cluster->addressed_at?->toIso8601String()]);
    }
}
