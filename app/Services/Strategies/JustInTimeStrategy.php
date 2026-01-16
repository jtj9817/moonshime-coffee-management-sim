<?php

namespace App\Services\Strategies;

use App\Interfaces\RestockStrategyInterface;
use App\Models\Inventory;

class JustInTimeStrategy implements RestockStrategyInterface
{
    /**
     * Just-In-Time Strategy: Replenish only for the demand during lead time.
     *
     * Formula: max(0, (DailyDemand * LeadTime) - CurrentInventory)
     *
     * Required Params:
     * - daily_demand (float)
     * - lead_time (int)
     */
    public function calculateReorderAmount(Inventory $inventory, array $params = []): int
    {
        $dailyDemand = $params['daily_demand'] ?? 0;
        $leadTime = $params['lead_time'] ?? 0;

        $targetInventory = (int) ceil($dailyDemand * $leadTime);
        $reorderAmount = $targetInventory - $inventory->quantity;

        return max(0, $reorderAmount);
    }
}
