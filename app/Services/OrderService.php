<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected LogisticsService $logistics;

    protected PricingService $pricingService;

    public function __construct(LogisticsService $logistics, PricingService $pricingService)
    {
        $this->logistics = $logistics;
        $this->pricingService = $pricingService;
    }

    /**
     * Create a new order with multi-hop shipments.
     *
     * @param  array  $items  Array of ['product_id', 'quantity', 'cost_per_unit']
     * @param  Collection|null  $path  Pre-calculated path or null to calculate
     * @param  bool  $autoSubmit  When false, order is created in draft state for manual submission.
     *
     * @throws \Exception
     */
    public function createOrder(
        User $user,
        Vendor $vendor,
        Location $targetLocation,
        array $items,
        ?Collection $path = null,
        bool $autoSubmit = true
    ): Order {
        // 1. Calculate Path if not provided
        if (! $path) {
            // Vendors are not locations in the graph directly usually, but their source_location is?
            // Wait, GraphSeeder: source_id => vendor->id.
            // So Vendor IS a location type.
            $vendorLocation = Location::where('id', $vendor->id)->first();
            if (! $vendorLocation) {
                // Fallback: Check if Vendor has a 'location' relation or if the ID matches
                // In this app, Vendor seems to strictly be a Vendor model, and Location is separate?
                // Checking GraphSeeder: $vendors = Location::factory()->...->create(['type' => 'vendor']);
                // So "Vendor" in the graph is a Location with type 'vendor'.
                // But the 'Vendor' model (App\Models\Vendor) might be different.
                // CoreGameStateSeeder creates App\Models\Vendor.
                // The graph links Location IDs.
                // We need to map App\Models\Vendor to App\Models\Location of type 'vendor'.
                // Assuming for now they might not be the same ID.
                // Let's assume the caller passes the correct Location for the vendor.
                throw new \Exception('Vendor location mapping required.');
            }
            $path = $this->logistics->findBestRoute($vendorLocation, $targetLocation);
        }

        if (! $path || $path->isEmpty()) {
            throw new \Exception('No valid route found between vendor and target location.');
        }

        return DB::transaction(function () use ($user, $vendor, $targetLocation, $items, $path, $autoSubmit) {
            $pricedItems = $this->applyPricingToItems($user, $vendor, $items);
            $totalCost = $this->sumItemTotals($pricedItems) + $this->calculateShippingCost($path);

            // Transits
            $totalDays = $path->sum('transit_days');
            // Note: Actual delivery day depends on when order is placed + total days.
            // Assuming order is placed 'now' (today).
            // We need GameState to know 'today', but Order usually stores relative or absolute?
            // Order has 'delivery_day'.
            $gameState = $user->gameState;
            $deliveryDay = $gameState ? $gameState->day + $totalDays : 1 + $totalDays;

            // 3. Create Order
            $order = Order::create([
                'user_id' => $user->id,
                'vendor_id' => $vendor->id,
                'location_id' => $targetLocation->id,
                'status' => $autoSubmit
                    ? \App\States\Order\Pending::class
                    : \App\States\Order\Draft::class,
                'total_cost' => $totalCost,
                'total_transit_days' => $totalDays,
                'delivery_day' => $deliveryDay,
                'delivery_date' => now()->addDays($totalDays),
                'created_day' => $gameState ? $gameState->day : 1,
            ]);

            // 4. Create Items
            foreach ($pricedItems as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'cost_per_unit' => (int) $item['cost_per_unit'],
                ]);
            }

            // 5. Create Shipments
            $this->createShipmentsForOrder($order, $path, $gameState ? $gameState->day : 1);

            return $order;
        });
    }

    /**
     * Calculate the final order total in cents using the same pricing logic as createOrder.
     *
     * @param  array<int, array{product_id: string, quantity: int, cost_per_unit: int}>  $items
     */
    public function calculateOrderTotalCost(User $user, Vendor $vendor, array $items, Collection $path): int
    {
        $pricedItems = $this->applyPricingToItems($user, $vendor, $items);

        return $this->sumItemTotals($pricedItems) + $this->calculateShippingCost($path);
    }

    /**
     * Apply dynamic pricing to each line item.
     *
     * @param  array<int, array{product_id: string, quantity: int, cost_per_unit: int}>  $items
     * @return array<int, array{product_id: string, quantity: int, cost_per_unit: int}>
     */
    protected function applyPricingToItems(User $user, Vendor $vendor, array $items): array
    {
        return collect($items)->map(function (array $item) use ($user, $vendor): array {
            $multiplier = $this->pricingService->getPriceMultiplierFor($user, $item['product_id'], $vendor->id);
            $item['cost_per_unit'] = (int) round(((int) $item['cost_per_unit']) * $multiplier);

            return $item;
        })->all();
    }

    /**
     * Sum line-item totals in integer cents.
     *
     * @param  array<int, array{product_id: string, quantity: int, cost_per_unit: int}>  $items
     */
    protected function sumItemTotals(array $items): int
    {
        return (int) collect($items)->sum(fn (array $item): int => $item['quantity'] * (int) $item['cost_per_unit']);
    }

    protected function calculateShippingCost(Collection $path): int
    {
        return (int) $path->sum(fn ($route) => $this->logistics->calculateCost($route));
    }

    protected function createShipmentsForOrder(Order $order, Collection $path, int $currentDay): void
    {
        $sequence = 0;
        $arrivalAccumulator = $currentDay;

        foreach ($path as $route) {
            $arrivalAccumulator += $route->transit_days;

            Shipment::create([
                'order_id' => $order->id,
                'route_id' => $route->id,
                'source_location_id' => $route->source_id,
                'target_location_id' => $route->target_id,
                'status' => $sequence === 0 ? 'in_transit' : 'pending',
                'sequence_index' => $sequence,
                'arrival_day' => $arrivalAccumulator,
                'arrival_date' => now()->addDays($arrivalAccumulator - $currentDay),
            ]);

            $sequence++;
        }
    }
}
