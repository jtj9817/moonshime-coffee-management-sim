<?php
/**
 * Manual Test: Full Game Integration
 * Purpose: Verify SimulationService loop including deliveries, decay, and spikes.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

use Illuminate\Support\Facades\{DB, Log, Event};
use Carbon\Carbon;
use App\Models\User;
use App\Models\GameState;
use App\Models\Location;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\SpikeEvent;
use App\Services\SimulationService;
use App\States\Order\Shipped;
use App\States\Order\Delivered;

$testRunId = 'game_integration_' . Carbon::now()->format('Y_m_d_His');
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

try {
    DB::beginTransaction();
    
    logInfo("=== Starting Game Integration Test: {$testRunId} ===");
    
    // === SETUP PHASE ===
    logInfo("Setting up test data...");
    
    $user = User::factory()->create(['email' => 'test_simulation@example.com']);
    Auth::login($user);
    
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
        'cash' => 10000,
    ]);
    
    $location = Location::factory()->create(['name' => 'Main Warehouse']);
    $vendor = Vendor::factory()->create(['name' => 'Premium Coffee Co']);
    
    $perishableProduct = Product::factory()->create([
        'name' => 'Fresh Milk',
        'is_perishable' => true,
    ]);
    
    $stableProduct = Product::factory()->create([
        'name' => 'Coffee Beans',
        'is_perishable' => false,
    ]);
    
    $milkInventory = Inventory::factory()->create([
        'location_id' => $location->id,
        'product_id' => $perishableProduct->id,
        'quantity' => 100,
    ]);
    
    $beansInventory = Inventory::factory()->create([
        'location_id' => $location->id,
        'product_id' => $stableProduct->id,
        'quantity' => 100,
    ]);
    
    $order = Order::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => Shipped::class,
        'delivery_day' => 2,
    ]);
    
    logInfo("Setup complete. Initial state: Day 1, Milk: 100, Beans: 100, Order: Shipped (Delivery Day 2)");
    
    // === EXECUTION PHASE ===
    logInfo("Advancing time to Day 2...");
    
    $simulationService = app(SimulationService::class);
    $simulationService->advanceTime();
    
    logInfo("Time advanced. Verifying outcomes...");
    
    $gameState->refresh();
    logInfo("New Game Day: {$gameState->day}");
    if ($gameState->day !== 2) {
        throw new Exception("Day increment failed!");
    }
    
    $milkInventory->refresh();
    logInfo("Milk quantity after decay: {$milkInventory->quantity}");
    if ($milkInventory->quantity >= 100) {
        throw new Exception("Milk decay failed!");
    }
    
    $beansInventory->refresh();
    logInfo("Beans quantity (should be stable): {$beansInventory->quantity}");
    if ($beansInventory->quantity !== 100) {
        throw new Exception("Stable product decayed erroneously!");
    }
    
    $order->refresh();
    logInfo("Order status: " . (string)$order->status);
    if (!($order->status instanceof Delivered)) {
        throw new Exception("Order delivery processing failed!");
    }
    
    $spikes = SpikeEvent::where('starts_at_day', 3)->get();
    logInfo("Spikes generated for tomorrow: " . $spikes->count());
    foreach ($spikes as $spike) {
        logInfo("- Spike: {$spike->type} (Magnitude: {$spike->magnitude})");
    }
    
    logInfo("Integration test completed successfully!");
    
} catch (\Exception $e) {
    logError("Test failed", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Database transaction rolled back.");
    logInfo("=== Test Run Finished ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}
