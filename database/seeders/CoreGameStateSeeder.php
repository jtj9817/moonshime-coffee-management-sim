<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Seeds global world data: vendors, products, and one main location.
 * Per-user inventory is handled by InitializeNewGame action.
 */
class CoreGameStateSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Main Store is now handled/connected in GraphSeeder
        // We skip creating it here to avoid isolation.

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

        // Note: Per-user inventory is now seeded by InitializeNewGame action
    }
}
