<?php

use App\Actions\GenerateIsolationAlerts;
use App\Models\Alert;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Route;
use App\Models\SpikeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it generates isolation alert only when unreachable and stock is low', function () {
    // 1. Setup
    $warehouse = Location::factory()->create(['type' => 'warehouse']);
    $store = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create();
    
    // Create an inactive route (store is isolated)
    Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $store->id,
        'is_active' => false,
    ]);

    // Create an active spike that likely caused this
    $spike = SpikeEvent::factory()->create([
        'type' => 'blizzard',
        'is_active' => true,
    ]);

    // Scenario A: Reachability false, but stock is NOT low
    Inventory::factory()->create([
        'location_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 100, // Not low (threshold is 10)
    ]);

    $action = app(GenerateIsolationAlerts::class);
    $action->handle();

    expect(Alert::where('type', 'isolation')->count())->toBe(0);

    // Scenario B: Reachability false, AND stock is low
    Inventory::where('location_id', $store->id)->update(['quantity' => 5]);

    $action->handle();

    expect(Alert::where('type', 'isolation')->count())->toBe(1);
    
    $alert = Alert::where('type', 'isolation')->first();
    expect($alert->location_id)->toBe($store->id);
    expect($alert->spike_event_id)->toBe($spike->id);
    expect($alert->severity)->toBe('critical');
});

test('it does not generate alert if reachable even if stock is low', function () {
    $warehouse = Location::factory()->create(['type' => 'warehouse']);
    $store = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create();
    
    // Create an ACTIVE route
    Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $store->id,
        'is_active' => true,
    ]);

    Inventory::factory()->create([
        'location_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 5, // Low stock
    ]);

    $action = app(GenerateIsolationAlerts::class);
    $action->handle();

    expect(Alert::where('type', 'isolation')->count())->toBe(0);
});