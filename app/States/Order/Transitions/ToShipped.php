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
        if (!$this->order->route_id) {
            throw new RuntimeException('Cannot ship order without a assigned route.');
        }

        $route = $this->order->route;
        
        // Check Capacity (Throughput Limits)
        $totalQuantity = $this->order->items->sum('quantity');
        if ($totalQuantity > $route->capacity) {
            throw new RuntimeException("Order quantity ($totalQuantity) exceeds route capacity ({$route->capacity}).");
        }
        
        // Get current day from game state
        $gameState = app(GameState::class);
        $currentDay = $gameState->day;

        $this->order->delivery_day = $currentDay + $route->transit_days;
        $this->order->status = new Shipped($this->order);
        $this->order->save();

        return $this->order;
    }
}
