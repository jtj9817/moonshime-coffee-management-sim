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

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->gameState = GameState::factory()->create([
        'user_id' => $this->user->id,
        'cash' => 100000,
        'day' => 5,
    ]);
    $this->demandService = app(DemandSimulationService::class);
});

describe('Demand Spike Simulation', function () {
    test('demand spike increases inventory consumption', function () {
        $store = Location::factory()->create(['type' => 'store']);
        $product = Product::factory()->create();

        $inventory = Inventory::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        // Create a demand spike with 2x magnitude
        $spike = SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'demand',
            'magnitude' => 2.0, // 2x demand
            'location_id' => $store->id,
            'product_id' => $product->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 10,
        ]);

        // Run consumption simulation
        $this->demandService->processDailyConsumption($this->gameState, 5);

        $inventory->refresh();
        $consumed = 1000 - $inventory->quantity;

        // With 2x multiplier, consumption should be significantly higher than baseline
        // Baseline is ~5 units (±20% variance), so with 2x should be ~10+ units
        expect($consumed)->toBeGreaterThan(5);
    });

    test('no spike means baseline consumption', function () {
        $store = Location::factory()->create(['type' => 'store']);
        $product = Product::factory()->create();

        $inventory = Inventory::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        // No spike created

        // Run consumption simulation multiple times to average out variance
        $totalConsumed = 0;
        for ($i = 0; $i < 5; $i++) {
            $before = $inventory->refresh()->quantity;
            $this->demandService->processDailyConsumption($this->gameState, 5 + $i);
            $after = $inventory->refresh()->quantity;
            $totalConsumed += ($before - $after);
        }

        $avgConsumed = $totalConsumed / 5;

        // Baseline is ~5 units per day (±20% variance)
        // Average should be around 4-6 units
        expect($avgConsumed)->toBeGreaterThan(3)
            ->and($avgConsumed)->toBeLessThan(8);
    });

    test('global demand spike affects all products at location', function () {
        $store = Location::factory()->create(['type' => 'store']);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $inventory1 = Inventory::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $store->id,
            'product_id' => $product1->id,
            'quantity' => 1000,
        ]);

        $inventory2 = Inventory::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $store->id,
            'product_id' => $product2->id,
            'quantity' => 1000,
        ]);

        // Create location-wide demand spike (no product_id = affects all)
        SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'demand',
            'magnitude' => 2.0,
            'location_id' => $store->id,
            'product_id' => null, // Affects all products
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 10,
        ]);

        $this->demandService->processDailyConsumption($this->gameState, 5);

        $consumed1 = 1000 - $inventory1->refresh()->quantity;
        $consumed2 = 1000 - $inventory2->refresh()->quantity;

        // Both should have elevated consumption
        expect($consumed1)->toBeGreaterThan(5)
            ->and($consumed2)->toBeGreaterThan(5);
    });

    test('demand spike only affects correct user inventory', function () {
        $store = Location::factory()->create(['type' => 'store']);
        $product = Product::factory()->create();

        $otherUser = User::factory()->create();

        $myInventory = Inventory::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        $otherInventory = Inventory::factory()->create([
            'user_id' => $otherUser->id,
            'location_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        // Create demand spike for MY user
        SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'demand',
            'magnitude' => 3.0, // 3x demand
            'location_id' => $store->id,
            'product_id' => $product->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 10,
        ]);

        $this->demandService->processDailyConsumption($this->gameState, 5);

        $myInventory->refresh();
        $otherInventory->refresh();

        // My inventory should be consumed, other user's should NOT be touched
        expect($myInventory->quantity)->toBeLessThan(1000)
            ->and($otherInventory->quantity)->toBe(1000);
    });
});

describe('Multi-day Simulation', function () {
    test('cumulative consumption over multiple days with active spike', function () {
        $store = Location::factory()->create(['type' => 'store']);
        $product = Product::factory()->create();

        $inventory = Inventory::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        // Create 2x demand spike
        SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'demand',
            'magnitude' => 2.0,
            'location_id' => $store->id,
            'product_id' => $product->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 15,
        ]);

        // Simulate 5 days
        for ($day = 5; $day <= 9; $day++) {
            $this->demandService->processDailyConsumption($this->gameState, $day);
        }

        $inventory->refresh();
        $totalConsumed = 1000 - $inventory->quantity;

        // 5 days × ~10 units/day (2x of baseline 5) = ~50 units minimum
        expect($totalConsumed)->toBeGreaterThan(40);
    });

    test('consumption stops when inventory depleted', function () {
        $store = Location::factory()->create(['type' => 'store']);
        $product = Product::factory()->create();

        // Start with low inventory
        $inventory = Inventory::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // Create high demand spike
        SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'demand',
            'magnitude' => 5.0, // Very high demand
            'location_id' => $store->id,
            'product_id' => $product->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 15,
        ]);

        // Simulate multiple days
        for ($day = 5; $day <= 10; $day++) {
            $this->demandService->processDailyConsumption($this->gameState, $day);
        }

        $inventory->refresh();

        // Should not go below zero
        expect($inventory->quantity)->toBeGreaterThanOrEqual(0);
    });
});
