<?php

namespace App\Console\Commands;

use App\Services\UsageAggregationService;
use Illuminate\Console\Command;

class AggregateCurrentCycleUsageCommand extends Command
{
    protected $signature = 'usage:aggregate-current-cycle';

    protected $description = 'Recompute usage summaries for the active billing cycle';

    public function handle(UsageAggregationService $usageAggregationService): int
    {
        $cycle = $usageAggregationService->aggregateCurrentCycle();

        $this->info(sprintf('Aggregated billing cycle %s', $cycle->label));

        return self::SUCCESS;
    }
}
