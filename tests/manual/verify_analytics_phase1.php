<?php
/**
 * Manual Test: Verify Analytics Phase 1 (Migrations & Snapshot)
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Location;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\GameState;
use App\Events\TimeAdvanced;

$testRunId = 'test_' . Carbon::now()->format('Y_m_d_His');
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
    
    logInfo("=== Starting Analytics Phase 1 Verification: {$testRunId} ===");
    
    // === SETUP PHASE ===
    logInfo("Setting up test data...");
    
    $user = User::factory()->create();
    logInfo("Created User: {$user->id}");

    $location = Location::factory()->create();
    logInfo("Created Location: {$location->id}");

    // Test new unit_price column
    $product = Product::factory()->create([
        'unit_price' => 15.50
    ]);
    logInfo("Created Product: {$product->id} with unit_price: {$product->unit_price}");

    if ($product->unit_price != 15.50) {
        throw new Exception("Product unit_price mismatch. Expected 15.50, got {$product->unit_price}");
    }
    
    // Create Inventory
    $inventory = Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);
    logInfo("Created Inventory: 100 units");

    $gameState = GameState::factory()->create(['user_id' => $user->id]);

    // === EXECUTION PHASE ===
    logInfo("Dispatching TimeAdvanced event for Day 1...");
    
    // We need to ensure the listener runs. 
    // Since we are in a script, Laravel events are synchronous by default unless queued.
    // Ensure queue worker is not needed or we process it.
    // Listener is likely synchronous (not ShouldQueue).
    
    $event = new TimeAdvanced(1, $gameState);
    Event::dispatch($event);
    
    logInfo("Event dispatched.");

    // === VERIFICATION PHASE ===
    logInfo("Verifying inventory_history...");

    $history = DB::table('inventory_history')
        ->where('user_id', $user->id)
        ->where('day', 1)
        ->where('product_id', $product->id)
        ->first();

    if (!$history) {
        throw new Exception("No inventory history found for Day 1");
    }

    logInfo("Found History Record", (array)$history);

    if ($history->quantity != 100) {
        throw new Exception("History quantity mismatch. Expected 100, got {$history->quantity}");
    }

    // Verify Composite Unique Constraint (Try to insert duplicate manually)
    logInfo("Verifying unique constraint...");
    try {
        DB::table('inventory_history')->insert([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'product_id' => $product->id,
            'day' => 1,
            'quantity' => 200, // different quantity
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        throw new Exception("Constraint failed: Duplicate insert succeeded.");
    } catch (\Illuminate\Database\QueryException $e) {
        logInfo("Constraint verified: Duplicate insert failed as expected.");
    }

    logInfo("Tests completed successfully");
    
} catch (\Exception $e) {
    logError("Test failed", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo "[FAIL] " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Cleanup completed (Rollback)");
    logInfo("=== Test Run Finished ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}
