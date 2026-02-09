<?php

use App\Interfaces\RestockStrategyInterface;
use App\Services\InventoryManagementService;
use App\Services\InventoryMathService;
use App\Services\Strategies\JustInTimeStrategy;
use App\Services\Strategies\SafetyStockStrategy;

test('inventory math service is registered as singleton', function () {
    $service1 = app(InventoryMathService::class);
    $service2 = app(InventoryMathService::class);

    expect($service1)->toBeInstanceOf(InventoryMathService::class);
    expect($service1)->toBe($service2);
});

test('restock strategy interface binds to just in time by default', function () {
    $strategy = app(RestockStrategyInterface::class);

    expect($strategy)->toBeInstanceOf(JustInTimeStrategy::class);
});

test('strategies can be resolved explicitly', function () {
    $jit = app(JustInTimeStrategy::class);
    $ss = app(SafetyStockStrategy::class);

    expect($jit)->toBeInstanceOf(JustInTimeStrategy::class);
    expect($ss)->toBeInstanceOf(SafetyStockStrategy::class);
});

test('inventory management service resolves with dependencies', function () {
    $service = app(InventoryManagementService::class);

    expect($service)->toBeInstanceOf(InventoryManagementService::class);
});
