<?php

use App\DTOs\InventoryContextDTO;

test('it can be instantiated with valid data', function () {
    $dto = new InventoryContextDTO(
        productId: 'prod-123',
        locationId: 'loc-456',
        quantity: 100,
        averageDailySales: 5.5
    );

    expect($dto->productId)->toBe('prod-123')
        ->and($dto->locationId)->toBe('loc-456')
        ->and($dto->quantity)->toBe(100)
        ->and($dto->averageDailySales)->toBe(5.5);
});

test('it is readonly', function () {
    $class = new ReflectionClass(InventoryContextDTO::class);
    expect($class->isReadOnly())->toBeTrue();
});
