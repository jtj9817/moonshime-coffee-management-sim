<?php

use App\DTOs\InventoryAdvisoryDTO;

test('it can be instantiated', function () {
    $dto = new InventoryAdvisoryDTO(
        restockAmount: 50,
        reasoning: 'Stock is low due to heatwave.'
    );

    expect($dto->restockAmount)->toBe(50)
        ->and($dto->reasoning)->toBe('Stock is low due to heatwave.');
});

test('it is readonly', function () {
    $class = new ReflectionClass(InventoryAdvisoryDTO::class);
    expect($class->isReadOnly())->toBeTrue();
});
