<?php

namespace App\Services;

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\SpikeEvent;
use Illuminate\Support\Facades\Log;

class DemandSimulationService
{
    /**
     * Baseline daily consumption per product (in units).
     * TODO: Move to config or database
     */
    protected array $baselineConsumption = [
        // Product category => units per day per store
        'default' => 5,
    ];

    /**
     * Process daily consumption for all stores.
     * This simulates customer demand and inventory depletion.
     */
    public function processDailyConsumption(GameState $gameState, int $day): void
    {
        $userId = $gameState->user_id;

        // Get all retail/store locations for this user
        $stores = Location::where('type', 'store')->get();

        foreach ($stores as $store) {
            // Get inventory at this location
            $inventories = Inventory::where('location_id', $store->id)->get();

            foreach ($inventories as $inventory) {
                if ($inventory->quantity <= 0) {
                    continue; // Already empty
                }

                // Calculate baseline consumption
                $baseline = $this->getBaselineConsumption($inventory->product);

                // Apply random variance (±20%)
                $variance = 1 + (rand(-20, 20) / 100);
                $consumption = (int) ($baseline * $variance);

                // Check for active demand spikes affecting this product/location
                $demandMultiplier = $this->getDemandMultiplier($userId, $store->id, $inventory->product_id);
                
                if ($demandMultiplier > 1.0) {
                    $consumption = (int) ($consumption * $demandMultiplier);
                    Log::info("Demand spike active: product {$inventory->product_id} at {$store->name}, multiplier: {$demandMultiplier}x");
                }

                // Decrement inventory (cannot go below 0)
                $actualConsumed = min($consumption, $inventory->quantity);
                $inventory->decrement('quantity', $actualConsumed);

                // TODO: Record stockout event if demand > available quantity
                if ($consumption > $actualConsumed) {
                    Log::warning("Stockout at {$store->name} for product {$inventory->product_id}");
                }
            }
        }
    }

    /**
     * Get baseline daily consumption for a product.
     */
    protected function getBaselineConsumption($product): int
    {
        // You can extend this to have per-product or per-category baselines
       return $this->baselineConsumption['default'] ?? 5;
    }

    /**
     * Get the demand multiplier from active demand spikes.
     * Returns 1.0 if no active spikes, or the spike's magnitude if one is active.
     */
    protected function getDemandMultiplier(int $userId, string $locationId, string $productId): float
    {
        $spike = SpikeEvent::forUser($userId)
            ->active()
            ->where('type', 'demand')
            ->where(function ($query) use ($locationId) {
                // Match exact location or global spikes (null location_id)
                $query->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            })
            ->where(function ($query) use ($productId) {
                // Match exact product or global spikes (null product_id)
                $query->where('product_id', $productId)
                    ->orWhereNull('product_id');
            })
            ->first();

        return $spike ? (float) $spike->magnitude : 1.0;
    }
}
