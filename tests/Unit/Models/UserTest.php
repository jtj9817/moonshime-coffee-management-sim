<?php

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user sync locations attaches inventory and vendor locations idempotently', function () {
    $user = User::factory()->create();
    $storeLocation = Location::factory()->create(['type' => 'store']);
    $vendorLocationA = Location::factory()->create(['type' => 'vendor']);
    $vendorLocationB = Location::factory()->create(['type' => 'vendor']);
    $product = Product::factory()->create();

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $storeLocation->id,
        'product_id' => $product->id,
    ]);

    $attachedCount = $user->syncLocations();

    expect($attachedCount)->toBe(3)
        ->and($user->locations()->pluck('locations.id')->sort()->values()->all())
        ->toEqualCanonicalizing([
            $storeLocation->id,
            $vendorLocationA->id,
            $vendorLocationB->id,
        ]);

    $attachedCountOnSecondSync = $user->fresh()->syncLocations();

    expect($attachedCountOnSecondSync)->toBe(0)
        ->and($user->fresh()->locations()->count())->toBe(3);
});

test('user sync locations picks up newly granted locations on later syncs', function () {
    $user = User::factory()->create();
    $storeLocationA = Location::factory()->create(['type' => 'store']);
    $vendorLocationA = Location::factory()->create(['type' => 'vendor']);
    $product = Product::factory()->create();

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $storeLocationA->id,
        'product_id' => $product->id,
    ]);

    $user->syncLocations();

    $storeLocationB = Location::factory()->create(['type' => 'store']);
    $vendorLocationB = Location::factory()->create(['type' => 'vendor']);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $storeLocationB->id,
        'product_id' => $product->id,
    ]);

    $attachedCount = $user->fresh()->syncLocations();

    expect($attachedCount)->toBe(2)
        ->and($user->fresh()->locations()->pluck('locations.id')->all())
        ->toContain($storeLocationB->id, $vendorLocationB->id);
});
