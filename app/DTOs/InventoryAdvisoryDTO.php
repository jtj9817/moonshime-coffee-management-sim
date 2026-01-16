<?php

namespace App\DTOs;

readonly class InventoryAdvisoryDTO
{
    public function __construct(
        public int $restockAmount,
        public string $reasoning,
        public float $confidenceScore,
        public string $suggestedAction,
    ) {}
}
