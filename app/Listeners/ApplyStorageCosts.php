<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\GameState;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class ApplyStorageCosts
{
    /**
     * Handle the event.
     */
    public function handle(TimeAdvanced $event): void
    {
        $gameState = $event->gameState;
        
        $totalStorageCost = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->where('inventories.user_id', $gameState->user_id)
            ->sum(DB::raw('inventories.quantity * products.storage_cost'));

        if ($totalStorageCost > 0) {
            $gameState->decrement('cash', (int) $totalStorageCost);
        }
    }
}
