<?php

namespace App\Services\Strategies;

use App\Models\Inventory;

class JustInTimeStrategy implements RestockStrategyInterface
{
    public function calculateReorderQuantity(Inventory $inventory): int
    {
        // Just-In-Time (JIT) logic:
        // Only order enough to cover immediate demand plus a tiny buffer.
        // Assuming a hardcoded immediate demand for now, or using average usage.
        
        // Mock logic: Order exactly 3 days of usage.
        // In real implementation, this would look at future scheduled orders/demand.
        $avgDailyUsage = 10; // TODO: Fetch from Inventory History
        $daysToCover = 3;
        
        $targetStock = $avgDailyUsage * $daysToCover;
        $currentStock = $inventory->quantity;
        
        return max(0, $targetStock - $currentStock);
    }
}
