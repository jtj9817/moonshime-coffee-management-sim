<?php

namespace App\Interfaces;

use App\Models\Inventory;

interface RestockStrategyInterface
{
    public function calculateReorderAmount(Inventory $inventory, array $params = []): int;
}
