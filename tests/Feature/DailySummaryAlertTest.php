<?php

use App\Models\Alert;
use App\Models\DemandEvent;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LostSale;
use App\Models\Product;
use App\Models\User;
use App\Listeners\CreateDailySummaryAlert;
use App\Events\TimeAdvanced;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a daily summary alert on time advanced', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500, 'storage_cost' => 10]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 3]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    // Create demand events for today
    DemandEvent::create([
        'user_id' => $user->id,
        'day' => 3,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'requested_quantity' => 8,
        'fulfilled_quantity' => 6,
        'lost_quantity' => 2,
        'unit_price' => 500,
        'revenue' => 3000,
        'lost_revenue' => 1000,
    ]);

    // Create lost sale record
    LostSale::create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'day' => 3,
        'quantity_lost' => 2,
        'potential_revenue_lost' => 1000,
    ]);

    $listener = new CreateDailySummaryAlert();
    $listener->handle(new TimeAdvanced(3, $gameState));

    $alert = Alert::where('user_id', $user->id)
        ->where('type', 'summary')
        ->where('created_day', 3)
        ->first();

    expect($alert)->not->toBeNull();
    expect($alert->severity)->toBe('info');
    expect($alert->data)->toHaveKeys(['units_sold', 'lost_sales', 'storage_fees', 'revenue']);
    expect($alert->data['units_sold'])->toBe(6);
    expect($alert->data['lost_sales'])->toBe(2);
    expect($alert->data['revenue'])->toBe(3000);
});

it('includes storage fee deduction in summary', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500, 'storage_cost' => 20]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 5]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 30,
    ]);

    $listener = new CreateDailySummaryAlert();
    $listener->handle(new TimeAdvanced(5, $gameState));

    $alert = Alert::where('user_id', $user->id)
        ->where('type', 'summary')
        ->first();

    expect($alert)->not->toBeNull();
    // Storage fees = 30 * 20 = 600 cents
    expect($alert->data['storage_fees'])->toBe(600);
});

it('creates only one summary per day per user', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 500, 'storage_cost' => 10]);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 2]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    $listener = new CreateDailySummaryAlert();
    $listener->handle(new TimeAdvanced(2, $gameState));
    $listener->handle(new TimeAdvanced(2, $gameState));

    expect(Alert::where('user_id', $user->id)->where('type', 'summary')->where('created_day', 2)->count())->toBe(1);
});
