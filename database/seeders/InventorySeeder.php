<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeds global (non-user-specific) inventory for all stores × products with random quantities.
 */
class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $logger = Log::channel('game-initialization');
        $logger->info('InventorySeeder: Starting global inventory seeding');

        try {
            $stores = Location::where('type', 'store')->get();
            $products = Product::all();

            if ($stores->isEmpty()) {
                $logger->warning('InventorySeeder: No stores found, skipping inventory seeding', [
                    'hint' => 'Run GraphSeeder first to create locations',
                ]);
                return;
            }

            if ($products->isEmpty()) {
                $logger->warning('InventorySeeder: No products found, skipping inventory seeding', [
                    'hint' => 'Run CoreGameStateSeeder first to create products',
                ]);
                return;
            }

            $logger->info('InventorySeeder: Seeding inventory', [
                'stores_count' => $stores->count(),
                'products_count' => $products->count(),
                'total_combinations' => $stores->count() * $products->count(),
            ]);

            $inventoryCount = 0;

            foreach ($stores as $store) {
                foreach ($products as $product) {
                    Inventory::updateOrCreate(
                        [
                            'user_id' => null, // Global inventory (non-user-specific)
                            'location_id' => $store->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'quantity' => fake()->numberBetween(50, 200),
                            'last_restocked_at' => now(),
                        ]
                    );
                    $inventoryCount++;
                }
            }

            $logger->info('InventorySeeder: Global inventory seeding completed', [
                'inventory_entries_created' => $inventoryCount,
            ]);
        } catch (\Exception $e) {
            $logger->error('InventorySeeder: Seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
