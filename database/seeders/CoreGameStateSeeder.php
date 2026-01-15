<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class CoreGameStateSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Main Store
        $location = Location::factory()->create([
            'name' => 'Moonshine Central',
            'max_storage' => 1000,
        ]);

        // 2. Create Vendors
        $beanVendor = Vendor::factory()->create(['name' => 'Bean Baron']);
        $dairyVendor = Vendor::factory()->create(['name' => 'Dairy King']);
        $suppliesVendor = Vendor::factory()->create(['name' => 'General Supplies Co']);

        // 3. Create Products
        $arabicaBeans = Product::factory()->create([
            'name' => 'Arabica Beans',
            'category' => 'Beans',
            'is_perishable' => false,
            'storage_cost' => 0.50,
        ]);

        $robustaBeans = Product::factory()->create([
            'name' => 'Robusta Beans',
            'category' => 'Beans',
            'is_perishable' => false,
            'storage_cost' => 0.40,
        ]);

        $wholeMilk = Product::factory()->create([
            'name' => 'Whole Milk',
            'category' => 'Milk',
            'is_perishable' => true,
            'storage_cost' => 1.00,
        ]);

        $oatMilk = Product::factory()->create([
            'name' => 'Oat Milk',
            'category' => 'Milk',
            'is_perishable' => true,
            'storage_cost' => 1.20,
        ]);

        $cups = Product::factory()->create([
            'name' => '12oz Cups',
            'category' => 'Cups',
            'is_perishable' => false,
            'storage_cost' => 0.10,
        ]);

        // 4. Attach Products to Vendors
        $beanVendor->products()->attach([$arabicaBeans->id, $robustaBeans->id]);
        $dairyVendor->products()->attach([$wholeMilk->id, $oatMilk->id]);
        $suppliesVendor->products()->attach([$cups->id]);

        // 5. Initialize Inventory
        Inventory::create([
            'location_id' => $location->id,
            'product_id' => $arabicaBeans->id,
            'quantity' => 50,
        ]);

        Inventory::create([
            'location_id' => $location->id,
            'product_id' => $wholeMilk->id,
            'quantity' => 20,
        ]);

        Inventory::factory()->create([
             'location_id' => $location->id,
             'product_id' => $cups->id,
             'quantity' => 500,
        ]);
    }
}