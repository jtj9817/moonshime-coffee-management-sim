<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Models\Alert;
use App\Models\Inventory;
use App\Services\SimulationService;
use App\Events\SpikeOccurred;
use App\Events\SpikeEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('scenario a: "the cascade" stress test', function () {
    // --- 1. SETUP LARGE GRAPH ---
    $user = User::factory()->create();
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);
    
    // Central Warehouse (Supply Source)
    $warehouse = Location::factory()->create(['type' => 'warehouse', 'name' => 'Central Hub']);
    
    // Create 15 Stores
    $stores = Location::factory()->count(15)->create(['type' => 'store']);
    
    // Connect all stores to warehouse via vulnerable routes
    foreach ($stores as $index => $store) {
        Route::factory()->create([
            'source_id' => $warehouse->id,
            'target_id' => $store->id,
            'is_active' => true,
            'weather_vulnerability' => true,
            'transport_mode' => 'Truck ' . ($index + 1),
        ]);
        
        // Give each store low stock to trigger isolation alerts
        Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $store->id,
            'product_id' => \App\Models\Product::factory()->create()->id,
            'quantity' => 5, // Below isolation threshold (10)
        ]);
    }

    $service = new SimulationService($gameState);

    // --- 2. TRIGGER ROOT SPIKE (Blizzard) ---
    // A blizzard that targets no specific route but we'll manually apply it 
    // to simulate a massive event or just create one that affects a central bottleneck.
    // In this case, we'll create a blizzard that affects ALL routes from warehouse.
    
    $routes = Route::where('source_id', $warehouse->id)->get();
    
    foreach ($routes as $route) {
        $spike = SpikeEvent::create([
            'user_id' => $user->id,
            'type' => 'blizzard',
            'magnitude' => 1.0,
            'duration' => 2,
            'affected_route_id' => $route->id,
            'starts_at_day' => 2,
            'ends_at_day' => 4,
            'is_active' => false,
        ]);
        
        // Activate
        $spike->update(['is_active' => true]);
        event(new SpikeOccurred($spike));
    }

    // --- 3. VERIFY CASCADE ---
    // Running analysis tick should generate isolation alerts for all stores
    $service->advanceTime(); // Day 1 -> 2
    
    // We expect 15 isolation alerts (one for each store)
    $isolationAlerts = Alert::where('type', 'isolation')->count();
    expect($isolationAlerts)->toBe(15, "Should have 15 isolation alerts");
    
    // Check reachability via LogisticsService directly for one store
    $logistics = app(\App\Services\LogisticsService::class);
    expect($logistics->checkReachability($stores->first()))->toBeFalse();

    // --- 4. VERIFY RECOVERY ---
    $service->advanceTime(); // Day 2 -> 3
    $service->advanceTime(); // Day 3 -> 4 (Spikes expire)
    
    // All routes should be restored
    foreach ($routes as $route) {
        expect($route->fresh()->is_active)->toBeTrue("Route {$route->id} should be restored");
    }
    
    // Running analysis tick on Day 4 should resolve isolation alerts
    $service->advanceTime(); // Day 3 -> 4
    
    $unresolvedAlerts = Alert::where('type', 'isolation')->where('is_resolved', false)->count();
    expect($unresolvedAlerts)->toBe(0, "All isolation alerts should be resolved");
    
    $resolvedAlerts = Alert::where('type', 'isolation')->where('is_resolved', true)->count();
    expect($resolvedAlerts)->toBe(15, "15 alerts should be marked as resolved");
});
