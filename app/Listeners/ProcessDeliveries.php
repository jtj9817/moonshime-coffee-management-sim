<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\Order;
use App\Models\Transfer;
use App\States\Order\Delivered;
use App\States\Order\Shipped;
use App\States\Transfer\Completed;
use App\States\Transfer\InTransit;

class ProcessDeliveries
{
    /**
     * Handle the event.
     */
    public function onTimeAdvanced(TimeAdvanced $event): void
    {
        // Process Orders
        Order::whereState('status', Shipped::class)
            ->where('delivery_day', '<=', $event->day)
            ->get()
            ->each(fn ($order) => $order->status->transitionTo(Delivered::class));

        // Process Transfers
        Transfer::whereState('status', InTransit::class)
            ->where('delivery_day', '<=', $event->day)
            ->get()
            ->each(fn ($transfer) => $transfer->status->transitionTo(Completed::class));
    }
}
