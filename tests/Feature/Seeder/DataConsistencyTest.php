<?php

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run seeders for realistic data
    $this->seed(\Database\Seeders\CoreGameStateSeeder::class);
    $this->seed(\Database\Seeders\GraphSeeder::class);
    $this->seed(\Database\Seeders\InventorySeeder::class);
});

describe('Product Categories', function () {
    test('exactly 11 distinct product categories exist', function () {
        $categories = Product::pluck('category')->unique();

        expect($categories)->toHaveCount(11);
    });

    test('all config categories are present', function () {
        $expectedCategories = config('game_data.categories');
        $actualCategories = Product::pluck('category')->unique()->sort()->values()->toArray();

        expect($actualCategories)->toEqualCanonicalizing($expectedCategories);
    });
});

describe('Specific Products', function () {
    test('Espresso Blend product exists', function () {
        expect(Product::where('name', 'Espresso Blend')->exists())->toBeTrue();
    });

    test('all 11 products from config exist', function () {
        $expectedProducts = collect(config('game_data.products'))->pluck('name');

        foreach ($expectedProducts as $name) {
            expect(Product::where('name', $name)->exists())
                ->toBeTrue("Product '{$name}' should exist");
        }
    });
});

describe('Location Names', function () {
    test('all location names are unique', function () {
        $totalLocations = Location::count();
        $uniqueNames = Location::pluck('name')->unique()->count();

        expect($uniqueNames)->toBe($totalLocations);
    });

    test('no location is named "Test Coffee"', function () {
        expect(Location::where('name', 'Test Coffee')->exists())->toBeFalse();
    });
});

describe('Inventory Levels', function () {
    test('all store inventory levels are >= 50', function () {
        $lowInventory = Inventory::where('quantity', '<', 50)->count();

        expect($lowInventory)->toBe(0);
    });

    test('every store has inventory for every product', function () {
        $stores = Location::where('type', 'store')->get();
        $products = Product::all();

        foreach ($stores as $store) {
            foreach ($products as $product) {
                $inventory = Inventory::where('location_id', $store->id)
                    ->where('product_id', $product->id)
                    ->first();

                expect($inventory)
                    ->not->toBeNull("Store '{$store->name}' should have inventory for '{$product->name}'");
            }
        }
    });
});
