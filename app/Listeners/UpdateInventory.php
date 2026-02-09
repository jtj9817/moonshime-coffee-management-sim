<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
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

        if ($event instanceof OrderDelivered) {
            $this->handleOrderDelivered($event);
        }
    }

    protected function handleTransferCompleted(TransferCompleted $event): void
    {
        $transfer = $event->transfer;

        // Increment target inventory
        $inventory = Inventory::firstOrCreate(
            [
                'user_id' => $transfer->user_id,
                'location_id' => $transfer->target_location_id,
                'product_id' => $transfer->product_id,
            ],
            [
                'quantity' => 0,
            ]
        );

        $inventory->increment('quantity', $transfer->quantity);
    }

    protected function handleOrderDelivered(OrderDelivered $event): void
    {
        $order = $event->order;
        $locationId = $order->location_id;

        if (! $locationId) {
            // Fallback: If no location_id is set on the order, we can't update inventory reliably.
            // In a production app, we might log a warning or use a default.
            return;
        }

        foreach ($order->items as $item) {
            $inventory = Inventory::firstOrCreate(
                [
                    'user_id' => $order->user_id,
                    'location_id' => $locationId,
                    'product_id' => $item->product_id,
                ],
                [
                    'quantity' => 0,
                ]
            );

            $inventory->increment('quantity', $item->quantity);
        }
    }
}
