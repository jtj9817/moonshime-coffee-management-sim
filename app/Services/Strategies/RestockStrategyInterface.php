<?php

namespace App\Services\Strategies;

use App\Models\Inventory;

interface RestockStrategyInterface
{
    /**
     * Calculate the quantity to reorder based on the strategy.
     */
    public function calculateReorderQuantity(Inventory $inventory): int;
}
