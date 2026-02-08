<?php

use App\Models\DemandEvent;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationDailyMetric;
use App\Models\LostSale;
use App\Models\Product;
use App\Models\User;
use App\Listeners\CreateLocationDailyMetrics;
use App\Events\TimeAdvanced;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates location daily metrics with correct revenue from demand events', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create([
        'unit_price' => 500,   // $5.00
        'storage_cost' => 10,  // $0.10/unit/day
    ]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 5]);

    // Simulate inventory
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    // Create demand events for today (simulating what DemandSimulationService produces)
    DemandEvent::create([
        'user_id' => $user->id,
        'day' => 5,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'requested_quantity' => 8,
        'fulfilled_quantity' => 8,
        'lost_quantity' => 0,
        'unit_price' => 500,
        'revenue' => 4000,       // 8 * 500 cents
        'lost_revenue' => 0,
    ]);

    $listener = new CreateLocationDailyMetrics();
    $listener->handle(new TimeAdvanced(5, $gameState));

    $metric = LocationDailyMetric::where('user_id', $user->id)
        ->where('location_id', $location->id)
        ->where('day', 5)
        ->first();

    expect($metric)->not->toBeNull();
    expect($metric->revenue)->toBe(4000);           // 8 * 500
    expect($metric->units_sold)->toBe(8);
    expect($metric->opex)->toBe(50 * 10);            // quantity * storage_cost = 500
    expect($metric->net_profit)->toBe(4000 - 0 - 500); // revenue - cogs - opex
});

it('calculates opex as inventory quantity times storage cost', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product1 = Product::factory()->create(['unit_price' => 500, 'storage_cost' => 20]);
    $product2 = Product::factory()->create(['unit_price' => 300, 'storage_cost' => 15]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 3]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product1->id,
        'quantity' => 30,
    ]);
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product2->id,
        'quantity' => 40,
    ]);

    $listener = new CreateLocationDailyMetrics();
    $listener->handle(new TimeAdvanced(3, $gameState));

    $metric = LocationDailyMetric::where('user_id', $user->id)
        ->where('location_id', $location->id)
        ->where('day', 3)
        ->first();

    expect($metric)->not->toBeNull();
    // OpEx = (30 * 20) + (40 * 15) = 600 + 600 = 1200
    expect($metric->opex)->toBe(1200);
});

it('tracks stockout count from lost sales', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500, 'storage_cost' => 10]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 2]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    // Simulate 2 lost sale records for this day/location
    LostSale::create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'day' => 2,
        'quantity_lost' => 5,
        'potential_revenue_lost' => 2500,
    ]);

    $listener = new CreateLocationDailyMetrics();
    $listener->handle(new TimeAdvanced(2, $gameState));

    $metric = LocationDailyMetric::where('user_id', $user->id)
        ->where('location_id', $location->id)
        ->where('day', 2)
        ->first();

    expect($metric)->not->toBeNull();
    expect($metric->stockouts)->toBe(1); // 1 lost sale record = 1 stockout event
});

it('creates metrics for each active store location', function () {
    $user = User::factory()->create();
    $store1 = Location::factory()->create(['type' => 'store']);
    $store2 = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500, 'storage_cost' => 10]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $store1->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $store2->id,
        'product_id' => $product->id,
        'quantity' => 30,
    ]);

    $listener = new CreateLocationDailyMetrics();
    $listener->handle(new TimeAdvanced(1, $gameState));

    expect(LocationDailyMetric::where('user_id', $user->id)->where('day', 1)->count())->toBe(2);
});

it('isolates metrics per user', function () {
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500, 'storage_cost' => 10]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $gs1 = GameState::factory()->create(['user_id' => $user1->id, 'day' => 1]);
    $gs2 = GameState::factory()->create(['user_id' => $user2->id, 'day' => 1]);

    Inventory::factory()->create([
        'user_id' => $user1->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);
    Inventory::factory()->create([
        'user_id' => $user2->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    $listener = new CreateLocationDailyMetrics();
    $listener->handle(new TimeAdvanced(1, $gs1));
    $listener->handle(new TimeAdvanced(1, $gs2));

    $m1 = LocationDailyMetric::where('user_id', $user1->id)->first();
    $m2 = LocationDailyMetric::where('user_id', $user2->id)->first();

    // OpEx should differ: user1 = 20*10=200, user2 = 50*10=500
    expect($m1->opex)->toBe(200);
    expect($m2->opex)->toBe(500);
});
