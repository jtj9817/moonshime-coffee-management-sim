<?php

use App\Models\Inventory;
use App\Services\Strategies\JustInTimeStrategy;

test('calculateReorderAmount returns correct amount for JIT strategy', function () {
    $strategy = new JustInTimeStrategy();
    $inventory = new Inventory(['quantity' => 10]);

    // Daily Demand: 5, Lead Time: 3 -> Target: 15
    // Current: 10 -> Reorder: 5
    $params = [
        'daily_demand' => 5,
        'lead_time' => 3,
    ];

    expect($strategy->calculateReorderAmount($inventory, $params))->toBe(5);
});

test('calculateReorderAmount returns zero if inventory exceeds target', function () {
    $strategy = new JustInTimeStrategy();
    $inventory = new Inventory(['quantity' => 20]);

    // Target: 15
    // Current: 20 -> Reorder: 0
    $params = [
        'daily_demand' => 5,
        'lead_time' => 3,
    ];

    expect($strategy->calculateReorderAmount($inventory, $params))->toBe(0);
});
