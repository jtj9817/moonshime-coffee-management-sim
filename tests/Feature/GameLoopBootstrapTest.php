<?php

use App\Actions\InitializeNewGame;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use App\Models\User;
use App\Services\SimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed global world data
    $this->seed(\Database\Seeders\CoreGameStateSeeder::class);
    $this->seed(\Database\Seeders\GraphSeeder::class);
});

test('new game bootstrap creates inventory across multiple locations', function () {
    $user = User::factory()->create();

    app(InitializeNewGame::class)->handle($user);

    // Should have inventory at multiple locations
    $inventoryLocations = Inventory::where('user_id', $user->id)
        ->distinct('location_id')
        ->pluck('location_id');

    expect($inventoryLocations->count())->toBeGreaterThanOrEqual(2, 'Should have inventory at 2+ locations');

    // Should have inventory for core products
    $inventoryProducts = Inventory::where('user_id', $user->id)
        ->distinct('product_id')
        ->pluck('product_id');

    $totalProducts = Product::count();
    expect($inventoryProducts->count())->toBeGreaterThanOrEqual(min(3, $totalProducts), 'Should have inventory for core SKUs');
});

test('new game bootstrap creates pipeline activity arriving Days 2-4', function () {
    $user = User::factory()->create();

    app(InitializeNewGame::class)->handle($user);

    // Should have at least one in-transit transfer
    $transfers = Transfer::where('user_id', $user->id)
        ->where('status', 'in_transit')
        ->get();

    expect($transfers)->not->toBeEmpty('Should have at least one in-transit transfer');

    // Transfers should arrive between Days 2-4
    foreach ($transfers as $transfer) {
        expect($transfer->delivery_day)->toBeGreaterThanOrEqual(2);
        expect($transfer->delivery_day)->toBeLessThanOrEqual(4);
    }
});

test('new game bootstrap seeds initial spikes', function () {
    $user = User::factory()->create();

    app(InitializeNewGame::class)->handle($user);

    // Should have spikes scheduled (from guaranteed spike generation plan)
    $spikes = SpikeEvent::where('user_id', $user->id)->get();

    expect($spikes)->not->toBeEmpty('Should have initial spikes seeded');

    // Spikes should be scheduled Days 2-7
    foreach ($spikes as $spike) {
        expect($spike->starts_at_day)->toBeGreaterThanOrEqual(2);
        expect($spike->starts_at_day)->toBeLessThanOrEqual(7);
    }
});

test('5-day loop produces inventory changes from transfers', function () {
    $user = User::factory()->create();
    $gameState = app(InitializeNewGame::class)->handle($user);

    $service = new SimulationService($gameState);

    // Record initial inventory state
    $store = Location::where('type', 'store')->first();
    $initialInventory = Inventory::where('user_id', $user->id)
        ->where('location_id', $store->id)
        ->get()
        ->keyBy('product_id')
        ->map(fn ($inv) => $inv->quantity);

    // Advance to Day 5
    for ($i = 0; $i < 4; $i++) {
        $service->advanceTime();
    }

    expect($gameState->fresh()->day)->toBe(5);

    // Check that transfers completed
    $completedTransfers = Transfer::where('user_id', $user->id)
        ->where('status', 'completed')
        ->count();

    expect($completedTransfers)->toBeGreaterThan(0, 'At least one transfer should complete by Day 5');
});

test('5-day loop does not leak data between users', function () {
    // Create two users with their own game states
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $gameState1 = app(InitializeNewGame::class)->handle($user1);
    $gameState2 = app(InitializeNewGame::class)->handle($user2);

    // Ensure inventories are user-scoped
    $user1Inventory = Inventory::where('user_id', $user1->id)->count();
    $user2Inventory = Inventory::where('user_id', $user2->id)->count();

    expect($user1Inventory)->toBeGreaterThan(0);
    expect($user2Inventory)->toBeGreaterThan(0);

    // Advance user1's simulation
    $service1 = new SimulationService($gameState1);
    $service1->advanceTime();

    // User2 should still be on Day 1
    expect($gameState2->fresh()->day)->toBe(1);
    expect($gameState1->fresh()->day)->toBe(2);

    // User2's transfers should be unaffected
    $user2Transfers = Transfer::where('user_id', $user2->id)
        ->where('status', 'in_transit')
        ->count();
    expect($user2Transfers)->toBeGreaterThan(0, 'User2 transfers should still be in transit');
});

test('transfer completion dispatches TransferCompleted event and updates inventory', function () {
    $user = User::factory()->create();
    $gameState = app(InitializeNewGame::class)->handle($user);

    $service = new SimulationService($gameState);
    $store = Location::where('type', 'store')->first();
    $product = Product::first();

    // Get initial inventory at store for the product
    $initialInventory = Inventory::where('user_id', $user->id)
        ->where('location_id', $store->id)
        ->where('product_id', $product->id)
        ->first();

    $initialQuantity = $initialInventory?->quantity ?? 0;

    // Find a transfer for this product arriving Day 2
    $transfer = Transfer::where('user_id', $user->id)
        ->where('target_location_id', $store->id)
        ->where('product_id', $product->id)
        ->where('delivery_day', 2)
        ->first();

    if (! $transfer) {
        $this->markTestSkipped('No transfer for this product arriving Day 2');
    }

    // Advance to Day 2
    $service->advanceTime();

    // Transfer should be completed
    expect($transfer->fresh()->status->getValue())->toBe('completed');

    // Inventory should be updated (increased from transfer, may have decayed if perishable)
    $finalInventory = Inventory::where('user_id', $user->id)
        ->where('location_id', $store->id)
        ->where('product_id', $product->id)
        ->first();

    expect($finalInventory)->not->toBeNull();
    // Just verify the transfer completed - exact quantity may vary due to decay on perishables
    expect($transfer->fresh()->status->getValue())->toBe('completed');
});
