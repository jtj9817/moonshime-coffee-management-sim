<?php

namespace App\Services;

use App\Models\GameState;
use App\Models\DemandEvent;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\SpikeEvent;
use App\Events\StockoutOccurred;
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

        // Get all retail/store locations that have inventory for this user
        $stores = Location::where('type', 'store')
            ->whereHas('inventories', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with(['inventories' => function ($query) use ($userId) {
                // Load only the current user's inventories to avoid cross-user depletion.
                $query->where('user_id', $userId)->with('product');
            }])
            ->get();

        foreach ($stores as $store) {
            // Get inventory at this location
            $inventories = $store->inventories;

            foreach ($inventories as $inventory) {
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

                $requestedQuantity = max(0, (int) $consumption);
                if ($requestedQuantity <= 0) {
                    continue;
                }

                // Decrement inventory (cannot go below 0)
                $availableQuantity = max(0, (int) $inventory->quantity);
                $actualConsumed = min($requestedQuantity, $availableQuantity);
                if ($actualConsumed > 0) {
                    $inventory->decrement('quantity', $actualConsumed);
                }

                $lostQuantity = $requestedQuantity - $actualConsumed;
                $unitPrice = (int) ($inventory->product->unit_price ?? 0);
                $revenue = $actualConsumed * $unitPrice;
                $lostRevenue = $lostQuantity * $unitPrice;

                $demandEvent = DemandEvent::create([
                    'user_id' => $userId,
                    'day' => $day,
                    'location_id' => $store->id,
                    'product_id' => $inventory->product_id,
                    'requested_quantity' => $requestedQuantity,
                    'fulfilled_quantity' => $actualConsumed,
                    'lost_quantity' => $lostQuantity,
                    'unit_price' => $unitPrice,
                    'revenue' => $revenue,
                    'lost_revenue' => $lostRevenue,
                ]);

                if ($lostQuantity > 0) {
                    Log::warning("Stockout at {$store->name} for product {$inventory->product_id}");
                    event(new StockoutOccurred($demandEvent));
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
