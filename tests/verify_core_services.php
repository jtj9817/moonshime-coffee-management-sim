<?php
/**
 * Verification Script: Phase 3 - Service Orchestration
 * Verifies InventoryManagementService and its integration with strategies and math service.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\InventoryManagementService;
use App\Services\InventoryMathService;
use App\Services\Strategies\JustInTimeStrategy;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

echo "--- Starting Verification: Phase 3 Service Orchestration ---\n";

// Snapshot
$initialInventoryCount = Inventory::count();
echo ">> Initial Inventory Count: $initialInventoryCount\n";

try {
    // 1. Setup
    echo ">> Setting up test data...\n";
    $location = Location::create([
        'name' => 'Test Location',
        'address' => '123 Test St',
        'max_storage' => 1000
    ]);
    
    $product = Product::create([
        'name' => 'Test Product',
        'category' => 'beans',
        'is_perishable' => false,
        'storage_cost' => 0.50
    ]);
    
    $inventory = Inventory::create([
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10
    ]);
    
    echo "   - Created Inventory ID: {$inventory->id} (Qty: 10)\n";

    // 2. Instantiate Service
    $math = new InventoryMathService();
    $strategy = new JustInTimeStrategy();
    $service = new InventoryManagementService($strategy, $math);
    
    echo ">> Testing InventoryManagementService...\n";

    // 3. Test Restock (JIT)
    // JIT: Target = DailyDemand * LeadTime
    // Params: D=5, LT=4 => Target=20. Current=10. Restock=10.
    echo "   [Test] Restocking...\n";
    $amount = $service->restock($inventory, null, ['daily_demand' => 5, 'lead_time' => 4]);
    
    $inventory->refresh();
    echo "   - Restocked Amount: {$amount} (Expected: 10)\n";
    echo "   - New Quantity: {$inventory->quantity} (Expected: 20)\n";
    
    if ($amount === 10 && $inventory->quantity === 20) {
        echo "   ✓ Restock Success\n";
    } else {
        echo "   ✗ Restock Failed\n";
    }

    // 4. Test Consume
    echo "   [Test] Consuming...\n";
    $service->consume($inventory, 5);
    $inventory->refresh();
    
    echo "   - New Quantity after consume(5): {$inventory->quantity} (Expected: 15)\n";
    
    if ($inventory->quantity === 15) {
        echo "   ✓ Consume Success\n";
    } else {
        echo "   ✗ Consume Failed\n";
    }

    // 5. Cleanup
    echo ">> Cleaning up...\n";
    $inventory->delete();
    $product->delete();
    $location->delete();
    
    $finalInventoryCount = Inventory::count();
    echo ">> Final Inventory Count: $finalInventoryCount\n";
    
    if ($finalInventoryCount === $initialInventoryCount) {
        echo "SUCCESS: State restored.\n";
    } else {
        echo "WARNING: State mismatch.\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "--- Verification Complete ---\n";