<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

class DemandForecastService
{
    /**
     * Baseline daily consumption per product (units).
     */
    protected int $baselineConsumption = 5;

    /**
     * Generate a 7-day demand forecast for a specific SKU at a location.
     *
     * @return array<int, array{day_offset: int, predicted_demand: int, predicted_stock: int, risk_level: string, incoming_deliveries: int}>
     */
    public function getForecast(int $userId, string $locationId, string $productId, int $currentDay): array
    {
        $inventory = Inventory::where('user_id', $userId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->first();

        $currentStock = $inventory ? (int) $inventory->quantity : 0;

        // Pre-fetch incoming deliveries for the forecast window
        $deliveries = $this->getIncomingDeliveries($userId, $locationId, $productId, $currentDay);

        // Pre-fetch active demand spikes
        $demandMultipliers = $this->getDemandMultipliers($userId, $locationId, $productId, $currentDay);

        $forecast = [];
        $runningStock = $currentStock;

        for ($offset = 1; $offset <= 7; $offset++) {
            $forecastDay = $currentDay + $offset;

            // Calculate demand with spike multiplier
            $multiplier = $demandMultipliers[$forecastDay] ?? 1.0;
            $predictedDemand = (int) ($this->baselineConsumption * $multiplier);

            // Add incoming deliveries
            $incoming = $deliveries[$forecastDay] ?? 0;
            $runningStock = $runningStock + $incoming - $predictedDemand;
            $runningStock = max(0, $runningStock);

            $riskLevel = $this->calculateRiskLevel($runningStock, $predictedDemand);

            $forecast[] = [
                'day_offset' => $offset,
                'predicted_demand' => $predictedDemand,
                'predicted_stock' => $runningStock,
                'risk_level' => $riskLevel,
                'incoming_deliveries' => $incoming,
            ];
        }

        return $forecast;
    }

    /**
     * Get incoming deliveries (from orders and transfers) grouped by delivery day.
     *
     * @return array<int, int> day => total quantity
     */
    protected function getIncomingDeliveries(int $userId, string $locationId, string $productId, int $currentDay): array
    {
        $endDay = $currentDay + 7;
        $deliveries = [];

        // Incoming from orders (pending/shipped, not yet delivered)
        $orderDeliveries = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $userId)
            ->where('orders.location_id', $locationId)
            ->where('order_items.product_id', $productId)
            ->whereNotIn('orders.status', ['delivered', 'cancelled'])
            ->where('orders.delivery_day', '>', $currentDay)
            ->where('orders.delivery_day', '<=', $endDay)
            ->select('orders.delivery_day', DB::raw('SUM(order_items.quantity) as total_qty'))
            ->groupBy('orders.delivery_day')
            ->get();

        foreach ($orderDeliveries as $row) {
            $day = (int) $row->delivery_day;
            $deliveries[$day] = ($deliveries[$day] ?? 0) + (int) $row->total_qty;
        }

        // Incoming from transfers (in-transit)
        $transferDeliveries = Transfer::where('user_id', $userId)
            ->where('target_location_id', $locationId)
            ->where('product_id', $productId)
            ->whereState('status', \App\States\Transfer\InTransit::class)
            ->where('delivery_day', '>', $currentDay)
            ->where('delivery_day', '<=', $endDay)
            ->select('delivery_day', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('delivery_day')
            ->get();

        foreach ($transferDeliveries as $row) {
            $day = (int) $row->delivery_day;
            $deliveries[$day] = ($deliveries[$day] ?? 0) + (int) $row->total_qty;
        }

        return $deliveries;
    }

    /**
     * Get demand multipliers from active spikes for each forecast day.
     *
     * @return array<int, float> day => multiplier
     */
    protected function getDemandMultipliers(int $userId, string $locationId, string $productId, int $currentDay): array
    {
        $endDay = $currentDay + 7;
        $multipliers = [];

        $spikes = SpikeEvent::forUser($userId)
            ->where('type', 'demand')
            ->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)->orWhereNull('location_id');
            })
            ->where(function ($q) use ($productId) {
                $q->where('product_id', $productId)->orWhereNull('product_id');
            })
            ->where('starts_at_day', '<=', $endDay)
            ->where('ends_at_day', '>', $currentDay)
            ->get();

        for ($day = $currentDay + 1; $day <= $endDay; $day++) {
            $maxMagnitude = 1.0;
            foreach ($spikes as $spike) {
                if ($spike->starts_at_day <= $day && $spike->ends_at_day > $day) {
                    $maxMagnitude = max($maxMagnitude, (float) $spike->magnitude);
                }
            }
            $multipliers[$day] = $maxMagnitude;
        }

        return $multipliers;
    }

    /**
     * Calculate risk level based on predicted stock and demand.
     */
    protected function calculateRiskLevel(int $predictedStock, int $predictedDemand): string
    {
        if ($predictedStock <= 0) {
            return 'stockout';
        }

        // If stock can cover less than 2 days of demand, it's medium risk
        if ($predictedStock < $predictedDemand * 2) {
            return 'medium';
        }

        return 'low';
    }
}
