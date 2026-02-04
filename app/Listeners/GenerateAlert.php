<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\SpikeOccurred;
use App\Events\TransferCompleted;
use App\Models\Alert;

class GenerateAlert
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
        } elseif ($event instanceof SpikeOccurred) {
            $this->handleSpikeOccurred($event);
        }
    }

    protected function handleOrderPlaced(OrderPlaced $event): void
    {
        Alert::create([
            'user_id' => $event->order->user_id,
            'type' => 'order_placed',
            'message' => "Order #{$event->order->id} placed for {$event->order->total_cost} cash.",
            'location_id' => $event->order->location_id,
            'created_day' => $event->order->created_day,
            'data' => [
                'order_id' => $event->order->id,
                'total_cost' => $event->order->total_cost,
            ],
        ]);
    }

    protected function handleTransferCompleted(TransferCompleted $event): void
    {
        Alert::create([
            'user_id' => $event->transfer->user_id,
            'type' => 'transfer_completed',
            'message' => "Transfer #{$event->transfer->id} completed successfully.",
            'location_id' => $event->transfer->target_location_id,
            'product_id' => $event->transfer->product_id,
            'created_day' => $event->transfer->delivery_day,
            'data' => [
                'transfer_id' => $event->transfer->id,
            ],
        ]);
    }

    protected function handleSpikeOccurred(SpikeOccurred $event): void
    {
        $type = $event->spike->type ?? 'unknown';
        Alert::create([
            'user_id' => $event->spike->user_id,
            'type' => 'spike_occurred',
            'message' => "A chaos event occurred: {$type}!",
            'location_id' => $event->spike->location_id,
            'product_id' => $event->spike->product_id,
            'spike_event_id' => $event->spike->id ?? null,
            'created_day' => $event->spike->starts_at_day ?? null,
            'data' => (array) $event->spike,
        ]);
    }
}
