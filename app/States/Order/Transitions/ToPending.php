<?php

namespace App\States\Order\Transitions;

use App\Models\GameState;
use App\Models\Order;
use App\States\Order\Pending;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Spatie\ModelStates\Transition;

class ToPending extends Transition
{
    public function __construct(
        protected Order $order,
    ) {}

    public function handle(): Order
    {
        $user = Auth::user();

        if (!$user) {
            throw new RuntimeException('Authenticated user required to place an order.');
        }

        $gameState = GameState::where('user_id', $user->id)->first();

        if (!$gameState) {
            throw new RuntimeException('Game state not found for user.');
        }

        $orderTotal = round((float) $this->order->total_cost, 2);
        if ($gameState->cash < $orderTotal) {
            throw new RuntimeException('Insufficient funds to place order.');
        }

        // Note: Actual cash deduction is handled by the OrderPlaced event listener.
        // This transition just validates and updates the state.
        
        $this->order->status = new Pending($this->order);
        $this->order->save();

        return $this->order;
    }
}
