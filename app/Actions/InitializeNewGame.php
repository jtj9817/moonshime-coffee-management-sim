<?php

namespace App\Actions;

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SpikeSeeder;

/**
 * Reusable action for initializing a new game for a user.
 * Seeds inventory, pipeline activity (orders/transfers), and spikes.
 */
class InitializeNewGame
{
    /**
     * Initialize a new game for a user, including seeding all per-user state.
     */
    public function handle(User $user): GameState
    {
        $gameState = GameState::firstOrCreate(
            ['user_id' => $user->id],
            ['cash' => 10000.00, 'xp' => 0, 'day' => 1]
        );

        // Only seed state if this is a fresh game (day 1)
        if ($gameState->wasRecentlyCreated || $gameState->day === 1) {
            $this->seedInitialInventory($user);
            $this->seedPipelineActivity($user, $gameState);
            app(SpikeSeeder::class)->seedInitialSpikes($gameState);
        }

        return $gameState;
    }

    /**
     * Seed starting inventory across locations with core SKUs.
     */
    protected function seedInitialInventory(User $user): void
    {
        // Get all stores and warehouses
        $stores = Location::where('type', 'store')->get();
        $warehouse = Location::where('type', 'warehouse')->first();

        if ($stores->isEmpty() || !$warehouse) {
            return; // World not seeded yet
        }

        $products = Product::all();
        if ($products->isEmpty()) {
            return;
        }

        $primaryStore = $stores->first();

        foreach ($products as $product) {
            // Primary store: full stock
            Inventory::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'location_id' => $primaryStore->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => $product->is_perishable ? 30 : 80,
                    'last_restocked_at' => now(),
                ]
            );

            // Warehouse: bulk non-perishables, limited perishables
            Inventory::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'location_id' => $warehouse->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => $product->is_perishable ? 20 : 200,
                    'last_restocked_at' => now(),
                ]
            );

            // Secondary stores: lower baseline stock (creates transfer opportunities)
            foreach ($stores->skip(1) as $secondaryStore) {
                Inventory::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'location_id' => $secondaryStore->id,
                        'product_id' => $product->id,
                    ],
                    [
                        // Lower quantities than primary store
                        'quantity' => $product->is_perishable ? 10 : 25,
                        'last_restocked_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Seed in-transit orders and transfers arriving Days 2-4.
     */
    protected function seedPipelineActivity(User $user, GameState $gameState): void
    {
        $vendor = Vendor::first();
        $store = Location::where('type', 'store')->first();
        $warehouse = Location::where('type', 'warehouse')->first();
        $product = Product::first();

        if (!$vendor || !$store || !$warehouse || !$product) {
            return; // World not seeded yet
        }

        // Seed a shipped order arriving Day 3 (using multi-hop service)
        $vendorLocation = Location::where('type', 'vendor')->first();
        if ($vendorLocation && $store) {
            $logistics = app(\App\Services\LogisticsService::class);
            $path = $logistics->findBestRoute($vendorLocation, $store);

            if ($path && $path->isNotEmpty()) {
                app(\App\Services\OrderService::class)->createOrder(
                    user: $user,
                    vendor: $vendor,
                    targetLocation: $store,
                    items: [[
                        'product_id' => $product->id,
                        'quantity' => 50,
                        'cost_per_unit' => 1.00,
                    ]],
                    path: $path
                );
            }
        }

        // Seed an in-transit transfer arriving Day 2
        Transfer::create([
            'user_id' => $user->id,
            'source_location_id' => $warehouse->id,
            'target_location_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 25,
            'status' => 'in_transit',
            'delivery_day' => $gameState->day + 1, // Arrives Day 2
        ]);

        // Seed another transfer arriving Day 4 for variety
        $secondProduct = Product::where('id', '!=', $product->id)->first();
        if ($secondProduct) {
            Transfer::create([
                'user_id' => $user->id,
                'source_location_id' => $warehouse->id,
                'target_location_id' => $store->id,
                'product_id' => $secondProduct->id,
                'quantity' => 15,
                'status' => 'in_transit',
                'delivery_day' => $gameState->day + 3, // Arrives Day 4
            ]);
        }
    }
}
