<?php

namespace App\Interfaces;

use App\DTOs\InventoryAdvisoryDTO;
use App\DTOs\InventoryContextDTO;

interface AiProviderInterface
{
    public function generateAdvisory(InventoryContextDTO $context): InventoryAdvisoryDTO;
}
