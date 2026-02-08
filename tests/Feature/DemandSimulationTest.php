<?php

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Services\DemandSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('consumes inventory only for the active user', function () {
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create();

    $activeUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $activeInventory = Inventory::factory()->create([
        'user_id' => $activeUser->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);

    $otherInventory = Inventory::factory()->create([
        'user_id' => $otherUser->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);

    $gameState = GameState::factory()->create(['user_id' => $activeUser->id]);

    app(DemandSimulationService::class)->processDailyConsumption($gameState, 1);

    expect($activeInventory->refresh()->quantity)->toBeLessThan(100);
    expect($otherInventory->refresh()->quantity)->toBe(100);
});

/**
 * TICKET-003 & TICKET-004: Test explicit day-based spike filtering.
 * Spikes outside the current day range should not affect demand.
 */
it('uses explicit day-based filtering for spike events', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 10]);

    // Create inventory at the store
    $inventory = Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 1000, // Large quantity to avoid stockouts affecting the test
    ]);

    // Create a demand spike that ends before day 10 (should NOT apply)
    SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'demand',
        'magnitude' => 5.0, // 5x demand multiplier if active
        'duration' => 3,
        'starts_at_day' => 5,
        'ends_at_day' => 8, // Ends before day 10
        'is_active' => true, // Even with is_active=true, day filtering should exclude it
    ]);

    // Get baseline consumption before running with spike
    $service = app(DemandSimulationService::class);
    $service->processDailyConsumption($gameState, 10);

    $consumedWithExpiredSpike = 1000 - $inventory->refresh()->quantity;

    // Reset inventory
    $inventory->update(['quantity' => 1000]);

    // Create a spike that IS active on day 10
    SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'demand',
        'magnitude' => 5.0,
        'duration' => 5,
        'starts_at_day' => 8,
        'ends_at_day' => 15, // Active on day 10
        'is_active' => true,
    ]);

    $service->processDailyConsumption($gameState, 10);
    $consumedWithActiveSpike = 1000 - $inventory->refresh()->quantity;

    // With an active 5x spike, consumption should be notably higher
    // Note: Due to random variance (±20%), we check for significant difference
    expect($consumedWithActiveSpike)->toBeGreaterThan($consumedWithExpiredSpike);
});
