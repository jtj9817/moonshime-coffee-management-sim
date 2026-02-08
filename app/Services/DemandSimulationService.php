<?php

namespace App\Services;

use App\Models\GameState;
use App\Models\DemandEvent;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LostSale;
use App\Models\SpikeEvent;
use App\Events\StockoutOccurred;
use Illuminate\Support\Facades\Log;

class DemandSimulationService
{
    /**
     * Baseline daily consumption per product (in units).
     */
    protected array $baselineConsumption = [
        'default' => 5,
    ];

    /**
     * Price elasticity factor for demand calculation.
     * Higher = more price-sensitive demand.
     */
    protected float $elasticityFactor = 0.5;

    /**
     * Pre-loaded demand spikes for current simulation (TICKET-003).
     * @var \Illuminate\Support\Collection<int, SpikeEvent>|null
     */
    protected ?\Illuminate\Support\Collection $demandSpikes = null;

    /**
     * Process daily consumption for all stores.
     * This simulates customer demand and inventory depletion.
     */
    public function processDailyConsumption(GameState $gameState, int $day): void
    {
        $userId = $gameState->user_id;

        // Pre-load all demand spikes for this user/day (TICKET-003 & TICKET-004)
        // Uses explicit day-based filtering instead of relying on is_active flag
        $this->demandSpikes = $this->preloadDemandSpikes($userId, $day);

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

                // Apply price elasticity: EffectiveDemand = BaseDemand * (StandardPrice / CurrentPrice)^ElasticityFactor
                $elasticityMultiplier = $this->getPriceElasticityMultiplier($store, $inventory->product);
                $consumption = (int) ($consumption * $elasticityMultiplier);

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
                // Use sell_price if set on location, otherwise use product's unit_price
                $unitPrice = (int) ($store->sell_price ?? $inventory->product->unit_price ?? 0);
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
                    LostSale::create([
                        'user_id' => $userId,
                        'location_id' => $store->id,
                        'product_id' => $inventory->product_id,
                        'day' => $day,
                        'quantity_lost' => $lostQuantity,
                        'potential_revenue_lost' => $lostRevenue,
                    ]);

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
     * Calculate price elasticity multiplier.
     * Formula: (StandardPrice / CurrentPrice)^ElasticityFactor
     * Returns 1.0 when sell_price is null (standard pricing).
     */
    protected function getPriceElasticityMultiplier(Location $store, $product): float
    {
        $sellPrice = $store->sell_price;
        $standardPrice = (int) ($product->unit_price ?? 0);

        // No elasticity if sell_price not set or standard price is 0
        if ($sellPrice === null || $standardPrice <= 0 || $sellPrice <= 0) {
            return 1.0;
        }

        return pow($standardPrice / $sellPrice, $this->elasticityFactor);
    }

    /**
     * Get the demand multiplier from pre-loaded demand spikes (TICKET-003).
     * Filters spikes in memory instead of querying the database per inventory item.
     * Returns 1.0 if no active spikes, or the spike's magnitude if one is active.
     */
    protected function getDemandMultiplier(int $userId, string $locationId, string $productId): float
    {
        if ($this->demandSpikes === null || $this->demandSpikes->isEmpty()) {
            return 1.0;
        }

        // Filter pre-loaded spikes in memory (no DB query)
        $maxMagnitude = $this->demandSpikes
            ->filter(fn ($s) =>
                ($s->location_id === $locationId || $s->location_id === null) &&
                ($s->product_id === $productId || $s->product_id === null)
            )
            ->max('magnitude');

        return $maxMagnitude ? (float) $maxMagnitude : 1.0;
    }

    /**
     * Pre-load all active demand spikes with explicit day-based filtering (TICKET-004).
     * Uses starts_at_day <= $day and ends_at_day > $day instead of relying on is_active flag.
     */
    protected function preloadDemandSpikes(int $userId, int $day): \Illuminate\Support\Collection
    {
        return SpikeEvent::forUser($userId)
            ->where('type', 'demand')
            ->where('starts_at_day', '<=', $day)
            ->where('ends_at_day', '>', $day)
            ->get();
    }
}
