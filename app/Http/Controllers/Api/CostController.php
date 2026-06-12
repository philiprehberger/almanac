<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\Cost\BudgetEnforcer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostController extends Controller
{
    public function __construct(private readonly BudgetEnforcer $budget)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $mtd = $this->budget->monthToDateCost($workspace);
        $cap = (float) $workspace->monthly_budget_usd;
        $daily = DB::table('workspace_cost_daily')
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('day')
            ->limit(30)
            ->get(['day', 'cost_usd', 'query_count', 'tokens_in', 'tokens_out']);

        $top = DB::table('queries')
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('cost_usd')
            ->limit(10)
            ->get(['id', 'query_text', 'cost_usd', 'tokens_in', 'tokens_out', 'created_at']);

        return response()->json([
            'workspace_id' => $workspace->id,
            'month_to_date_usd' => round($mtd, 4),
            'monthly_cap_usd' => round($cap, 2),
            'usage_ratio' => $cap > 0 ? round(min(1.0, $mtd / $cap), 4) : null,
            'daily' => $daily,
            'top_queries' => $top,
        ]);
    }
}
