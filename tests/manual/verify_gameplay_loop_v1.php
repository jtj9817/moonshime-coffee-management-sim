<?php
/**
 * Manual Test: Gameplay Loop Verification (Phase 4)
 * Generated: 2026-01-17
 * Purpose: Verify deterministic Day 1, user decision persistence, inventory updates on delivery, 
 *          storage costs, order cancellation/refunds, and route capacity limits.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

use Illuminate\Support\Facades\{DB, Log, Auth};
use App\Models\{GameState, Location, Order, OrderItem, Product, Route, User, Inventory};
use App\Services\SimulationService;
use App\Events\OrderPlaced;
use App\States\Order\{Pending, Shipped, Delivered, Cancelled};
use Carbon\Carbon;

$testRunId = 'gameplay_loop_v1_' . Carbon::now()->format('Y_m_d_His');
$logFile = storage_path("logs/manual_tests/{$testRunId}.log");

if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

config(['logging.channels.manual_test' => [
    'driver' => 'single',
    'path' => $logFile,
    'level' => 'debug',
]]);

function logInfo($msg, $ctx = []) {
    Log::channel('manual_test')->info($msg, $ctx);
    echo "[INFO] {$msg}\n";
}

function logError($msg, $ctx = []) {
    Log::channel('manual_test')->error($msg, $ctx);
    echo "[ERROR] {$msg}\n";
}

function assertCondition($condition, $message) {
    if (!$condition) {
        logError("Assertion Failed: {$message}");
        throw new \Exception("Assertion Failed: {$message}");
    }
    logInfo("Assertion Passed: {$message}");
}

try {
    DB::beginTransaction();
    
    logInfo("=== Starting Manual Test: {$testRunId} ===");
    
    // === 1. SETUP PHASE ===
    logInfo("Setting up test world...");
    $user = User::factory()->create(['name' => 'Manual Test User']);
    Auth::login($user);
    
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
        'cash' => 100000
    ]);
    
    $vendorLoc = Location::factory()->create(['type' => 'vendor', 'name' => 'Supplier HQ']);
    $warehouse = Location::factory()->create(['type' => 'warehouse', 'name' => 'Main Warehouse']);
    $product = Product::factory()->create([
        'name' => 'Manual Test Beans', 
        'storage_cost' => 10,
        'is_perishable' => false
    ]);
    
    // Standard Route (2 days)
    $route = Route::factory()->create([
        'source_id' => $vendorLoc->id,
        'target_id' => $warehouse->id,
        'transport_mode' => 'Truck',
        'cost' => 1000,
        'transit_days' => 2,
        'capacity' => 500,
        'is_active' => true,
    ]);

    $service = new SimulationService($gameState);
    
    // === 2. EXECUTION PHASE ===
    
    // Scenario A: Day 1 Stability & Order Placement
    logInfo("Running Scenario A: Day 1 Stability & Order Placement");
    assertCondition($gameState->day === 1, "Game starts on Day 1");
    
    $order = Order::create([
        'user_id' => $user->id,
        'vendor_id' => \App\Models\Vendor::factory()->create()->id,
        'location_id' => $warehouse->id,
        'route_id' => $route->id,
        'total_cost' => 10000,
        'status' => 'draft',
    ]);
    
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'cost_per_unit' => 90,
    ]);
    
    $order->status->transitionTo(Pending::class);
    event(new OrderPlaced($order));
    
    assertCondition($gameState->fresh()->cash === 90000, "Cash deducted correctly ($10,000)");
    
    // Scenario B: Delivery Timing & Inventory Update
    logInfo("Running Scenario B: Delivery Timing & Inventory Update");
    $service->advanceTime(); // Day 1 -> 2
    assertCondition($gameState->fresh()->day === 2, "Advanced to Day 2");
    
    $order->status->transitionTo(Shipped::class);
    assertCondition($order->fresh()->delivery_day === 4, "Delivery day calculated correctly (2 + 2 = 4)");
    
    $service->advanceTime(); // Day 2 -> 3
    assertCondition($order->fresh()->status instanceof Shipped, "Order still shipped on Day 3");
    
    $service->advanceTime(); // Day 3 -> 4
    assertCondition($order->fresh()->status instanceof Delivered, "Order delivered on Day 4");
    
    $inventory = Inventory::where([
        'user_id' => $user->id,
        'location_id' => $warehouse->id,
        'product_id' => $product->id
    ])->first();
    
    assertCondition($inventory && $inventory->quantity === 100, "Inventory updated on delivery");
    
    // Scenario C: Storage Costs
    logInfo("Running Scenario C: Storage Costs");
    // On Day 4, inventory was added. advanceTime to 5 should deduct storage.
    // 100 items * $10 = $1000
    $preStorageCash = $gameState->fresh()->cash;
    $service->advanceTime(); // Day 4 -> 5
    $postStorageCash = $gameState->fresh()->cash;
    
    assertCondition($postStorageCash === ($preStorageCash - 1000), "Storage costs deducted on Day 5 ($1,000)");
    
    // Scenario D: Cancellation & Refunds
    logInfo("Running Scenario D: Cancellation & Refunds");
    $refundableOrder = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $order->vendor_id,
        'location_id' => $warehouse->id,
        'route_id' => $route->id,
        'total_cost' => 5000,
        'status' => 'draft',
    ]);
    $refundableOrder->status->transitionTo(Pending::class);
    event(new OrderPlaced($refundableOrder));
    
    $cashBeforeCancel = $gameState->fresh()->cash;
    $refundableOrder->status->transitionTo(Shipped::class);
    $refundableOrder->status->transitionTo(Cancelled::class);
    
    assertCondition($gameState->fresh()->cash === ($cashBeforeCancel + 5000), "Refund issued correctly after cancellation");
    
    // Scenario E: Capacity Limits
    logInfo("Running Scenario E: Capacity Limits");
    $massiveOrder = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $order->vendor_id,
        'location_id' => $warehouse->id,
        'route_id' => $route->id,
        'total_cost' => 1000,
        'status' => 'draft',
    ]);
    OrderItem::create([
        'order_id' => $massiveOrder->id,
        'product_id' => $product->id,
        'quantity' => 1000, // Exceeds route capacity of 500
        'cost_per_unit' => 1,
    ]);
    
    $massiveOrder->status->transitionTo(Pending::class);
    try {
        $massiveOrder->status->transitionTo(Shipped::class);
        logError("Failed to block over-capacity order");
    } catch (\RuntimeException $e) {
        logInfo("Caught expected capacity exception: " . $e->getMessage());
    }
    
    logInfo("Tests completed successfully");
    
} catch (\Exception $e) {
    logError("Test failed", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {
    // === 3. CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Database transaction rolled back (Cleanup complete)");
    logInfo("=== Test Run Finished ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}
