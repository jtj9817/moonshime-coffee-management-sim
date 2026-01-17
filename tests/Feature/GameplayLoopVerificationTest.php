<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\Vendor;
use App\Models\User;
use App\Services\SimulationService;
use App\States\Order\Pending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('full 5-day gameplay loop verification', function () {
    // --- SETUP ---
    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
        'cash' => 1000000
    ]);
    Auth::login($user);

    $vendor = Vendor::factory()->create();
    $vendorLocation = Location::factory()->create(['type' => 'vendor']);
    $warehouse = Location::factory()->create(['type' => 'warehouse']);
    $product = Product::factory()->create();
    
    $route = Route::factory()->create([
        'source_id' => $vendorLocation->id,
        'target_id' => $warehouse->id,
        'is_active' => true,
        'weather_vulnerability' => true,
    ]);

    $service = new SimulationService($gameState);

    // --- DAY 1: Stability & Decision ---
    // Verify no active spikes
    expect(SpikeEvent::where('starts_at_day', '<=', 1)->count())->toBe(0);

    // Decision: Place an Order
    $order = Order::factory()->create([
        'vendor_id' => $vendor->id,
        'total_cost' => 1000,
    ]);
    $order->status->transitionTo(Pending::class);

    // Create a planned spike for Day 2
    $spike = SpikeEvent::create([
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 3, // Starts 2, Ends 5 (2, 3, 4 active)
        'affected_route_id' => $route->id,
        'starts_at_day' => 2,
        'ends_at_day' => 5,
        'is_active' => false,
    ]);

    // --- DAY 2: Activation ---
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(2);
    expect($spike->fresh()->is_active)->toBeTrue('Spike should activate on Day 2');
    expect($route->fresh()->is_active)->toBeFalse('Route should be blocked by blizzard');
    
    // Verify decision persisted
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);

    // --- DAY 3: Persistence & Progression ---
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(3);
    expect($spike->fresh()->is_active)->toBeTrue('Spike should remain active on Day 3');
    expect($route->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);

    // --- DAY 4: Persistence ---
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(4);
    expect($spike->fresh()->is_active)->toBeTrue('Spike should remain active on Day 4');
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);

    // --- DAY 5: Expiration & Cleanup ---
    // On Day 5, processEventTick sees ends_at_day (5) <= current day (5), so it ends it.
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(5);
    expect($spike->fresh()->is_active)->toBeFalse('Spike should expire on Day 5');
    
    // Route should be restored (RollbackSpikeEffect listener)
    expect($route->fresh()->is_active)->toBeTrue('Route should be restored after blizzard ends');
    
    // Verify decision still persisted through the whole cycle
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
});

test('day 1 is stable and deterministic with no random events', function () {
    $gameState = GameState::factory()->create(['day' => 1]);
    $service = new SimulationService($gameState);
    
    expect(SpikeEvent::where('starts_at_day', '<=', 1)->count())->toBe(0);
    
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(2);
    
    // New spikes might have been generated for Day 2+, but none should be active for Day 1
    $activeOnDay1 = SpikeEvent::where('starts_at_day', '<=', 1)
        ->where('ends_at_day', '>=', 1)
        ->count();
    expect($activeOnDay1)->toBe(0);
});
