<?php

namespace App\Services\Spikes;

use App\Interfaces\SpikeTypeInterface;
use App\Models\Order;
use App\Models\SpikeEvent;
use App\States\Order\Pending;
use App\States\Order\Shipped;
use Carbon\Carbon;

class DelaySpike implements SpikeTypeInterface
{
    /**
     * Apply the delay spike effect.
     * 
     * Delays all pending/shipped orders owned by the spike's user.
     * Stores original delivery dates in spike.meta for rollback.
     */
    public function apply(SpikeEvent $event): void
    {
        if (!$event->user_id) {
            return;
        }

        $delayDays = (int) $event->magnitude;
        $affectedOrders = [];

        // Get orders belonging to the spike's owner that are in transit
        $orders = Order::where('user_id', $event->user_id)
            ->where(function ($query) {
                $query->whereState('status', Pending::class)
                    ->orWhereState('status', Shipped::class);
            })
            ->get();

        foreach ($orders as $order) {
            // If spike targets a specific product, only affect orders containing that product
            if ($event->product_id) {
                $hasProduct = $order->items()->where('product_id', $event->product_id)->exists();
                if (!$hasProduct) {
                    continue;
                }
            }

            // Store original values for rollback
            $affectedOrders[$order->id] = [
                'original_delivery_day' => $order->delivery_day,
                'original_delivery_date' => $order->delivery_date?->toISOString(),
            ];

            // Apply the delay
            $updates = [];
            
            if ($order->delivery_day !== null) {
                $updates['delivery_day'] = $order->delivery_day + $delayDays;
            }
            
            if ($order->delivery_date) {
                $updates['delivery_date'] = Carbon::parse($order->delivery_date)->addDays($delayDays);
            }

            if (!empty($updates)) {
                $order->update($updates);
            }
        }

        // Store affected orders in meta for rollback
        $meta = $event->meta ?? [];
        $meta['affected_orders'] = $affectedOrders;
        $meta['delay_days'] = $delayDays;

        $event->update([
            'is_active' => true,
            'meta' => $meta,
        ]);
    }

    /**
     * Rollback the delay spike effect.
     * 
     * Restores original delivery dates from spike.meta.
     */
    public function rollback(SpikeEvent $event): void
    {
        $meta = $event->meta ?? [];
        $affectedOrders = $meta['affected_orders'] ?? [];

        foreach ($affectedOrders as $orderId => $originals) {
            $order = Order::find($orderId);
            
            if (!$order) {
                continue;
            }

            $updates = [];

            if (isset($originals['original_delivery_day'])) {
                $updates['delivery_day'] = $originals['original_delivery_day'];
            }

            if (isset($originals['original_delivery_date'])) {
                $updates['delivery_date'] = $originals['original_delivery_date'] 
                    ? Carbon::parse($originals['original_delivery_date']) 
                    : null;
            }

            if (!empty($updates)) {
                $order->update($updates);
            }
        }

        $event->update(['is_active' => false]);
    }
}
