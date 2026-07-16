<?php

namespace App\Services\Cost;

use App\Models\Workspace;
use App\Models\WorkspaceCostDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetEnforcer
{
    /** Conservative upper bound charged up-front per request, reconciled on settle. */
    private const ESTIMATED_REQUEST_COST_USD = 0.05;

    /**
     * Atomically check the cap and pre-charge an estimate before the LLM call.
     *
     * A per-workspace transaction advisory lock serializes concurrent requests
     * so N of them can't all pass the check before any of them records — closing
     * the check-then-act race. Pre-charging also means a request that dies after
     * the provider call still consumed budget. Returns the estimate charged, to
     * be reconciled by {@see settle}.
     *
     * @throws BudgetExceededException
     */
    public function reserve(Workspace $workspace): float
    {
        return DB::transaction(function () use ($workspace) {
            DB::statement('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['budget:'.$workspace->id]);

            $monthToDate = $this->monthToDateCost($workspace);
            $cap = (float) $workspace->monthly_budget_usd;
            if ($cap > 0 && $monthToDate >= $cap) {
                throw new BudgetExceededException($workspace->id, $monthToDate, $cap);
            }

            $estimate = self::ESTIMATED_REQUEST_COST_USD;
            $this->upsert($workspace, $estimate, 0, 0, 0);

            return $estimate;
        });
    }

    /**
     * Reconcile the pre-charged estimate to the actual cost once the request
     * completes, and record the real token counts + the query itself.
     */
    public function settle(Workspace $workspace, int $tokensIn, int $tokensOut, float $actualCost, float $estimate): void
    {
        $this->upsert($workspace, $actualCost - $estimate, $tokensIn, $tokensOut, 1);
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
        $this->upsert($workspace, $costUsd, $tokensIn, $tokensOut, 1);
    }

    /**
     * Atomic per-day accumulation. `queryCountDelta` lets a reservation add cost
     * without counting a query (the query is counted once, on settle).
     */
    private function upsert(Workspace $workspace, float $costDelta, int $tokensIn, int $tokensOut, int $queryCountDelta): void
    {
        $today = Carbon::now()->toDateString();

        DB::statement(
            'INSERT INTO workspace_cost_daily
                 (workspace_id, day, cost_usd, query_count, tokens_in, tokens_out, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON CONFLICT (workspace_id, day) DO UPDATE SET
                 cost_usd    = workspace_cost_daily.cost_usd    + EXCLUDED.cost_usd,
                 query_count = workspace_cost_daily.query_count + EXCLUDED.query_count,
                 tokens_in   = workspace_cost_daily.tokens_in   + EXCLUDED.tokens_in,
                 tokens_out  = workspace_cost_daily.tokens_out  + EXCLUDED.tokens_out,
                 updated_at  = NOW()',
            [$workspace->id, $today, $costDelta, $queryCountDelta, $tokensIn, $tokensOut]
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
