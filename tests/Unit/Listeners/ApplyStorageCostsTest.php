<?php

use App\Events\TimeAdvanced;
use App\Listeners\ApplyStorageCosts;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('apply storage costs listener deducts integer cents', function () {
    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 10000,
    ]);

    $location = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['storage_cost' => 37]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    app(ApplyStorageCosts::class)->handle(new TimeAdvanced(2, $gameState));

    $gameState->refresh();

    expect($gameState->cash)->toBe(9889)
        ->and($gameState->cash)->toBeInt();
});

test('apply storage costs listener only deducts inventory for the active user scope', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $gameState = GameState::factory()->create([
        'user_id' => $userA->id,
        'cash' => 10000,
    ]);

    $location = Location::factory()->create(['type' => 'store']);

    $cheapProduct = Product::factory()->create(['storage_cost' => 10]);
    $expensiveProduct = Product::factory()->create(['storage_cost' => 500]);

    Inventory::factory()->create([
        'user_id' => $userA->id,
        'location_id' => $location->id,
        'product_id' => $cheapProduct->id,
        'quantity' => 5,
    ]);

    Inventory::factory()->create([
        'user_id' => $userB->id,
        'location_id' => $location->id,
        'product_id' => $expensiveProduct->id,
        'quantity' => 9,
    ]);

    app(ApplyStorageCosts::class)->handle(new TimeAdvanced(2, $gameState));

    $gameState->refresh();

    // Only user A inventory should be counted: 5 * 10 = 50 cents.
    expect($gameState->cash)->toBe(9950);
});
