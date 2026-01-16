<?php

use App\Interfaces\RestockStrategyInterface;
use App\Models\Inventory;

test('interface exists', function () {
    expect(interface_exists(RestockStrategyInterface::class))->toBeTrue();
});

test('interface has calculateReorderAmount method', function () {
    $reflection = new ReflectionClass(RestockStrategyInterface::class);
    $method = $reflection->getMethod('calculateReorderAmount');
    
    expect($method->hasReturnType())->toBeTrue();
    expect($method->getReturnType()->getName())->toBe('int');
    
    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getType()->getName())->toBe(Inventory::class);
});
