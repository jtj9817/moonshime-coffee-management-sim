<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\TransferCompleted;
use App\Models\GameState;
use Illuminate\Support\Facades\Auth;

class UpdateMetrics
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof OrderPlaced) {
            $this->handleOrderPlaced($event);
        } elseif ($event instanceof TransferCompleted) {
            $this->handleTransferCompleted($event);
        }
    }

    protected function handleOrderPlaced(OrderPlaced $event): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $gameState = GameState::where('user_id', $user->id)->first();

        if ($gameState) {
            $gameState->increment('xp', 10);
        }

        // Also update vendor reliability?
        $vendor = $event->order->vendor;
        if ($vendor) {
            // Logic for reliability update...
        }
    }

    protected function handleTransferCompleted(TransferCompleted $event): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $gameState = GameState::where('user_id', $user->id)->first();

        if ($gameState) {
            $gameState->increment('xp', 5);
        }
    }
}
