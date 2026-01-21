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
        // 1. Get data from config
        $productsConfig = config('game_data.products');
        $vendorsConfig = config('game_data.vendors');

        // 2. Create all products from config
        $products = [];
        foreach ($productsConfig as $productData) {
            $products[$productData['category']][] = Product::updateOrCreate(
                ['name' => $productData['name']],
                [
                    'category' => $productData['category'],
                    'is_perishable' => $productData['is_perishable'],
                    'storage_cost' => $productData['storage_cost'],
                ]
            );
        }

        // 3. Create vendors from config and attach products by category
        foreach ($vendorsConfig as $vendorData) {
            $vendor = Vendor::updateOrCreate(
                ['name' => $vendorData['name']],
                [
                    'reliability_score' => $vendorData['reliability_score'],
                    'metrics' => $vendorData['metrics'],
                ]
            );

            // Attach products that match the vendor's categories
            $productIds = [];
            foreach ($vendorData['categories'] as $category) {
                if (isset($products[$category])) {
                    foreach ($products[$category] as $product) {
                        $productIds[] = $product->id;
                    }
                }
            }
            $vendor->products()->syncWithoutDetaching($productIds);
        }

        // Note: Per-user inventory is seeded by InitializeNewGame action
        // Location creation and graph connections are handled by GraphSeeder
    }
}
