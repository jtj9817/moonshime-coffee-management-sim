<?php

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run CoreGameStateSeeder for products and vendors
    $this->seed(\Database\Seeders\CoreGameStateSeeder::class);
});

describe('Vendor-Product Relationships', function () {
    test('seeded products have at least one vendor', function () {
        // Get products from config that should be seeded
        $seededProductNames = collect(config('game_data.products'))->pluck('name');

        foreach ($seededProductNames as $name) {
            $product = Product::where('name', $name)->first();

            if (! $product) {
                continue; // Skip if product not found
            }

            expect($product->vendors->count())
                ->toBeGreaterThan(0, "Product '{$name}' should have at least one vendor");
        }
    });

    test('all 12 seeded products exist', function () {
        $seededProductNames = collect(config('game_data.products'))->pluck('name');

        foreach ($seededProductNames as $name) {
            expect(Product::where('name', $name)->exists())
                ->toBeTrue("Product '{$name}' should exist");
        }
    });
});

describe('Vendor Category Matching', function () {
    test('vendors only sell products from their assigned categories', function () {
        $vendorsConfig = collect(config('game_data.vendors'));

        foreach ($vendorsConfig as $vendorData) {
            $vendor = Vendor::where('name', $vendorData['name'])->first();

            if (! $vendor) {
                continue;
            }

            $vendorCategories = $vendorData['categories'];
            $attachedProducts = $vendor->products;

            foreach ($attachedProducts as $product) {
                expect(in_array($product->category, $vendorCategories, true))
                    ->toBeTrue("Vendor '{$vendor->name}' should only sell from its categories, but sells '{$product->name}' (category: {$product->category})");
            }
        }
    });

    test('BeanCo Global sells Beans category products', function () {
        $vendor = Vendor::where('name', 'BeanCo Global')->first();

        expect($vendor)->not->toBeNull();

        $beanProducts = $vendor->products()->where('category', 'Beans')->count();

        expect($beanProducts)->toBeGreaterThan(0);
    });

    test('Dairy Direct sells Milk category products', function () {
        $vendor = Vendor::where('name', 'Dairy Direct')->first();

        expect($vendor)->not->toBeNull();

        $milkProducts = $vendor->products()->where('category', 'Milk')->count();

        expect($milkProducts)->toBeGreaterThan(0);
    });
});

describe('Vendor Coverage', function () {
    test('all configured vendors exist', function () {
        $expectedVendors = collect(config('game_data.vendors'))->pluck('name');

        foreach ($expectedVendors as $name) {
            expect(Vendor::where('name', $name)->exists())
                ->toBeTrue("Vendor '{$name}' should exist");
        }
    });

    test('exactly 4 seeded vendors exist', function () {
        $expectedVendors = collect(config('game_data.vendors'))->pluck('name');
        $count = Vendor::whereIn('name', $expectedVendors)->count();

        expect($count)->toBe(4);
    });
});
