<?php

namespace App\Actions;

use App\Models\Alert;
use App\Models\Location;
use App\Models\SpikeEvent;
use App\Services\LogisticsService;

class GenerateIsolationAlerts
{
    public function __construct(
        protected LogisticsService $logistics
    ) {}

    public function handle(): void
    {
        // Get all stores
        $stores = Location::where('type', 'store')->get();

        foreach ($stores as $store) {
            $isReachable = $this->logistics->checkReachability($store);

            if (!$isReachable) {
                // ... (existing logic for creating alerts)
                // Check if stock is low for at least one product
                $hasLowStock = $store->inventories()->where('quantity', '<', 10)->exists();

                if (!$hasLowStock) {
                    continue;
                }

                // Check if we already have an active isolation alert for this store
                $existingAlert = Alert::where('location_id', $store->id)
                    ->where('type', 'isolation')
                    ->where('is_resolved', false)
                    ->exists();

                if ($existingAlert) {
                    continue;
                }

                // Find a likely cause (Active Spike)
                $cause = SpikeEvent::where('is_active', true)
                    ->whereIn('type', ['blizzard', 'breakdown'])
                    ->latest()
                    ->first();

                Alert::create([
                    'type' => 'isolation',
                    'severity' => 'critical',
                    'location_id' => $store->id,
                    'spike_event_id' => $cause?->id,
                    'message' => "Store '{$store->name}' is isolated from supply chain and low on stock!",
                    'data' => [
                        'reason' => $cause ? "Likely due to {$cause->type}" : "Unknown network failure"
                    ]
                ]);
            } else {
                // Store is reachable - RESOLVE existing isolation alerts
                Alert::where('location_id', $store->id)
                    ->where('type', 'isolation')
                    ->where('is_resolved', false)
                    ->update(['is_resolved' => true]);
            }
        }
    }
}
