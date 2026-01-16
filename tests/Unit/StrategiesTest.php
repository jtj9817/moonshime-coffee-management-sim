<?php

use App\Models\Inventory;
use App\Services\InventoryMathService;
use App\Services\Strategies\JustInTimeStrategy;
use App\Services\Strategies\SafetyStockStrategy;

test('jit strategy calculates correct quantity', function () {
    $strategy = new JustInTimeStrategy;

    // Mock Inventory (attributes handled by Eloquent, but we can mock generic object or use partial mock)
    // Simpler: Just create a real instance but don't save it, or use Mockery.
    // Since Inventory is a Model, let's just make a new instance.
    $inventory = new Inventory(['quantity' => 10]);

    // Target = 10 * 3 = 30. Current = 10. Order = 20.
    $result = $strategy->calculateReorderAmount($inventory, ['daily_demand' => 10, 'lead_time' => 3]);

    expect($result)->toBe(20);
});

test('jit strategy returns zero if overstocked', function () {
    $strategy = new JustInTimeStrategy;
    $inventory = new Inventory(['quantity' => 50]);

    // Target = 30. Current = 50. Order = 0.
    $result = $strategy->calculateReorderAmount($inventory, ['daily_demand' => 10, 'lead_time' => 3]);

    expect($result)->toBe(0);
});

test('safety stock strategy uses math service', function () {
    // Mock the math service
    $mathMock = Mockery::mock(InventoryMathService::class);

    // Setup expectations
    $mathMock->shouldReceive('getZScore')->andReturn(1.645);
    $mathMock->shouldReceive('calculateSafetyStock')->andReturn(20); // Mocked SS
    $mathMock->shouldReceive('calculateReorderPoint')->andReturn(70); // Mocked ROP (50 usage + 20 SS)

    $strategy = new SafetyStockStrategy($mathMock);
    $inventory = new Inventory(['quantity' => 30]);

    // Target = ROP (70) + SS (20) = 90
    // Current = 30
    // Order = 60

    $result = $strategy->calculateReorderAmount($inventory);

    expect($result)->toBe(60);
});
