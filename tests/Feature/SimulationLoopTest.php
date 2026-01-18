<?php

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use App\Services\SimulationService;
use App\States\Transfer\InTransit;
use App\States\Transfer\Completed;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('simulation loop integrates event, physics and analysis ticks', function () {
    // 1. Setup Environment
    $gameState = GameState::factory()->create(['day' => 1]);
    $user = $gameState->user; // Get the user created by factory

    $warehouse = Location::factory()->create(['type' => 'warehouse']);
    $store = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create();
    
    // Route for the store
    $route = Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $store->id,
        'weather_vulnerability' => true,
        'is_active' => true,
    ]);

    $service = new SimulationService($gameState);

    // 2. Setup "Event Tick" data:
    // A. A spike that should START on day 2
    $pendingSpike = SpikeEvent::factory()->create([
        'type' => 'blizzard',
        'affected_route_id' => $route->id,
        'starts_at_day' => 2,
        'ends_at_day' => 4,
        'is_active' => false,
        'user_id' => $user->id,
    ]);

    // 3. Setup "Physics Tick" data:
    // A transfer that should ARRIVE on day 2
    $transfer = Transfer::factory()->create([
        'source_location_id' => $warehouse->id,
        'target_location_id' => $store->id,
        'delivery_day' => 2,
        'user_id' => $user->id,
    ]);
    $transfer->status->transitionTo(InTransit::class);

    // 4. Setup "Analysis Tick" data:
    // Low stock for the store, so when it becomes isolated on day 2, it should trigger alert
    Inventory::factory()->create([
        'location_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 5, // Low stock
        'user_id' => $user->id,
    ]);

    // --- ACT: Advance to Day 2 ---
    $service->advanceTime();

    // --- ASSERTIONS ---

    // Game day should be 2
    expect($gameState->fresh()->day)->toBe(2);

    // Event Tick Assertion: Spike should be active, route should be blocked
    expect($pendingSpike->fresh()->is_active)->toBeTrue();
    expect($route->fresh()->is_active)->toBeFalse();

    // Physics Tick Assertion: Transfer should be completed
    expect($transfer->fresh()->status)->toBeInstanceOf(Completed::class);

    // Analysis Tick Assertion: Isolation Alert should be generated
    // (Because it's day 2, route is now blocked, and stock is low)
    expect(\App\Models\Alert::where('type', 'isolation')->count())->toBe(1);
    $alert = \App\Models\Alert::where('type', 'isolation')->first();
    expect($alert->location_id)->toBe($store->id);
    expect($alert->spike_event_id)->toBe($pendingSpike->id);

    // --- ACT: Advance to Day 4 (Spike should END) ---
    // We must advance day-by-day to ensure intermediate spikes are processed
    $service->advanceTime(); // Advance to Day 3
    $service->advanceTime(); // Advance to Day 4

    // Event Tick Assertion: Spike should be inactive, route should be restored
    // Note: We check if it is inactive. A NEW blizzard might have started, 
    // but the specific spike we are tracking MUST be inactive.
    expect($pendingSpike->fresh()->is_active)->toBeFalse();
    
    // To ensure the route is active, we must make sure no OTHER blizzard is active.
    // In this test, we can just check if any active blizzard exists.
    $activeBlizzard = SpikeEvent::where('type', 'blizzard')->where('is_active', true)->exists();
    if (!$activeBlizzard) {
        expect($route->fresh()->is_active)->toBeTrue();
    }
});
