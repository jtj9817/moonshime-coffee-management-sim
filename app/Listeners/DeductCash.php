<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\OrderCancelled;
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

        if ($event instanceof OrderCancelled) {
            $this->handleOrderCancelled($event);
        }
    }

    protected function handleOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order;
        $userId = $order->user_id;

        if (!$userId) {
            return;
        }

        $gameState = GameState::where('user_id', $userId)->first();

        if (!$gameState) {
            throw new RuntimeException('Game state not found for user');
        }

        $orderTotal = (int) $order->total_cost;
        if ($gameState->cash < $orderTotal) {
            throw new RuntimeException('Insufficient funds');
        }

        $gameState->decrement('cash', $orderTotal);
    }

    protected function handleOrderCancelled(OrderCancelled $event): void
    {
        $order = $event->order;
        $userId = $order->user_id;

        if (!$userId) {
            return;
        }

        $gameState = GameState::where('user_id', $userId)->first();

        if ($gameState) {
            $gameState->increment('cash', (int) $order->total_cost);
        }
    }
}
