<?php

namespace App\DTOs;

readonly class InventoryContextDTO
{
    public function __construct(
        public string $productId,
        public string $locationId,
        public int $quantity,
        public float $averageDailySales,
    ) {}
}
