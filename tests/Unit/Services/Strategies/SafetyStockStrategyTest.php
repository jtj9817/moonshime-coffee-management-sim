<?php

use App\Models\Inventory;
use App\Services\InventoryMathService;
use App\Services\Strategies\SafetyStockStrategy;

beforeEach(function () {
    $this->mathService = new InventoryMathService();
    $this->strategy = new SafetyStockStrategy($this->mathService);
});

test('calculateReorderAmount triggers order when below ROP', function () {
    $inventory = new Inventory(['quantity' => 50]);

    // Params configuration
    // AvgUsage: 10, LeadTime: 5 -> Base Demand: 50
    // Variability -> SS (approx 19 from previous math test) -> ROP = 50 + 19 = 69
    // Target = 69 + 19 = 88
    // Current 50 <= 69 (ROP) -> Order 88 - 50 = 38
    
    $params = [
        'avg_daily_usage' => 10,
        'avg_lead_time' => 5,
        'daily_usage_std_dev' => 2,
        'lead_time_std_dev' => 1,
        'service_level' => 0.95 // Z=1.645
    ];

    // Let's verify expectations based on MathService logic
    // SS = ceil(1.645 * sqrt( (5*4) + (100*1) )) = ceil(1.645 * 10.95) = 19
    // ROP = (10 * 5) + 19 = 69
    // Target = 69 + 19 = 88
    
    $amount = $this->strategy->calculateReorderAmount($inventory, $params);
    
    expect($amount)->toBe(38); // 88 - 50
});

test('calculateReorderAmount returns zero when above ROP', function () {
    $inventory = new Inventory(['quantity' => 70]);

    // Using same params as above, ROP is 69.
    // Current 70 > 69 -> No order.
    
    $params = [
        'avg_daily_usage' => 10,
        'avg_lead_time' => 5,
        'daily_usage_std_dev' => 2,
        'lead_time_std_dev' => 1,
        'service_level' => 0.95
    ];

    $amount = $this->strategy->calculateReorderAmount($inventory, $params);
    
    expect($amount)->toBe(0);
});

test('calculateReorderAmount uses default service level if missing', function () {
    $inventory = new Inventory(['quantity' => 50]);
    
    $params = [
        'avg_daily_usage' => 10,
        'avg_lead_time' => 5,
        'daily_usage_std_dev' => 2,
        'lead_time_std_dev' => 1,
        // service_level missing, should default to 0.95
    ];

    $amount = $this->strategy->calculateReorderAmount($inventory, $params);
    
    // Should behave exactly like the first test
    expect($amount)->toBe(38);
});
