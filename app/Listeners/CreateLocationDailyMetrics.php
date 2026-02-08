<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\DemandEvent;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationDailyMetric;
use App\Models\LostSale;
use Illuminate\Support\Facades\DB;

class CreateLocationDailyMetrics
{
    /**
     * Calculate and persist P&L metrics for each store location on day advancement.
     */
    public function handle(TimeAdvanced $event): void
    {
        $gameState = $event->gameState;
        $userId = $gameState->user_id;
        $day = $event->day;

        // Get all store locations with user's inventory
        $storeLocations = Location::where('type', 'store')
            ->whereHas('inventories', fn ($q) => $q->where('user_id', $userId))
            ->get();

        foreach ($storeLocations as $location) {
            $this->createMetricForLocation($userId, $location, $day);
        }
    }

    /**
     * Create a metric record for a single location/day.
     */
    protected function createMetricForLocation(int $userId, Location $location, int $day): void
    {
        // Revenue & units sold from DemandEvents
        $demandSummary = DemandEvent::where('user_id', $userId)
            ->where('location_id', $location->id)
            ->where('day', $day)
            ->selectRaw('COALESCE(SUM(revenue), 0) as total_revenue, COALESCE(SUM(fulfilled_quantity), 0) as total_units_sold')
            ->first();

        $revenue = (int) ($demandSummary->total_revenue ?? 0);
        $unitsSold = (int) ($demandSummary->total_units_sold ?? 0);

        // COGS: approximate from order item costs for products sold at this location
        $cogs = $this->calculateCogs($userId, $location->id, $day);

        // OpEx: sum(inventory.quantity * product.storage_cost) for this location
        $opex = (int) DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->where('inventories.user_id', $userId)
            ->where('inventories.location_id', $location->id)
            ->sum(DB::raw('inventories.quantity * products.storage_cost'));

        // Stockout count from lost sales
        $stockouts = LostSale::where('user_id', $userId)
            ->where('location_id', $location->id)
            ->where('day', $day)
            ->count();

        // Satisfaction: 100% base, reduced by stockout ratio
        $totalRequested = (int) DemandEvent::where('user_id', $userId)
            ->where('location_id', $location->id)
            ->where('day', $day)
            ->sum('requested_quantity');
        $satisfaction = $totalRequested > 0
            ? round(($unitsSold / $totalRequested) * 100, 2)
            : 100.00;

        $netProfit = $revenue - $cogs - $opex;

        LocationDailyMetric::create([
            'user_id' => $userId,
            'location_id' => $location->id,
            'day' => $day,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'opex' => $opex,
            'net_profit' => $netProfit,
            'units_sold' => $unitsSold,
            'stockouts' => $stockouts,
            'satisfaction' => $satisfaction,
        ]);
    }

    /**
     * Calculate COGS using weighted average purchase cost from order items.
     *
     * TICKET-001: Uses weighted average (SUM(cost * qty) / SUM(qty)) instead of simple average.
     * TICKET-002: Batches all product cost queries into a single aggregated query.
     */
    protected function calculateCogs(int $userId, string $locationId, int $day): int
    {
        // Get units sold per product from demand events
        $productsSold = DemandEvent::where('user_id', $userId)
            ->where('location_id', $locationId)
            ->where('day', $day)
            ->where('fulfilled_quantity', '>', 0)
            ->select('product_id', DB::raw('SUM(fulfilled_quantity) as qty_sold'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        if ($productsSold->isEmpty()) {
            return 0;
        }

        // Batch fetch weighted average costs for all products in ONE query (TICKET-002)
        // Uses weighted average: SUM(cost * quantity) / SUM(quantity) (TICKET-001)
        $productCosts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $userId)
            ->whereIn('order_items.product_id', $productsSold->keys())
            ->groupBy('order_items.product_id')
            ->select(
                'order_items.product_id',
                DB::raw('SUM(order_items.cost_per_unit * order_items.quantity) as total_cost'),
                DB::raw('SUM(order_items.quantity) as total_quantity')
            )
            ->get()
            ->keyBy('product_id');

        // Calculate COGS using pre-fetched weighted averages
        $totalCogs = 0;
        foreach ($productsSold as $productId => $ps) {
            $costData = $productCosts->get($productId);
            if ($costData && $costData->total_quantity > 0) {
                $weightedAvgCost = $costData->total_cost / $costData->total_quantity;
                $totalCogs += (int) ($ps->qty_sold * $weightedAvgCost);
            }
        }

        return $totalCogs;
    }
}
