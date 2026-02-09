<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Seeds global world data: vendors, products, and one main location.
 * Per-user inventory is handled by InitializeNewGame action.
 */
class CoreGameStateSeeder extends Seeder
{
    public function run(): void
    {
        $logger = Log::channel('game-initialization');
        $logger->info('CoreGameStateSeeder: Starting seeding');

        try {
            // 1. Validate configuration exists and has required structure
            $productsConfig = config('game_data.products');
            $vendorsConfig = config('game_data.vendors');

            if (empty($productsConfig) || ! is_array($productsConfig)) {
                throw new RuntimeException('Config game_data.products is missing or empty');
            }

            if (empty($vendorsConfig) || ! is_array($vendorsConfig)) {
                throw new RuntimeException('Config game_data.vendors is missing or empty');
            }

            $logger->info('CoreGameStateSeeder: Configuration validated', [
                'products_count' => count($productsConfig),
                'vendors_count' => count($vendorsConfig),
            ]);

            // 2. Create all products from config
            $products = [];
            $productCount = 0;
            $categoryCounts = [];

            foreach ($productsConfig as $productData) {
                try {
                    $product = Product::updateOrCreate(
                        ['name' => $productData['name']],
                        [
                            'category' => $productData['category'],
                            'is_perishable' => $productData['is_perishable'],
                            'storage_cost' => $productData['storage_cost'],
                        ]
                    );

                    $products[$productData['category']][] = $product;
                    $productCount++;
                    $categoryCounts[$productData['category']] = ($categoryCounts[$productData['category']] ?? 0) + 1;
                } catch (\Exception $e) {
                    $logger->error('CoreGameStateSeeder: Failed to create product', [
                        'product_name' => $productData['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $logger->info('CoreGameStateSeeder: Created products', [
                'total_products' => $productCount,
                'categories' => array_keys($categoryCounts),
                'category_counts' => $categoryCounts,
            ]);

            // 3. Create vendors from config and attach products by category
            $vendorCount = 0;
            $categoryMismatchWarnings = [];

            foreach ($vendorsConfig as $vendorData) {
                try {
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
                        } else {
                            // Log category mismatch warning
                            $categoryMismatchWarnings[] = [
                                'vendor' => $vendorData['name'],
                                'category' => $category,
                            ];
                        }
                    }

                    $vendor->products()->syncWithoutDetaching($productIds);
                    $vendorCount++;

                    $logger->info('CoreGameStateSeeder: Created vendor', [
                        'vendor_name' => $vendorData['name'],
                        'categories' => $vendorData['categories'],
                        'products_attached' => count($productIds),
                    ]);
                } catch (\Exception $e) {
                    $logger->error('CoreGameStateSeeder: Failed to create vendor', [
                        'vendor_name' => $vendorData['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            // Log warnings for category mismatches
            foreach ($categoryMismatchWarnings as $warning) {
                $logger->warning('CoreGameStateSeeder: Vendor category matched 0 products', [
                    'vendor' => $warning['vendor'],
                    'category' => $warning['category'],
                ]);
            }

            $logger->info('CoreGameStateSeeder: Seeding completed successfully', [
                'total_products' => $productCount,
                'total_vendors' => $vendorCount,
                'category_mismatches' => count($categoryMismatchWarnings),
            ]);
        } catch (\Exception $e) {
            $logger->error('CoreGameStateSeeder: Seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
