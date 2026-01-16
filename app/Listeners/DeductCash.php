<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Models\GameState;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class DeductCash
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof OrderPlaced) {
            $this->handleOrderPlaced($event);
        }
    }

    protected function handleOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order;
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $gameState = GameState::where('user_id', $user->id)->first();

        if (!$gameState) {
            throw new RuntimeException('Game state not found for user');
        }

        if ($gameState->cash < $order->total_cost) {
            throw new RuntimeException('Insufficient funds');
        }

        $gameState->decrement('cash', $order->total_cost);
    }
}
