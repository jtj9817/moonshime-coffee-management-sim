<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Centralized storage fee calculation service (TICKET-005).
 *
 * Single source of truth for storage fee calculations to prevent
 * logic duplication across listeners.
 */
class StorageFeeCalculator
{
    /**
     * Calculate total storage fees for a user's inventory.
     * Formula: SUM(inventory.quantity * product.storage_cost)
     *
     * @param  int  $userId  The user ID
     * @return int Total storage cost in cents
     */
    public function calculate(int $userId): int
    {
        return (int) DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->where('inventories.user_id', $userId)
            ->sum(DB::raw('inventories.quantity * products.storage_cost'));
    }

    /**
     * Calculate storage fees for a specific location.
     * Formula: SUM(inventory.quantity * product.storage_cost) for location
     *
     * @param  int  $userId  The user ID
     * @param  string  $locationId  The location ID
     * @return int Storage cost for location in cents
     */
    public function calculateForLocation(int $userId, string $locationId): int
    {
        return (int) DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->where('inventories.user_id', $userId)
            ->where('inventories.location_id', $locationId)
            ->sum(DB::raw('inventories.quantity * products.storage_cost'));
    }
}
