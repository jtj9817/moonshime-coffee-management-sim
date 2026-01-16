<?php

namespace App\Services\Spikes;

use App\Interfaces\SpikeTypeInterface;
use App\Models\SpikeEvent;
use App\Models\Order;
use Carbon\Carbon;

class DelaySpike implements SpikeTypeInterface
{
    public function apply(SpikeEvent $event): void
    {
        $orders = Order::whereIn('status', ['pending', 'shipped'])->get();
        
        foreach ($orders as $order) {
            if ($event->product_id) {
                $hasProduct = $order->items()->where('product_id', $event->product_id)->exists();
                if (!$hasProduct) continue;
            }
            
            if ($order->delivery_date) {
                $order->update([
                    'delivery_date' => Carbon::parse($order->delivery_date)->addDays((int)$event->magnitude)
                ]);
            }
        }
        
        $event->update(['is_active' => true]);
    }

    public function rollback(SpikeEvent $event): void
    {
        $event->update(['is_active' => false]);
    }
}
