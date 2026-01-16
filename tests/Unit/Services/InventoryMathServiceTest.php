<?php

use App\Services\InventoryMathService;

beforeEach(function () {
    $this->service = new InventoryMathService;
});

test('getZScore returns correct value for standard service levels', function () {
    expect($this->service->getZScore(0.999))->toBe(3.09);
    expect($this->service->getZScore(0.99))->toBe(2.33);
    expect($this->service->getZScore(0.95))->toBe(1.645);
    expect($this->service->getZScore(0.50))->toBe(0.0);
});

test('calculateSafetyStock returns correct integer', function () {
    // formula: ceil(Z * sqrt( (AvgLeadTime * StdDevDemand^2) + (AvgDemand^2 * StdDevLeadTime^2) ))
    // Z = 1.645 (95%)
    // AvgLeadTime = 5
    // StdDevDemand = 2 -> Variance = 4
    // AvgDemand = 10
    // StdDevLeadTime = 1 -> Variance = 1

    // Term 1: 5 * 4 = 20
    // Term 2: 100 * 1 = 100
    // Sqrt(120) = 10.954
    // SS = 1.645 * 10.954 = 18.019
    // Ceil = 19

    $result = $this->service->calculateSafetyStock(
        dailyUsageStdDev: 2,
        avgLeadTime: 5,
        leadTimeStdDev: 1,
        avgDailyUsage: 10,
        zScore: 1.645
    );

    expect($result)->toBe(19);
});

test('calculateReorderPoint returns correct integer', function () {
    // (Demand * LeadTime) + SafetyStock
    // (10 * 5) + 19 = 69

    $result = $this->service->calculateReorderPoint(
        avgDailyUsage: 10,
        avgLeadTime: 5,
        safetyStock: 19
    );

    expect($result)->toBe(69);
});

test('calculateDaysCover returns correct float', function () {
    // OnHand / AvgDailyUsage
    // 100 / 10 = 10.0

    expect($this->service->calculateDaysCover(100, 10))->toBe(10.0);
    expect($this->service->calculateDaysCover(100, 0))->toBe(999.0);
});

test('calculateEOQ returns correct integer', function () {
    // sqrt( (2 * 1000 * 50) / 5 )
    // sqrt( 100000 / 5 ) = sqrt(20000) = 141.42
    // ceil = 142

    $result = $this->service->calculateEOQ(1000, 50, 5);
    expect($result)->toBe(142);
});
