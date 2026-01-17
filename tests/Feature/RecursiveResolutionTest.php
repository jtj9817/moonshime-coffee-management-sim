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

test('scenario c: "the recursive resolution" stress test', function () {
    // --- 1. SETUP ---
    $user = User::factory()->create();
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);
    
    $warehouse = Location::factory()->create(['type' => 'warehouse', 'name' => 'Source']);
    $store1 = Location::factory()->create(['type' => 'store', 'name' => 'Store 1']);
    $store2 = Location::factory()->create(['type' => 'store', 'name' => 'Store 2']);
    
    $route1 = Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $store1->id,
        'is_active' => true,
        'weather_vulnerability' => true,
    ]);

    $route2 = Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $store2->id,
        'is_active' => true,
        'weather_vulnerability' => true,
    ]);
    
    Inventory::factory()->create(['user_id' => $user->id, 'location_id' => $store1->id, 'quantity' => 0]);
    Inventory::factory()->create(['user_id' => $user->id, 'location_id' => $store2->id, 'quantity' => 0]);

    $service = new SimulationService($gameState);

    // --- 2. TRIGGER ROOT SPIKE ---
    // Create spike for Store 1 route
    $rootSpike1 = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 5,
        'affected_route_id' => $route1->id,
        'starts_at_day' => 1,
        'ends_at_day' => 6,
        'is_active' => true,
    ]);
    event(new SpikeOccurred($rootSpike1));

    // Create spike for Store 2 route
    $rootSpike2 = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 5,
        'affected_route_id' => $route2->id,
        'starts_at_day' => 1,
        'ends_at_day' => 6,
        'is_active' => true,
    ]);
    event(new SpikeOccurred($rootSpike2));

    // Analysis tick
    $service->advanceTime(); // Day 1 -> 2
    
    $alert1 = Alert::where('type', 'isolation')->where('location_id', $store1->id)->first();
    $alert2 = Alert::where('type', 'isolation')->where('location_id', $store2->id)->first();
    
    expect($alert1)->not->toBeNull();
    expect($alert2)->not->toBeNull();
    expect($alert1->is_resolved)->toBeFalse();
    expect($alert2->is_resolved)->toBeFalse();

    // --- 3. VERIFY RECURSIVE RESOLUTION ---
    // A. Manually resolve alert 1 (Simulate player task resolution)
    $alert1->update(['is_resolved' => true]);
    
    // B. End Spike 2 (Simulate root resolution)
    $rootSpike2->update(['ends_at_day' => 2]); // Should end on next advance
    
    $service->advanceTime(); // Day 2 -> 3
    
    // Alert 1 should STAY resolved (recursive integrity)
    expect($alert1->fresh()->is_resolved)->toBeTrue();
    
    // Alert 2 should AUTO-RESOLVE because Spike 2 ended and route 2 is active again
    expect($route2->fresh()->is_active)->toBeTrue();
    expect($alert2->fresh()->is_resolved)->toBeTrue();
    
    // Spike 1 is still active, route 1 is blocked
    expect($rootSpike1->fresh()->is_active)->toBeTrue();
    expect($route1->fresh()->is_active)->toBeFalse();
    
    // Store 1 is still isolated, but alert was marked resolved. 
    // Does our system recreate it? 
    // Current logic in GenerateIsolationAlerts: 
    // Alert::where('location_id', $store->id)->where('type', 'isolation')->where('is_resolved', false)->exists();
    // Since it's resolved, it SHOULD recreate it if reachability is still false.
    // Wait, is that what we want? Usually isolation alerts should persist until resolved by reachability.
    
    // If I manually resolved it, but the root cause is still there, 
    // the system "sees" it as unresolved debt.
    expect(Alert::where('location_id', $store1->id)->where('type', 'isolation')->where('is_resolved', false)->count())->toBe(1);
});