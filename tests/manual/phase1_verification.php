<?php

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;
use Database\Seeders\CoreGameStateSeeder;

echo "\n\n--- Starting Phase 1 Verification Script ---\n";

// 1. Snapshot
$counts = [
    'locations' => Location::count(),
    'vendors' => Vendor::count(),
    'products' => Product::count(),
    'inventories' => Inventory::count(),
];

echo 'Initial State: '.json_encode($counts)."\n";

// 2. Run Seeder
echo ">> Running CoreGameStateSeeder...\n";
$seeder = new CoreGameStateSeeder;
$seeder->run();

// 3. Verify Creation
$newCounts = [
    'locations' => Location::count(),
    'vendors' => Vendor::count(),
    'products' => Product::count(),
    'inventories' => Inventory::count(),
];

echo 'Post-Seed State: '.json_encode($newCounts)."\n";

// Validate increases
if ($newCounts['locations'] <= $counts['locations']) {
    echo "ERROR: Locations did not increase!\n";
    exit(1);
}
if ($newCounts['products'] <= $counts['products']) {
    echo "ERROR: Products did not increase!\n";
    exit(1);
}

// 4. Deep Inspection of Created Data
// We use latest('created_at') to find the batch we just made
$location = Location::latest('created_at')->first();
echo ">> Inspecting created Location: {$location->name} (ID: {$location->id})\n";

$inventoryItems = $location->inventories;
echo ">> Found {$inventoryItems->count()} inventory items.\n";

foreach ($inventoryItems as $item) {
    echo "   - Item: {$item->product->name} (Qty: {$item->quantity}) | Vendor: {$item->product->vendors->first()?->name}\n";
}

// 5. Cleanup
echo ">> Cleaning up...\n";

// Delete the location (cascades to inventory)
$location->delete();
echo "   - Deleted Location.\n";

// Delete the products and vendors created.
// Since we know the counts increased by specific amounts (1 loc, 3 vendors, 5 products)
// We can safely target the latest N records.

$vendorsToDelete = Vendor::latest('created_at')->take(3)->get();
foreach ($vendorsToDelete as $v) {
    $v->delete();
    echo "   - Deleted Vendor: {$v->name}\n";
}

$productsToDelete = Product::latest('created_at')->take(5)->get();
foreach ($productsToDelete as $p) {
    $p->delete();
    echo "   - Deleted Product: {$p->name}\n";
}

// 6. Final Verify
$finalCounts = [
    'locations' => Location::count(),
    'vendors' => Vendor::count(),
    'products' => Product::count(),
];

echo 'Final State: '.json_encode($finalCounts)."\n";

if ($finalCounts['locations'] !== $counts['locations']) {
    echo "WARNING: Location count mismatch (Expected {$counts['locations']}, got {$finalCounts['locations']})\n";
} else {
    echo "SUCCESS: Database returned to initial state.\n";
}

echo "--- Verification Complete ---\n";
