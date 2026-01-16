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
            'type' => 'order_placed',
            'message' => "Order #{$event->order->id} placed for {$event->order->total_cost} cash.",
            'data' => [
                'order_id' => $event->order->id,
                'total_cost' => $event->order->total_cost,
            ],
        ]);
    }

    protected function handleTransferCompleted(TransferCompleted $event): void
    {
        Alert::create([
            'type' => 'transfer_completed',
            'message' => "Transfer #{$event->transfer->id} completed successfully.",
            'data' => [
                'transfer_id' => $event->transfer->id,
            ],
        ]);
    }

    protected function handleSpikeOccurred(SpikeOccurred $event): void
    {
        $type = $event->spike->type ?? 'unknown';
        Alert::create([
            'type' => 'spike_occurred',
            'message' => "A chaos event occurred: {$type}!",
            'data' => (array) $event->spike,
        ]);
    }
}
