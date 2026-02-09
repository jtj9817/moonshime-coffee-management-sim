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

    public function handle(int $userId): void
    {
        // Get all stores (global physical locations)
        $stores = Location::where('type', 'store')->get();

        foreach ($stores as $store) {
            $isReachable = $this->logistics->checkReachability($store);

            if (! $isReachable) {
                // Check if the SPECIFIC USER has low stock at this store
                $hasLowStock = $store->inventories()
                    ->where('user_id', $userId)
                    ->where('quantity', '<', 10)
                    ->exists();

                if (! $hasLowStock) {
                    continue;
                }

                // Check if we already have an active isolation alert for this store and user
                $existingAlert = Alert::where('user_id', $userId)
                    ->where('location_id', $store->id)
                    ->where('type', 'isolation')
                    ->where('is_resolved', false)
                    ->exists();

                if ($existingAlert) {
                    continue;
                }

                // Find a likely cause (Active Spike for this user)
                $cause = SpikeEvent::where('user_id', $userId)
                    ->where('is_active', true)
                    ->whereIn('type', ['blizzard', 'breakdown'])
                    ->latest()
                    ->first();

                Alert::create([
                    'user_id' => $userId,
                    'type' => 'isolation',
                    'severity' => 'critical',
                    'location_id' => $store->id,
                    'spike_event_id' => $cause?->id,
                    'message' => "Store '{$store->name}' is isolated from supply chain and low on stock!",
                    'data' => [
                        'reason' => $cause ? "Likely due to {$cause->type}" : 'Unknown network failure',
                    ],
                ]);
            } else {
                // Store is reachable - RESOLVE existing isolation alerts for this user
                Alert::where('user_id', $userId)
                    ->where('location_id', $store->id)
                    ->where('type', 'isolation')
                    ->where('is_resolved', false)
                    ->update(['is_resolved' => true]);
            }
        }
    }
}
