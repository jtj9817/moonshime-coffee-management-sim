<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Route;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class GraphSeeder extends Seeder
{
    /**
     * Canonical node set for deterministic, idempotent graph seeding.
     *
     * @var array<string, array{name: string, address: string, max_storage: int, type: string}>
     */
    private const LOCATION_BLUEPRINTS = [
        // Vendors
        'vendor_1' => [
            'name' => 'Bean Supply North',
            'address' => '101 Roaster Row',
            'max_storage' => 500,
            'type' => 'vendor',
        ],
        'vendor_2' => [
            'name' => 'Atlas Imports',
            'address' => '202 Portside Ave',
            'max_storage' => 500,
            'type' => 'vendor',
        ],
        'vendor_3' => [
            'name' => 'Summit Trade House',
            'address' => '303 Commerce Blvd',
            'max_storage' => 500,
            'type' => 'vendor',
        ],

        // Warehouses
        'warehouse_1' => [
            'name' => 'North Distribution Depot',
            'address' => '404 Logistics Way',
            'max_storage' => 900,
            'type' => 'warehouse',
        ],
        'warehouse_2' => [
            'name' => 'South Distribution Depot',
            'address' => '505 Freight Loop',
            'max_storage' => 900,
            'type' => 'warehouse',
        ],

        // Hubs
        'hub_1' => [
            'name' => 'River Hub',
            'address' => '606 Transit St',
            'max_storage' => 700,
            'type' => 'hub',
        ],
        'hub_2' => [
            'name' => 'Sky Hub',
            'address' => '707 Airlink Dr',
            'max_storage' => 700,
            'type' => 'hub',
        ],
        'hub_3' => [
            'name' => 'Metro Hub',
            'address' => '808 Junction Rd',
            'max_storage' => 700,
            'type' => 'hub',
        ],

        // Stores
        'store_1' => [
            'name' => 'Moonshine Central',
            'address' => '909 Main Street',
            'max_storage' => 1000,
            'type' => 'store',
        ],
        'store_2' => [
            'name' => 'Moonshine Riverside',
            'address' => '100 Riverfront Blvd',
            'max_storage' => 650,
            'type' => 'store',
        ],
        'store_3' => [
            'name' => 'Moonshine Midtown',
            'address' => '111 Midtown Ave',
            'max_storage' => 650,
            'type' => 'store',
        ],
        'store_4' => [
            'name' => 'Moonshine Uptown',
            'address' => '121 Uptown Pkwy',
            'max_storage' => 650,
            'type' => 'store',
        ],
        'store_5' => [
            'name' => 'Moonshine Harbor',
            'address' => '131 Harbor Lane',
            'max_storage' => 650,
            'type' => 'store',
        ],
        'store_6' => [
            'name' => 'Moonshine University',
            'address' => '141 College Cir',
            'max_storage' => 650,
            'type' => 'store',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $logger = Log::channel('game-initialization');
        $logger->info('GraphSeeder: Starting logistics network creation');

        try {
            // Check for existing locations (idempotency)
            $existingLocationCount = Location::count();
            if ($existingLocationCount > 0) {
                $logger->warning('GraphSeeder: Locations already exist, idempotency check', [
                    'existing_count' => $existingLocationCount,
                    'action' => 'Proceeding with canonical upsert strategy',
                ]);
            }

            // 1. Create or update canonical nodes
            $logger->info('GraphSeeder: Upserting canonical location nodes');

            $locationsByKey = collect(self::LOCATION_BLUEPRINTS)
                ->map(fn (array $attributes) => Location::updateOrCreate(
                    ['name' => $attributes['name']],
                    $attributes
                ));

            $vendors = $locationsByKey->only(['vendor_1', 'vendor_2', 'vendor_3'])->values();
            $warehouses = $locationsByKey->only(['warehouse_1', 'warehouse_2'])->values();
            $hubs = $locationsByKey->only(['hub_1', 'hub_2', 'hub_3'])->values();
            $stores = $locationsByKey->only(['store_1', 'store_2', 'store_3', 'store_4', 'store_5', 'store_6'])->values();

            $logger->info('GraphSeeder: Created location nodes', [
                'vendors' => $vendors->count(),
                'warehouses' => $warehouses->count(),
                'hubs' => $hubs->count(),
                'stores' => $stores->count(),
                'total_locations' => $vendors->count() + $warehouses->count() + $hubs->count() + $stores->count(),
            ]);

            // Track route creation counts
            $routeCounts = [
                'vendors_to_warehouses' => 0,
                'warehouses_to_stores' => 0,
                'stores_to_stores' => 0,
                'vendors_to_hubs' => 0,
                'hubs_to_stores' => 0,
            ];

            // 2. Connect Vendors to Warehouses (Truck, Cheap, Reliable)
            $logger->info('GraphSeeder: Connecting vendors → warehouses');
            foreach ($vendors as $vendor) {
                foreach ($warehouses as $warehouse) {
                    Route::updateOrCreate([
                        'source_id' => $vendor->id,
                        'target_id' => $warehouse->id,
                        'transport_mode' => 'Truck',
                    ], [
                        'cost' => 50, // cents
                        'transit_days' => 2,
                        'capacity' => 1000,
                        'is_active' => true,
                        'weather_vulnerability' => true,
                    ]);
                    $routeCounts['vendors_to_warehouses']++;
                }
            }

            // 3. Connect Warehouses to Stores (Truck, Standard)
            $logger->info('GraphSeeder: Connecting warehouses → stores');
            foreach ($warehouses as $warehouse) {
                foreach ($stores as $store) {
                    Route::updateOrCreate([
                        'source_id' => $warehouse->id,
                        'target_id' => $store->id,
                        'transport_mode' => 'Truck',
                    ], [
                        'cost' => 100, // cents
                        'transit_days' => 1,
                        'capacity' => 1000,
                        'is_active' => true,
                        'weather_vulnerability' => true,
                    ]);
                    $routeCounts['warehouses_to_stores']++;
                }
            }

            // 4. Connect Stores to Stores (Lateral, Emergency)
            $logger->info('GraphSeeder: Connecting stores → stores (lateral)');
            // Chain stores: Store 1 -> Store 2 -> Store 3 ...
            for ($i = 0; $i < count($stores) - 1; $i++) {
                Route::updateOrCreate([
                    'source_id' => $stores[$i]->id,
                    'target_id' => $stores[$i + 1]->id,
                    'transport_mode' => 'Truck',
                ], [
                    'cost' => 150, // cents
                    'transit_days' => 3,
                    'capacity' => 1000,
                    'is_active' => true,
                    'weather_vulnerability' => true,
                ]);
                $routeCounts['stores_to_stores']++;
            }

            // 5. Connect Vendors to Hubs (Air, Fast)
            $logger->info('GraphSeeder: Connecting vendors → hubs (air)');
            foreach ($vendors as $vendor) {
                foreach ($hubs as $hub) {
                    Route::updateOrCreate([
                        'source_id' => $vendor->id,
                        'target_id' => $hub->id,
                        'transport_mode' => 'Air',
                    ], [
                        'cost' => 500, // cents
                        'transit_days' => 1,
                        'capacity' => 1000,
                        'is_active' => true,
                        'weather_vulnerability' => true,
                    ]);
                    $routeCounts['vendors_to_hubs']++;
                }
            }

            // 6. Connect Hubs to Stores (Air, Fast)
            $logger->info('GraphSeeder: Connecting hubs → stores (air)');
            foreach ($hubs as $hub) {
                foreach ($stores as $store) {
                    Route::updateOrCreate([
                        'source_id' => $hub->id,
                        'target_id' => $store->id,
                        'transport_mode' => 'Air',
                    ], [
                        'cost' => 500, // cents
                        'transit_days' => 1,
                        'capacity' => 1000,
                        'is_active' => true,
                        'weather_vulnerability' => true,
                    ]);
                    $routeCounts['hubs_to_stores']++;
                }
            }

            $totalRoutes = array_sum($routeCounts);

            $logger->info('GraphSeeder: Logistics network creation completed', [
                'total_routes' => $totalRoutes,
                'route_breakdown' => $routeCounts,
            ]);
        } catch (\Exception $e) {
            $logger->error('GraphSeeder: Network creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
