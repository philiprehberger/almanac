<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromptInjectionSignal;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptInjectionSignalsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        $limit = max(1, min(200, (int) $request->query('limit', 50)));

        $rows = PromptInjectionSignal::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (PromptInjectionSignal $s) => [
                'id' => $s->id,
                'query_id' => $s->query_id,
                'signal_kind' => $s->signal_kind,
                'details' => $s->details,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
