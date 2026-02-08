<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\GameState;
use App\Services\StorageFeeCalculator;

/**
 * Apply daily storage costs to user's cash balance.
 * Uses centralized StorageFeeCalculator service (TICKET-005).
 */
class ApplyStorageCosts
{
    public function __construct(
        protected StorageFeeCalculator $storageFeeCalculator
    ) {}

    /**
     * Handle the event.
     */
    public function handle(TimeAdvanced $event): void
    {
        $gameState = $event->gameState;

        $totalStorageCost = $this->storageFeeCalculator->calculate($gameState->user_id);

        if ($totalStorageCost > 0) {
            $gameState->decrement('cash', $totalStorageCost);
        }
    }
}
