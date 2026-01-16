<?php

namespace App\Observers;

use App\Events\LowStockDetected;
use App\Models\Inventory;

class InventoryObserver
{
    /**
     * Handle the Inventory "created" event.
     */
    public function created(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "updating" event.
     */
    public function updating(Inventory $inventory): void
    {
        $threshold = 10;

        if ($inventory->quantity <= $threshold && $inventory->getOriginal('quantity') > $threshold) {
            LowStockDetected::dispatch($inventory);
        }
    }

    /**
     * Handle the Inventory "updated" event.
     */
    public function updated(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "deleted" event.
     */
    public function deleted(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "restored" event.
     */
    public function restored(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "force deleted" event.
     */
    public function forceDeleted(Inventory $inventory): void
    {
        //
    }
}
