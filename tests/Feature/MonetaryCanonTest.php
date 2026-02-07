<?php

use App\Models\GameState;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route as RouteModel;
use App\Models\DemandEvent;
use App\Models\User;
use Database\Seeders\CoreGameStateSeeder;
use Database\Seeders\GraphSeeder;

/**
 * Phase 0 – Monetary Unit Canonicalization Tests
 *
 * All monetary values in the backend must be stored and computed as integer cents.
 * Conversion to display dollars ($X.XX) occurs only at the frontend boundary.
 */

// ─── Model Cast Tests ──────────────────────────────────────────────

test('GameState cash cast returns integer', function () {
    $gs = GameState::factory()->create(['cash' => 1000000]);
    $gs->refresh();
    expect($gs->cash)->toBeInt()->toBe(1000000);
});

test('Order total_cost cast returns integer', function () {
    $order = Order::factory()->create(['total_cost' => 5000]);
    $order->refresh();
    expect($order->total_cost)->toBeInt()->toBe(5000);
});

test('OrderItem cost_per_unit cast returns integer', function () {
    $item = OrderItem::factory()->create(['cost_per_unit' => 250]);
    $item->refresh();
    expect($item->cost_per_unit)->toBeInt()->toBe(250);
});

test('Product unit_price cast returns integer', function () {
    $product = Product::factory()->create(['unit_price' => 500]);
    $product->refresh();
    expect($product->unit_price)->toBeInt()->toBe(500);
});

test('Product storage_cost cast returns integer', function () {
    $product = Product::factory()->create(['storage_cost' => 25]);
    $product->refresh();
    expect($product->storage_cost)->toBeInt()->toBe(25);
});

test('Route cost cast returns integer', function () {
    $route = RouteModel::factory()->create(['cost' => 150]);
    $route->refresh();
    expect($route->cost)->toBeInt()->toBe(150);
});

test('DemandEvent monetary fields cast as integer', function () {
    $event = DemandEvent::factory()->create([
        'unit_price' => 500,
        'revenue' => 2500,
        'lost_revenue' => 1000,
    ]);
    $event->refresh();
    expect($event->unit_price)->toBeInt()->toBe(500);
    expect($event->revenue)->toBeInt()->toBe(2500);
    expect($event->lost_revenue)->toBeInt()->toBe(1000);
});

// ─── Initialization Invariants ──────────────────────────────────────

test('new game initializes cash as 1000000 integer cents', function () {
    $this->seed([CoreGameStateSeeder::class, GraphSeeder::class]);
    $user = User::factory()->create();
    $gameState = app(\App\Actions\InitializeNewGame::class)->handle($user);

    expect($gameState->cash)->toBeInt()->toBe(1000000);
});

test('middleware fallback game state uses 1000000 integer cents', function () {
    $this->seed([CoreGameStateSeeder::class, GraphSeeder::class]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/game/dashboard');

    $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->where('game.state.cash', 1000000)
    );
});

// ─── Factory Consistency ────────────────────────────────────────────

test('GameState factory generates integer cent cash values', function () {
    $gs = GameState::factory()->create();
    expect($gs->cash)->toBeInt();
});

test('Order factory generates integer cent total_cost values', function () {
    $order = Order::factory()->create();
    expect($order->total_cost)->toBeInt();
});

test('OrderItem factory generates integer cent cost_per_unit values', function () {
    $item = OrderItem::factory()->create();
    expect($item->cost_per_unit)->toBeInt();
});

test('Product factory generates integer cent monetary values', function () {
    $product = Product::factory()->create();
    expect($product->storage_cost)->toBeInt();
});

// ─── Domain Arithmetic (No Floats) ──────────────────────────────────

test('reset game restores cash to 1000000 integer cents', function () {
    $this->seed([CoreGameStateSeeder::class, GraphSeeder::class]);
    $user = User::factory()->create();
    GameState::factory()->create(['user_id' => $user->id, 'day' => 10, 'cash' => 500]);

    $this->actingAs($user)->post('/game/reset');

    $gameState = GameState::where('user_id', $user->id)->first();
    expect($gameState->cash)->toBeInt()->toBe(1000000);
});
