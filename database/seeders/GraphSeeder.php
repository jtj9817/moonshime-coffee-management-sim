<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Route;
use Illuminate\Database\Seeder;

class GraphSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Nodes
        $vendors = Location::factory()->count(3)->create(['type' => 'vendor']);
        $warehouses = Location::factory()->count(2)->create(['type' => 'warehouse']);
        $stores = Location::factory()->count(5)->create(['type' => 'store']);
        $hub = Location::factory()->create(['type' => 'hub', 'name' => 'Central Transit Hub']);

        // 2. Connect Vendors to Warehouses (Truck, Cheap, Reliable)
        foreach ($vendors as $vendor) {
            foreach ($warehouses as $warehouse) {
                Route::factory()->create([
                    'source_id' => $vendor->id,
                    'target_id' => $warehouse->id,
                    'transport_mode' => 'Truck',
                    'weights' => ['cost' => 50, 'time' => 2],
                    'is_active' => true,
                ]);
            }
        }

        // 3. Connect Warehouses to Stores (Truck, Standard)
        foreach ($warehouses as $warehouse) {
            foreach ($stores as $store) {
                Route::factory()->create([
                    'source_id' => $warehouse->id,
                    'target_id' => $store->id,
                    'transport_mode' => 'Truck',
                    'weights' => ['cost' => 100, 'time' => 1], // Shorter time, higher cost per mile maybe?
                    'is_active' => true,
                ]);
            }
        }

        // 4. Connect Stores to Stores (Lateral, Emergency)
        // Chain stores: Store 1 -> Store 2 -> Store 3 ...
        for ($i = 0; $i < count($stores) - 1; $i++) {
            Route::factory()->create([
                'source_id' => $stores[$i]->id,
                'target_id' => $stores[$i+1]->id,
                'transport_mode' => 'Truck',
                'weights' => ['cost' => 150, 'time' => 3], // Lateral is slower/costlier
                'is_active' => true,
            ]);
        }

        // 5. Connect Vendors to Hub (Air, Fast)
        foreach ($vendors as $vendor) {
            Route::factory()->create([
                'source_id' => $vendor->id,
                'target_id' => $hub->id,
                'transport_mode' => 'Air',
                'weights' => ['cost' => 500, 'time' => 0.5],
                'is_active' => true,
            ]);
        }

        // 6. Connect Hub to Stores (Air, Fast)
        foreach ($stores as $store) {
            Route::factory()->create([
                'source_id' => $hub->id,
                'target_id' => $store->id,
                'transport_mode' => 'Air',
                'weights' => ['cost' => 500, 'time' => 0.5],
                'is_active' => true,
            ]);
        }
    }
}