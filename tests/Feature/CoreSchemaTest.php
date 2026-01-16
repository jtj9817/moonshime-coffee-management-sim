<?php

use App\Events\LowStockDetected;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Facades\Event;

test('core schema migrations allow model creation', function () {
    $location = Location::factory()->create();
    $vendor = Vendor::factory()->create();
    $product = Product::factory()->create();

    expect($location->exists)->toBeTrue();
    expect($vendor->exists)->toBeTrue();
    expect($product->exists)->toBeTrue();
});

test('relationships work correctly', function () {
    $location = Location::factory()->create();
    $product = Product::factory()->create();
    $vendor = Vendor::factory()->create();

    // Attach vendor to product
    $vendor->products()->attach($product);
    expect($vendor->products->first()->id)->toBe($product->id);
    expect($product->vendors->first()->id)->toBe($vendor->id);

    // Create inventory
    $inventory = Inventory::factory()->create([
        'location_id' => $location->id,
        'product_id' => $product->id,
    ]);

    expect($inventory->location->id)->toBe($location->id);
    expect($inventory->product->id)->toBe($product->id);
    expect($location->inventories->first()->id)->toBe($inventory->id);
});

test('low stock event fires when inventory drops below threshold', function () {
    Event::fake([LowStockDetected::class]);

    $inventory = Inventory::factory()->create([
        'quantity' => 20,
    ]);

    // Update to low stock
    $inventory->update(['quantity' => 5]);

    Event::assertDispatched(LowStockDetected::class, function ($event) use ($inventory) {
        return $event->inventory->id === $inventory->id;
    });
});

test('low stock event does not fire if already low', function () {
    Event::fake([LowStockDetected::class]);

    $inventory = Inventory::factory()->create([
        'quantity' => 5,
    ]);

    // Update, staying low
    $inventory->update(['quantity' => 4]);

    Event::assertNotDispatched(LowStockDetected::class);
});
