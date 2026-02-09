<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class SnapshotInventoryLevels
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TimeAdvanced $event): void
    {
        $userId = $event->gameState->user_id;
        $day = $event->day;

        $inventories = Inventory::where('user_id', $userId)->get();

        if ($inventories->isEmpty()) {
            return;
        }

        $historyRecords = $inventories->map(function ($inventory) use ($day) {
            return [
                'user_id' => $inventory->user_id,
                'location_id' => $inventory->location_id,
                'product_id' => $inventory->product_id,
                'day' => $day,
                'quantity' => $inventory->quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        DB::table('inventory_history')->upsert(
            $historyRecords,
            ['user_id', 'location_id', 'product_id', 'day'],
            ['quantity', 'updated_at']
        );
    }
}
