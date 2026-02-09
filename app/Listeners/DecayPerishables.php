<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\Inventory;

class DecayPerishables
{
    /**
     * Handle the event.
     */
    public function onTimeAdvanced(TimeAdvanced $event): void
    {
        Inventory::where('user_id', $event->gameState->user_id)
            ->whereHas('product', function ($query) {
                $query->where('is_perishable', true);
            })
            ->where('quantity', '>', 0)
            ->get()
            ->each(function ($inventory) {
                $decayAmount = max(1, (int) ($inventory->quantity * 0.05));
                $inventory->decrement('quantity', $decayAmount);
            });
    }
}
