<?php

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a 7-day forecast array', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 5]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);

    $service = app(DemandForecastService::class);
    $forecast = $service->getForecast($user->id, $location->id, $product->id, $gameState->day);

    expect($forecast)->toHaveCount(7);
});

it('each forecast row has required fields', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    $service = app(DemandForecastService::class);
    $forecast = $service->getForecast($user->id, $location->id, $product->id, $gameState->day);

    foreach ($forecast as $row) {
        expect($row)->toHaveKeys(['day_offset', 'predicted_demand', 'predicted_stock', 'risk_level', 'incoming_deliveries']);
        expect($row['risk_level'])->toBeIn(['low', 'medium', 'stockout']);
        expect($row['day_offset'])->toBeGreaterThanOrEqual(1);
        expect($row['day_offset'])->toBeLessThanOrEqual(7);
    }
});

it('predicts stock depletion based on baseline consumption', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

    // With 15 units and baseline ~5/day, stock should deplete around day 3
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 15,
    ]);

    $service = app(DemandForecastService::class);
    $forecast = $service->getForecast($user->id, $location->id, $product->id, $gameState->day);

    // Day 1: 15 - 5 = 10 (low risk)
    expect($forecast[0]['predicted_stock'])->toBe(10);
    expect($forecast[0]['risk_level'])->toBe('low');

    // Day 2: 10 - 5 = 5 (medium risk)
    expect($forecast[1]['predicted_stock'])->toBe(5);
    expect($forecast[1]['risk_level'])->toBe('medium');

    // Day 3: 5 - 5 = 0 (stockout)
    expect($forecast[2]['predicted_stock'])->toBe(0);
    expect($forecast[2]['risk_level'])->toBe('stockout');

    // Day 4+: should remain at 0
    expect($forecast[3]['predicted_stock'])->toBe(0);
    expect($forecast[3]['risk_level'])->toBe('stockout');
});

it('applies demand spike multiplier to predictions', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 3]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    // Active demand spike with 2x multiplier covering days 3-8
    SpikeEvent::factory()->create([
        'user_id' => $user->id,
        'type' => 'demand',
        'magnitude' => 2.0,
        'location_id' => $location->id,
        'product_id' => null, // global product
        'starts_at_day' => 2,
        'ends_at_day' => 8,
        'is_active' => true,
    ]);

    $service = app(DemandForecastService::class);
    $forecast = $service->getForecast($user->id, $location->id, $product->id, $gameState->day);

    // With 2x spike, demand = 10/day instead of 5
    expect($forecast[0]['predicted_demand'])->toBe(10);
    expect($forecast[0]['predicted_stock'])->toBe(40); // 50 - 10
});

it('includes incoming deliveries from pending orders', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500]);
    $vendor = Vendor::factory()->create();
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 5]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    // Order delivering on day 7
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $location->id,
        'delivery_day' => 7,
        'created_day' => 4,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 20,
        'cost_per_unit' => 300,
    ]);

    $service = app(DemandForecastService::class);
    $forecast = $service->getForecast($user->id, $location->id, $product->id, $gameState->day);

    // Day offset 2 = game day 7 (delivery day)
    $dayOffset2 = collect($forecast)->firstWhere('day_offset', 2);
    expect($dayOffset2['incoming_deliveries'])->toBe(20);

    // Stock should include the delivery
    // Day 1 (day 6): 10 - 5 = 5
    // Day 2 (day 7): 5 + 20 - 5 = 20
    expect($forecast[0]['predicted_stock'])->toBe(5);
    expect($forecast[1]['predicted_stock'])->toBe(20);
});
