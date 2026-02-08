<?php

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LostSale;
use App\Models\Product;
use App\Models\User;
use App\Services\DemandSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a LostSale record when demand exceeds inventory', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]); // 500 cents = $5.00
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    // Set inventory to 0 so any demand creates a stockout
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 0,
    ]);

    app(DemandSimulationService::class)->processDailyConsumption($gameState, 1);

    // Since quantity is 0 and baseline consumption is ~5 (±20% variance),
    // all demand should be lost
    $lostSales = LostSale::where('user_id', $user->id)->get();
    expect($lostSales)->toHaveCount(1);

    $lostSale = $lostSales->first();
    expect($lostSale->location_id)->toBe($location->id);
    expect($lostSale->product_id)->toBe($product->id);
    expect($lostSale->day)->toBe(1);
    expect($lostSale->quantity_lost)->toBeGreaterThan(0);
    expect($lostSale->potential_revenue_lost)->toBe($lostSale->quantity_lost * 500);
});

it('does not create a LostSale record when inventory fulfills all demand', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    // Set inventory high enough to fulfill any demand (baseline ~5 units ±20%)
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);

    app(DemandSimulationService::class)->processDailyConsumption($gameState, 1);

    expect(LostSale::where('user_id', $user->id)->count())->toBe(0);
});

it('records correct potential_revenue_lost in integer cents', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 1200]); // $12.00
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 3]);

    // Only 1 unit available, baseline demand is ~5
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    app(DemandSimulationService::class)->processDailyConsumption($gameState, 3);

    $lostSale = LostSale::where('user_id', $user->id)->first();
    // With baseline ~5 and only 1 available, lost should be ~4 (with variance)
    expect($lostSale)->not->toBeNull();
    expect($lostSale->potential_revenue_lost)->toBe($lostSale->quantity_lost * 1200);
    expect($lostSale->day)->toBe(3);
});

it('isolates LostSale records per user', function () {
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $gameState1 = GameState::factory()->create(['user_id' => $user1->id, 'day' => 1]);
    $gameState2 = GameState::factory()->create(['user_id' => $user2->id, 'day' => 1]);

    // User 1 has stockout scenario
    Inventory::factory()->create([
        'user_id' => $user1->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 0,
    ]);

    // User 2 has plenty of stock
    Inventory::factory()->create([
        'user_id' => $user2->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);

    app(DemandSimulationService::class)->processDailyConsumption($gameState1, 1);
    app(DemandSimulationService::class)->processDailyConsumption($gameState2, 1);

    expect(LostSale::where('user_id', $user1->id)->count())->toBe(1);
    expect(LostSale::where('user_id', $user2->id)->count())->toBe(0);
});
