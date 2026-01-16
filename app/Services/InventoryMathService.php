<?php

namespace App\Services;

class InventoryMathService
{
    /**
     * Z-Score approximation map for common service levels.
     */
    public function getZScore(float $serviceLevel): float
    {
        return match (true) {
            $serviceLevel >= 0.999 => 3.09,
            $serviceLevel >= 0.99 => 2.33,
            $serviceLevel >= 0.98 => 2.05,
            $serviceLevel >= 0.95 => 1.645,
            $serviceLevel >= 0.90 => 1.28,
            $serviceLevel >= 0.85 => 1.04,
            $serviceLevel >= 0.80 => 0.84,
            default => 0.0,
        };
    }

    /**
     * Calculate Safety Stock.
     * Formula: Z * sqrt( (AvgLeadTime * StdDevDemand^2) + (AvgDemand^2 * StdDevLeadTime^2) )
     */
    public function calculateSafetyStock(
        float $dailyUsageStdDev,
        float $avgLeadTime,
        float $leadTimeStdDev,
        float $avgDailyUsage,
        float $zScore
    ): int {
        $demandVariance = pow($dailyUsageStdDev, 2);
        $leadTimeVariance = pow($leadTimeStdDev, 2);

        $combinedUncertainty = sqrt(
            ($avgLeadTime * $demandVariance) + (pow($avgDailyUsage, 2) * $leadTimeVariance)
        );

        return (int) ceil($zScore * $combinedUncertainty);
    }

    /**
     * Calculate Reorder Point (ROP).
     * Formula: (Demand * LeadTime) + SafetyStock
     */
    public function calculateReorderPoint(
        float $avgDailyUsage,
        float $avgLeadTime,
        int $safetyStock
    ): int {
        return (int) ceil(($avgDailyUsage * $avgLeadTime) + $safetyStock);
    }

    /**
     * Calculate Economic Order Quantity (EOQ).
     * Formula: sqrt( (2 * AnnualDemand * SetupCost) / HoldingCostPerUnit )
     */
    public function calculateEOQ(
        float $annualDemand,
        float $setupCost,
        float $holdingCostPerUnit
    ): int {
        if ($holdingCostPerUnit <= 0) {
            return 0;
        }

        return (int) ceil(sqrt((2 * $annualDemand * $setupCost) / $holdingCostPerUnit));
    }

    /**
     * Calculate Days of Cover.
     */
    public function calculateDaysCover(int $onHand, float $avgDailyUsage): float
    {
        if ($avgDailyUsage <= 0) {
            return 999.0;
        }

        return round($onHand / $avgDailyUsage, 1);
    }
}