<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds initial inventory for stores.
 * Every store gets every product with quantity >= 50.
 */
class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = Location::where('type', 'store')->get();
        $products = Product::all();

        // For each store and product, seed inventory with quantity 50-200
        foreach ($stores as $store) {
            foreach ($products as $product) {
                Inventory::updateOrCreate(
                    [
                        'location_id' => $store->id,
                        'product_id' => $product->id,
                        'user_id' => null, // Global inventory, not user-specific
                    ],
                    [
                        'quantity' => fake()->numberBetween(50, 200),
                        'last_restocked_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Seed inventory for a specific user's store.
     */
    public static function seedForUser(User $user, Location $store): void
    {
        $products = Product::all();

        foreach ($products as $product) {
            Inventory::updateOrCreate(
                [
                    'location_id' => $store->id,
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                ],
                [
                    'quantity' => fake()->numberBetween(50, 200),
                    'last_restocked_at' => now(),
                ]
            );
        }
    }
}
