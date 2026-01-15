<?php

namespace App\Services\Strategies;

use App\Models\Inventory;
use App\Services\InventoryMathService;

class SafetyStockStrategy implements RestockStrategyInterface
{
    public function __construct(
        protected InventoryMathService $mathService
    ) {}

    public function calculateReorderQuantity(Inventory $inventory): int
    {
        // Safety Stock logic:
        // Order enough to reach ROP + Safety Stock buffer.
        
        // Mock data - should come from Inventory analysis
        $avgDailyUsage = 10;
        $avgLeadTime = 5;
        $stdDevDemand = 2;
        $stdDevLeadTime = 1;
        $serviceLevel = 0.95; // 95% service level
        
        $zScore = $this->mathService->getZScore($serviceLevel);
        
        $safetyStock = $this->mathService->calculateSafetyStock(
            $stdDevDemand,
            $avgLeadTime,
            $stdDevLeadTime,
            $avgDailyUsage,
            $zScore
        );
        
        $rop = $this->mathService->calculateROP($avgDailyUsage, $avgLeadTime, $safetyStock);
        
        // If below ROP, order up to (ROP + EOQ or just ROP + Safety Stock?)
        // Let's implement an "Order Up To" policy where Target = ROP + SafetyStock (Conservative)
        $targetLevel = $rop + $safetyStock;
        
        return max(0, $targetLevel - $inventory->quantity);
    }
}
