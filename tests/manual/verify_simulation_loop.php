<?php
/**
 * Manual Verification: Phase 4 Simulation Loop Integration
 * Generated: 2026-01-16
 * Purpose: Verify Event, Physics, and Analysis ticks in a unified simulation advance.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use App\Models\Alert;
use App\Services\SimulationService;
use App\States\Transfer\InTransit;
use App\States\Transfer\Completed;
use Illuminate\Support\Facades\{DB, Log};
use Carbon\Carbon;

$testRunId = 'verify_sim_loop_' . Carbon::now()->format('Y_m_d_His');
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
    
    logInfo("=== Starting Phase 4 Verification: {$testRunId} ===");
    
    // === SETUP PHASE ===
    logInfo("Setting up test environment (Day 1)...");
    
    $warehouse = Location::factory()->create(['type' => 'warehouse', 'name' => 'Main Depot']);
    $store = Location::factory()->create(['type' => 'store', 'name' => 'Isolated Cafe']);
    $product = Product::factory()->create(['name' => 'Specialty Beans']);
    
    $route = Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $store->id,
        'weather_vulnerability' => true,
        'is_active' => true,
    ]);

    $gameState = GameState::factory()->create(['day' => 1]);
    $service = new SimulationService($gameState);

    // Setup Spike (Start Day 2, End Day 4)
    $spike = SpikeEvent::factory()->create([
        'type' => 'blizzard',
        'affected_route_id' => $route->id,
        'starts_at_day' => 2,
        'ends_at_day' => 4,
        'is_active' => false,
    ]);

    // Setup Transfer (Arrival Day 2)
    $transfer = Transfer::factory()->create([
        'source_location_id' => $warehouse->id,
        'target_location_id' => $store->id,
        'product_id' => $product->id,
        'delivery_day' => 2,
    ]);
    $transfer->status->transitionTo(InTransit::class);

    // Setup Low Stock
    Inventory::factory()->create([
        'location_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);
    
    logInfo("Setup complete. Store is currently reachable with low stock.");

    // === EXECUTION PHASE: DAY 2 ===
    logInfo("Advancing time to Day 2...");
    $service->advanceTime();
    
    logInfo("Inspecting Day 2 results:");
    
    // Check Event Tick (Spike Activation)
    $spike->refresh();
    $route->refresh();
    logInfo("Spike Active: " . ($spike->is_active ? 'YES' : 'NO'));
    logInfo("Route Active: " . ($route->is_active ? 'YES' : 'NO'));
    
    if (!$spike->is_active || $route->is_active) {
        logError("Event Tick failed: Route should be blocked by active blizzard.");
    } else {
        logInfo("Event Tick PASSED.");
    }

    // Check Physics Tick (Transfer Arrival)
    $transfer->refresh();
    logInfo("Transfer Status: " . $transfer->status);
    if (!($transfer->status instanceof Completed)) {
        logError("Physics Tick failed: Transfer should be completed.");
    } else {
        logInfo("Physics Tick PASSED.");
    }

    // Check Analysis Tick (Alert Generation)
    $alert = Alert::where('location_id', $store->id)->where('type', 'isolation')->first();
    if (!$alert) {
        logError("Analysis Tick failed: No isolation alert found for store.");
    } else {
        logInfo("Analysis Tick PASSED. Alert: {$alert->message}");
    }

    // === EXECUTION PHASE: DAY 3 & 4 ===
    logInfo("Advancing time through Day 3 to Day 4...");
    $service->advanceTime(); // Day 3
    $service->advanceTime(); // Day 4
    
    logInfo("Inspecting Day 4 results (Spike Expiration):");
    
    $spike->refresh();
    $route->refresh();
    logInfo("Spike Active: " . ($spike->is_active ? 'YES' : 'NO'));
    logInfo("Route Active: " . ($route->is_active ? 'YES' : 'NO'));

    if ($spike->is_active || !$route->is_active) {
        logError("Spike Cleanup failed: Route should be restored after blizzard ends.");
    } else {
        logInfo("Spike Cleanup PASSED.");
    }
    
    logInfo("Manual Verification sequence finished successfully.");
    
} catch (\Exception $e) {
    logError("Verification failed", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Cleanup: Database transaction rolled back.");
    logInfo("=== Verification Run Finished ===");
    echo "\n✓ Manual verification finished. See logs for details: {$logFile}\n";
}
