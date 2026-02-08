<?php

use App\Models\DemandEvent;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\DemandSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('increases demand when sell_price is lower than standard price', function () {
    $user = User::factory()->create();
    // sell_price lower than product unit_price => more demand
    $location = Location::factory()->create(['type' => 'store', 'sell_price' => 300]);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 200, // plenty of stock
    ]);

    // Run simulation multiple times to average out variance
    $totalConsumed = 0;
    $runs = 20;
    for ($i = 0; $i < $runs; $i++) {
        // Reset inventory before each run
        $inv = \App\Models\Inventory::where('user_id', $user->id)->first();
        $inv->update(['quantity' => 200]);
        DemandEvent::where('user_id', $user->id)->delete();

        app(DemandSimulationService::class)->processDailyConsumption($gameState, 1);

        $consumed = DemandEvent::where('user_id', $user->id)->sum('fulfilled_quantity');
        $totalConsumed += $consumed;
    }

    $avgConsumed = $totalConsumed / $runs;

    // With sell_price=300 and standard=500, elasticity should increase demand
    // (500/300)^0.5 ≈ 1.29, so avg demand should be > baseline 5
    expect($avgConsumed)->toBeGreaterThan(5);
});

it('decreases demand when sell_price is higher than standard price', function () {
    $user = User::factory()->create();
    // sell_price higher than product unit_price => less demand
    $location = Location::factory()->create(['type' => 'store', 'sell_price' => 1000]);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 200,
    ]);

    $totalConsumed = 0;
    $runs = 20;
    for ($i = 0; $i < $runs; $i++) {
        $inv = \App\Models\Inventory::where('user_id', $user->id)->first();
        $inv->update(['quantity' => 200]);
        DemandEvent::where('user_id', $user->id)->delete();

        app(DemandSimulationService::class)->processDailyConsumption($gameState, 1);

        $consumed = DemandEvent::where('user_id', $user->id)->sum('fulfilled_quantity');
        $totalConsumed += $consumed;
    }

    $avgConsumed = $totalConsumed / $runs;

    // With sell_price=1000 and standard=500, elasticity should decrease demand
    // (500/1000)^0.5 ≈ 0.71, so avg demand should be < baseline 5
    expect($avgConsumed)->toBeLessThan(5);
});

it('uses standard price when sell_price is null', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store', 'sell_price' => null]);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 200,
    ]);

    $totalConsumed = 0;
    $runs = 20;
    for ($i = 0; $i < $runs; $i++) {
        $inv = \App\Models\Inventory::where('user_id', $user->id)->first();
        $inv->update(['quantity' => 200]);
        DemandEvent::where('user_id', $user->id)->delete();

        app(DemandSimulationService::class)->processDailyConsumption($gameState, 1);

        $consumed = DemandEvent::where('user_id', $user->id)->sum('fulfilled_quantity');
        $totalConsumed += $consumed;
    }

    $avgConsumed = $totalConsumed / $runs;

    // When sell_price is null, elasticity multiplier = 1.0, so demand ≈ 5 (baseline)
    // With ±20% variance, average over 20 runs should be between 3 and 7
    expect($avgConsumed)->toBeGreaterThanOrEqual(3);
    expect($avgConsumed)->toBeLessThanOrEqual(7);
});

it('uses sell_price for revenue calculation when set', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store', 'sell_price' => 800]);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 200,
    ]);

    app(DemandSimulationService::class)->processDailyConsumption($gameState, 1);

    $demandEvent = DemandEvent::where('user_id', $user->id)->first();
    // Revenue should be based on sell_price (800), not unit_price (500)
    expect($demandEvent->unit_price)->toBe(800);
    expect($demandEvent->revenue)->toBe($demandEvent->fulfilled_quantity * 800);
});
