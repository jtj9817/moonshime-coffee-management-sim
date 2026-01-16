<?php

namespace App\Listeners;

use App\Events\TransferCompleted;
use App\Models\Inventory;

class UpdateInventory
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof TransferCompleted) {
            $this->handleTransferCompleted($event);
        }
    }

    protected function handleTransferCompleted(TransferCompleted $event): void
    {
        $transfer = $event->transfer;

        // Increment target inventory
        $inventory = Inventory::firstOrCreate(
            [
                'location_id' => $transfer->target_location_id,
                'product_id' => $transfer->product_id,
            ],
            [
                'quantity' => 0,
            ]
        );

        $inventory->increment('quantity', $transfer->quantity);
    }
}