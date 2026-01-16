<?php

namespace App\Interfaces;

use App\DTOs\InventoryContextDTO;
use App\DTOs\InventoryAdvisoryDTO;

interface AiProviderInterface
{
    public function generateAdvisory(InventoryContextDTO $context): InventoryAdvisoryDTO;
}
