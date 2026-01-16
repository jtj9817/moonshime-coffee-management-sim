<?php

use App\Interfaces\AiProviderInterface;
use App\DTOs\InventoryContextDTO;
use App\DTOs\InventoryAdvisoryDTO;

test('interface exists', function () {
    expect(interface_exists(AiProviderInterface::class))->toBeTrue();
});

test('interface has generateAdvisory method', function () {
    $reflection = new ReflectionClass(AiProviderInterface::class);
    $method = $reflection->getMethod('generateAdvisory');
    
    expect($method->hasReturnType())->toBeTrue();
    expect($method->getReturnType()->getName())->toBe(InventoryAdvisoryDTO::class);
    
    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getType()->getName())->toBe(InventoryContextDTO::class);
});
