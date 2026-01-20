<?php

namespace App\States\Order\Transitions;

use App\Models\Order;
use App\Models\GameState;
use App\States\Order\Shipped;
use Spatie\ModelStates\Transition;
use RuntimeException;

class ToShipped extends Transition
{
    public function __construct(
        protected Order $order,
    ) {}

    public function handle(): Order
    {
        $shipments = $this->order->shipments()->with('route')->get();
        if ($shipments->isEmpty()) {
            throw new RuntimeException('Cannot ship order without assigned shipments.');
        }

        $routes = $shipments->pluck('route')->filter();
        if ($routes->count() !== $shipments->count()) {
            throw new RuntimeException('Cannot ship order with missing route data.');
        }

        // Check Capacity (Throughput Limits)
        $totalQuantity = $this->order->items()->sum('quantity');
        $minCapacity = $routes->min('capacity');
        if ($minCapacity !== null && $totalQuantity > $minCapacity) {
            throw new RuntimeException("Order quantity ($totalQuantity) exceeds route capacity ({$minCapacity}).");
        }

        $totalTransitDays = $this->order->total_transit_days;
        if ($totalTransitDays === null) {
            $totalTransitDays = (int) $routes->sum('transit_days');
            $this->order->total_transit_days = $totalTransitDays;
        }

        // Get current day from game state
        $gameState = app(GameState::class);
        $currentDay = $gameState->day;

        $this->order->delivery_day = $currentDay + (int) $totalTransitDays;
        $this->order->status = new Shipped($this->order);
        $this->order->save();

        return $this->order;
    }
}
