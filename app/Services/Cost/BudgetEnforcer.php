<?php

namespace App\Services\Cost;

use App\Models\Workspace;
use App\Models\WorkspaceCostDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetEnforcer
{
    public function ensureUnderCap(Workspace $workspace): void
    {
        $monthToDate = $this->monthToDateCost($workspace);
        $cap = (float) $workspace->monthly_budget_usd;
        if ($cap > 0 && $monthToDate >= $cap) {
            throw new BudgetExceededException($workspace->id, $monthToDate, $cap);
        }
    }

    public function monthToDateCost(Workspace $workspace): float
    {
        $row = DB::selectOne(
            'SELECT COALESCE(SUM(cost_usd), 0) AS total
             FROM workspace_cost_daily
             WHERE workspace_id = ?
               AND day >= date_trunc(\'month\', CURRENT_DATE)',
            [$workspace->id]
        );
        return (float) ($row->total ?? 0.0);
    }

    public function record(Workspace $workspace, int $tokensIn, int $tokensOut, float $costUsd): void
    {
        $today = Carbon::now()->toDateString();

        DB::statement(
            'INSERT INTO workspace_cost_daily
                 (workspace_id, day, cost_usd, query_count, tokens_in, tokens_out, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, ?, NOW(), NOW())
             ON CONFLICT (workspace_id, day) DO UPDATE SET
                 cost_usd    = workspace_cost_daily.cost_usd    + EXCLUDED.cost_usd,
                 query_count = workspace_cost_daily.query_count + 1,
                 tokens_in   = workspace_cost_daily.tokens_in   + EXCLUDED.tokens_in,
                 tokens_out  = workspace_cost_daily.tokens_out  + EXCLUDED.tokens_out,
                 updated_at  = NOW()',
            [$workspace->id, $today, $costUsd, $tokensIn, $tokensOut]
        );
    }

    public function approachingCap(Workspace $workspace, float $thresholdRatio = 0.8): bool
    {
        $cap = (float) $workspace->monthly_budget_usd;
        if ($cap <= 0) {
            return false;
        }
        return $this->monthToDateCost($workspace) >= $cap * $thresholdRatio;
    }
}
