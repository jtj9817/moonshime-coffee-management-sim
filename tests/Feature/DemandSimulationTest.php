<?php

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
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
