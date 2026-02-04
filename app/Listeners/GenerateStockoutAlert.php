<?php

namespace App\Listeners;

use App\Events\StockoutOccurred;
use App\Models\Alert;

class GenerateStockoutAlert
{
    public function handle(StockoutOccurred $event): void
    {
        $demandEvent = $event->demandEvent;

        if ($demandEvent->lost_quantity <= 0) {
            return;
        }

        $exists = Alert::where('user_id', $demandEvent->user_id)
            ->where('type', 'stockout')
            ->where('location_id', $demandEvent->location_id)
            ->where('product_id', $demandEvent->product_id)
            ->where('created_day', $demandEvent->day)
            ->exists();

        if ($exists) {
            return;
        }

        $demandEvent->loadMissing(['location', 'product']);

        $requested = max(0, (int) $demandEvent->requested_quantity);
        $lost = max(0, (int) $demandEvent->lost_quantity);
        $fulfilled = max(0, (int) $demandEvent->fulfilled_quantity);
        $lossRatio = $requested > 0 ? ($lost / $requested) : 0;

        $severity = ($fulfilled === 0 || $lossRatio >= 0.5) ? 'critical' : 'warning';
        $locationName = $demandEvent->location?->name ?? 'Unknown location';
        $productName = $demandEvent->product?->name ?? 'Unknown product';

        Alert::create([
            'user_id' => $demandEvent->user_id,
            'type' => 'stockout',
            'severity' => $severity,
            'location_id' => $demandEvent->location_id,
            'product_id' => $demandEvent->product_id,
            'created_day' => $demandEvent->day,
            'message' => "Stockout at {$locationName} for {$productName}. Lost {$lost} units.",
            'data' => [
                'requested' => $requested,
                'fulfilled' => $fulfilled,
                'lost' => $lost,
                'revenue' => $demandEvent->revenue,
                'lost_revenue' => $demandEvent->lost_revenue,
            ],
        ]);
    }
}
