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
    $store = Location::factory()->create(['type' => 'store', 'name' => 'Isolated Store']);
    
    $route = Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $store->id,
        'is_active' => true,
        'weather_vulnerability' => true,
    ]);
    
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $store->id,
        'quantity' => 0,
    ]);

    $service = new SimulationService($gameState);

    // --- 2. TRIGGER ROOT SPIKE ---
    $rootSpike = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 5,
        'affected_route_id' => $route->id,
        'starts_at_day' => 1,
        'ends_at_day' => 6,
        'is_active' => true,
    ]);
    event(new SpikeOccurred($rootSpike));

    // Generate Isolation Alert (Symptom)
    $service->advanceTime(); // Analysis tick runs here
    
    $symptomAlert = Alert::where('type', 'isolation')
        ->where('location_id', $store->id)
        ->first();
    
    expect($symptomAlert)->not->toBeNull();
    expect($symptomAlert->spike_event_id)->toBe($rootSpike->id);

    // --- 3. VERIFY RECURSIVE RESOLUTION ---
    // A. Manually resolve the alert (Simulate player "reading" it or doing a sub-task)
    // Note: Our system currently resolves based on reachability.
    // If I manually mark it resolved, it should stay resolved UNLESS the action runs again.
    $symptomAlert->update(['is_resolved' => true]);
    
    // Run analysis tick again - should NOT recreate alert because spike is still active 
    // but we might need to check our logic if it recreates it if it's resolved but still unreachable.
    // Let's check GenerateIsolationAlerts.php: it checks for unresolved alerts.
    $service->advanceTime(); 
    
    // Should NOT have a new unresolved alert
    expect(Alert::where('type', 'isolation')->where('is_resolved', false)->count())->toBe(0);
    
    // B. End the Root Spike
    $rootSpike->update(['ends_at_day' => $gameState->fresh()->day]);
    $service->advanceTime(); // This should trigger SpikeEnded
    
    // Verify route is restored
    expect($route->fresh()->is_active)->toBeTrue();
    
    // Verify alert is resolved (should already be from our manual, but let's check auto-resolution too)
    // We'll create another store to test auto-resolution.
});
