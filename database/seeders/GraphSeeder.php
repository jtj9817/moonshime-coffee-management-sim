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
        
        // Create Main Store and include it in the stores list for connectivity
        $mainStore = Location::factory()->create([
            'name' => 'Moonshine Central',
            'type' => 'store',
            'max_storage' => 1000,
        ]);
        
        $stores->push($mainStore);

        // 2. Connect Vendors to Warehouses (Truck, Cheap, Reliable)
        foreach ($vendors as $vendor) {
            foreach ($warehouses as $warehouse) {
                Route::factory()->create([
                    'source_id' => $vendor->id,
                    'target_id' => $warehouse->id,
                    'transport_mode' => 'Truck',
                    'cost' => 0.50,
                    'transit_days' => 2,
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
                    'cost' => 1.00,
                    'transit_days' => 1,
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
                'cost' => 1.50,
                'transit_days' => 3,
                'is_active' => true,
            ]);
        }

        // 5. Connect Vendors to Hub (Air, Fast)
        foreach ($vendors as $vendor) {
            Route::factory()->create([
                'source_id' => $vendor->id,
                'target_id' => $hub->id,
                'transport_mode' => 'Air',
                'cost' => 5.00,
                'transit_days' => 1,
                'is_active' => true,
            ]);
        }

        // 6. Connect Hub to Stores (Air, Fast)
        foreach ($stores as $store) {
            Route::factory()->create([
                'source_id' => $hub->id,
                'target_id' => $store->id,
                'transport_mode' => 'Air',
                'cost' => 5.00,
                'transit_days' => 1,
                'is_active' => true,
            ]);
        }
    }
}
