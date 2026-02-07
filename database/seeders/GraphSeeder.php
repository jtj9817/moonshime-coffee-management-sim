<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Route;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class GraphSeeder extends Seeder
{
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
                    'action' => 'Proceeding with creation (will use factories)',
                ]);
            }

            // 1. Create Nodes
            $logger->info('GraphSeeder: Creating location nodes');

            $vendors = Location::factory()->count(3)->create(['type' => 'vendor']);
            $warehouses = Location::factory()->count(2)->create(['type' => 'warehouse']);
            // Create 3 hubs for multi-path diversity (ensures ≥3 distinct paths)
            $hubs = Location::factory()->count(3)->create(['type' => 'hub']);

            // Create Main Store FIRST so it gets included in route loops
            $mainStore = Location::factory()->create([
                'name' => 'Moonshine Central',
                'type' => 'store',
                'max_storage' => 1000,
            ]);

            $stores = Location::factory()->count(5)->create(['type' => 'store']);
            $stores->push($mainStore);

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
                    Route::factory()->create([
                        'source_id' => $vendor->id,
                        'target_id' => $warehouse->id,
                        'transport_mode' => 'Truck',
                        'cost' => 50, // cents
                        'transit_days' => 2,
                        'is_active' => true,
                    ]);
                    $routeCounts['vendors_to_warehouses']++;
                }
            }

            // 3. Connect Warehouses to Stores (Truck, Standard)
            $logger->info('GraphSeeder: Connecting warehouses → stores');
            foreach ($warehouses as $warehouse) {
                foreach ($stores as $store) {
                    Route::factory()->create([
                        'source_id' => $warehouse->id,
                        'target_id' => $store->id,
                        'transport_mode' => 'Truck',
                        'cost' => 100, // cents
                        'transit_days' => 1,
                        'is_active' => true,
                    ]);
                    $routeCounts['warehouses_to_stores']++;
                }
            }

            // 4. Connect Stores to Stores (Lateral, Emergency)
            $logger->info('GraphSeeder: Connecting stores → stores (lateral)');
            // Chain stores: Store 1 -> Store 2 -> Store 3 ...
            for ($i = 0; $i < count($stores) - 1; $i++) {
                Route::factory()->create([
                    'source_id' => $stores[$i]->id,
                    'target_id' => $stores[$i+1]->id,
                    'transport_mode' => 'Truck',
                    'cost' => 150, // cents
                    'transit_days' => 3,
                    'is_active' => true,
                ]);
                $routeCounts['stores_to_stores']++;
            }

            // 5. Connect Vendors to Hubs (Air, Fast)
            $logger->info('GraphSeeder: Connecting vendors → hubs (air)');
            foreach ($vendors as $vendor) {
                foreach ($hubs as $hub) {
                    Route::factory()->create([
                        'source_id' => $vendor->id,
                        'target_id' => $hub->id,
                        'transport_mode' => 'Air',
                        'cost' => 500, // cents
                        'transit_days' => 1,
                        'is_active' => true,
                    ]);
                    $routeCounts['vendors_to_hubs']++;
                }
            }

            // 6. Connect Hubs to Stores (Air, Fast)
            $logger->info('GraphSeeder: Connecting hubs → stores (air)');
            foreach ($hubs as $hub) {
                foreach ($stores as $store) {
                    Route::factory()->create([
                        'source_id' => $hub->id,
                        'target_id' => $store->id,
                        'transport_mode' => 'Air',
                        'cost' => 500, // cents
                        'transit_days' => 1,
                        'is_active' => true,
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
