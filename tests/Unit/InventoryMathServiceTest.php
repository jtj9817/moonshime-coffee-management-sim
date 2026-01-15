<?php

use App\Services\InventoryMathService;

test('it calculates correct Z-Score', function () {
    $service = new InventoryMathService();

    expect($service->getZScore(0.95))->toBe(1.645);
    expect($service->getZScore(0.99))->toBe(2.33);
    expect($service->getZScore(0.50))->toBe(0.0);
});

test('it calculates safety stock correctly', function () {
    $service = new InventoryMathService();

    // Example values
    // Daily Usage StdDev: 2
    // Avg Lead Time: 5 days
    // Lead Time StdDev: 1 day
    // Avg Daily Usage: 10
    // Z-Score: 1.645 (95%)
    
    // Variance Demand = 2^2 = 4
    // Variance LeadTime = 1^2 = 1
    // Term 1: 5 * 4 = 20
    // Term 2: 100 * 1 = 100
    // Sqrt(120) ≈ 10.95
    // Safety Stock = 1.645 * 10.95 ≈ 18.02 -> 19
    
    $result = $service->calculateSafetyStock(2, 5, 1, 10, 1.645);
    
    expect($result)->toBe(19);
});

test('it calculates ROP correctly', function () {
    $service = new InventoryMathService();

    // Avg Daily Usage: 10
    // Avg Lead Time: 5
    // Safety Stock: 19
    // ROP = (10 * 5) + 19 = 69

    $result = $service->calculateROP(10, 5, 19);

    expect($result)->toBe(69);
});

test('it calculates days cover correctly', function () {
    $service = new InventoryMathService();

    expect($service->calculateDaysCover(100, 10))->toBe(10.0);
    expect($service->calculateDaysCover(50, 0))->toBe(999.0);
    expect($service->calculateDaysCover(25, 5))->toBe(5.0);
});