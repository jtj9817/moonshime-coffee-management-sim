<?php

namespace App\Services\Strategies;

use App\Interfaces\RestockStrategyInterface;
use App\Models\Inventory;
use App\Services\InventoryMathService;

class SafetyStockStrategy implements RestockStrategyInterface
{
    public function __construct(
        protected InventoryMathService $mathService
    ) {}

    /**
     * Safety Stock Strategy.
     *
     * Prioritizes stock availability.
     * Logic:
     * 1. Calculate Safety Stock based on variability and service level.
     * 2. Calculate Reorder Point (ROP).
     * 3. If Current Inventory <= ROP, order up to (ROP + Safety Stock).
     *
     * Required Params:
     * - avg_daily_usage (float)
     * - avg_lead_time (float)
     * - daily_usage_std_dev (float)
     * - lead_time_std_dev (float)
     * - service_level (float, default 0.95)
     */
    public function calculateReorderAmount(Inventory $inventory, array $params = []): int
    {
        $avgDailyUsage = $params['avg_daily_usage'] ?? 0;
        $avgLeadTime = $params['avg_lead_time'] ?? 0;
        $dailyUsageStdDev = $params['daily_usage_std_dev'] ?? 0;
        $leadTimeStdDev = $params['lead_time_std_dev'] ?? 0;
        $serviceLevel = $params['service_level'] ?? 0.95;

        // 1. Get Z-Score
        $zScore = $this->mathService->getZScore($serviceLevel);

        // 2. Calculate Safety Stock
        $safetyStock = $this->mathService->calculateSafetyStock(
            $dailyUsageStdDev,
            $avgLeadTime,
            $leadTimeStdDev,
            $avgDailyUsage,
            $zScore
        );

        // 3. Calculate ROP
        $rop = $this->mathService->calculateReorderPoint(
            $avgDailyUsage,
            $avgLeadTime,
            $safetyStock
        );

        // 4. Determine Order Amount
        // If we are above ROP, we don't order yet (Min-Max logic behavior)
        if ($inventory->quantity > $rop) {
            return 0;
        }

        // Target Level: ROP + Safety Stock (Conservative "Order Up To")
        // Note: In a real Min-Max, Max might be ROP + EOQ.
        // For this strategy, we ensure we recover the buffer.
        $targetLevel = $rop + $safetyStock;

        return max(0, $targetLevel - $inventory->quantity);
    }
}
