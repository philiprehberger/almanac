<?php

namespace App\Services\Cost;

class BudgetExceededException extends \RuntimeException
{
    public function __construct(
        public readonly string $workspaceId,
        public readonly float $monthToDate,
        public readonly float $cap,
    ) {
        parent::__construct(
            sprintf(
                'Workspace %s month-to-date cost $%.4f exceeds cap $%.2f',
                $workspaceId,
                $monthToDate,
                $cap
            )
        );
    }
}
