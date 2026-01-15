<?php

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\InventoryManagementService;
use App\Services\Strategies\RestockStrategyInterface;
use App\Services\Strategies\JustInTimeStrategy;
use Illuminate\Support\Facades\Log;

$logFile = storage_path('logs/phase2_verification.log');
file_put_contents($logFile, "--- Phase 2 Verification Log ---\n");

function logToConsole($message, $file) {
    echo $message . "\n";
    file_put_contents($file, $message . "\n", FILE_APPEND);
}

logToConsole("Starting Phase 2 Verification...", $logFile);

try {
    // 1. Setup Data
    logToConsole(">>> Setting up test data...", $logFile);
    
    $location = Location::factory()->create(['name' => 'Phase2 Test Loc']);
    $product = Product::factory()->create(['name' => 'Phase2 Test Product']);
    $inventory = Inventory::factory()->create([
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10
    ]);

    logToConsole("   Created Inventory ID: {$inventory->id} | Initial Qty: 10", $logFile);

    // 2. Resolve Service
    logToConsole(">>> Resolving InventoryManagementService...", $logFile);
    $service = app(InventoryManagementService::class);
    
    if ($service) {
        logToConsole("   Service resolved successfully.", $logFile);
    } else {
        throw new Exception("Failed to resolve InventoryManagementService");
    }

    // 3. Test Restock (Explicit)
    logToConsole(">>> Testing Restock (Explicit +5)...", $logFile);
    $added = $service->restock($inventory, 5);
    $inventory->refresh();
    
    logToConsole("   Added: $added | New Qty: {$inventory->quantity}", $logFile);
    
    if ($inventory->quantity === 15) {
        logToConsole("   [PASS] Restock successful.", $logFile);
    } else {
        logToConsole("   [FAIL] Restock mismatch (Expected 15).", $logFile);
    }

    // 4. Test Consume
    logToConsole(">>> Testing Consume (-3)...", $logFile);
    $service->consume($inventory, 3);
    $inventory->refresh();
    
    logToConsole("   New Qty: {$inventory->quantity}", $logFile);
    
    if ($inventory->quantity === 12) {
        logToConsole("   [PASS] Consume successful.", $logFile);
    } else {
        logToConsole("   [FAIL] Consume mismatch (Expected 12).", $logFile);
    }

    // 5. Test Waste
    logToConsole(">>> Testing Waste (-2)...", $logFile);
    $service->waste($inventory, 2, 'Spoiled');
    $inventory->refresh();
    
    logToConsole("   New Qty: {$inventory->quantity}", $logFile);
    
    if ($inventory->quantity === 10) {
        logToConsole("   [PASS] Waste successful.", $logFile);
    } else {
        logToConsole("   [FAIL] Waste mismatch (Expected 10).", $logFile);
    }

    // 6. Test Strategy Resolution
    logToConsole(">>> Testing Strategy Resolution...", $logFile);
    $strategy = app(RestockStrategyInterface::class);
    logToConsole("   Default Strategy: " . get_class($strategy), $logFile);
    
    if ($strategy instanceof JustInTimeStrategy) {
        logToConsole("   [PASS] Default strategy is JIT.", $logFile);
    } else {
        logToConsole("   [FAIL] Unexpected default strategy.", $logFile);
    }
    
    // Test JIT Calculation via Service (implicit restock)
    // JIT Mock logic: Target = 10 * 3 = 30. Current = 10. Order = 20.
    logToConsole(">>> Testing Strategy-based Restock (JIT)...", $logFile);
    logToConsole("   Current Qty: 10. Target (hardcoded in JIT): 30.", $logFile);
    
    $addedJit = $service->restock($inventory);
    $inventory->refresh();
    
    logToConsole("   Added: $addedJit | New Qty: {$inventory->quantity}", $logFile);
    
    if ($inventory->quantity === 30) {
        logToConsole("   [PASS] JIT Restock successful.", $logFile);
    } else {
        logToConsole("   [FAIL] JIT Restock mismatch (Expected 30).", $logFile);
    }

} catch (Exception $e) {
    logToConsole("ERROR: " . $e->getMessage(), $logFile);
} finally {
    // 7. Cleanup
    logToConsole(">>> Cleaning up...", $logFile);
    
    if (isset($inventory)) {
        $inventory->delete();
        logToConsole("   Deleted Inventory.", $logFile);
    }
    if (isset($product)) {
        $product->delete();
        logToConsole("   Deleted Product.", $logFile);
    }
    if (isset($location)) {
        $location->delete();
        logToConsole("   Deleted Location.", $logFile);
    }
    
    logToConsole("--- Verification Complete ---", $logFile);
}