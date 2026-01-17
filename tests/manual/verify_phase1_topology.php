<?php
/**
 * Manual Verification Script for Phase 1: Physical Graph Foundation
 * Generated: 2026-01-16
 * Purpose: Verify GraphSeeder, Route Model, and LogisticsService
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
use Carbon\Carbon;
use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Database\Seeders\GraphSeeder;

$testRunId = 'verify_phase1_' . Carbon::now()->format('Y_m_d_His');
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
    
    logInfo("=== Starting Phase 1 Verification: {$testRunId} ===");
    
    // === SETUP PHASE ===
    logInfo("Phase 1: Running GraphSeeder...");
    $seeder = new GraphSeeder();
    $seeder->run();
    logInfo("GraphSeeder completed.");

    // === INSPECTION PHASE ===
    logInfo("Phase 2: Inspecting Database State...");

    // 1. Verify Node Counts
    $vendorCount = Location::where('type', 'vendor')->count();
    $warehouseCount = Location::where('type', 'warehouse')->count();
    $storeCount = Location::where('type', 'store')->count();
    $hubCount = Location::where('type', 'hub')->count();
    $routeCount = Route::count();

    logInfo("Counts:", [
        'vendors' => $vendorCount,
        'warehouses' => $warehouseCount,
        'stores' => $storeCount,
        'hub' => $hubCount,
        'routes' => $routeCount
    ]);

    if ($vendorCount !== 3 || $warehouseCount !== 2 || $storeCount !== 5 || $hubCount !== 1) {
        throw new Exception("Location counts mismatch!");
    }
    if ($routeCount !== 28) {
        throw new Exception("Route count mismatch! Expected 28, got {$routeCount}");
    }
    logInfo("Topology Counts Verified.");

    // 2. Verify LogisticsService
    logInfo("Phase 3: Verifying LogisticsService...");
    $service = new LogisticsService();

    // Test Vendor -> Hub (Air Route)
    $vendor = Location::where('type', 'vendor')->first();
    $hub = Location::where('type', 'hub')->first();

    logInfo("Testing Route: Vendor({$vendor->id}) -> Hub({$hub->id})");
    $routes = $service->getValidRoutes($vendor, $hub);
    
    if ($routes->isEmpty()) {
        throw new Exception("No route found between Vendor and Hub!");
    }

    $route = $routes->first();
    logInfo("Route Found:", ['transport' => $route->transport_mode, 'weights' => $route->weights]);

    if ($route->transport_mode !== 'Air') {
        throw new Exception("Expected Air transport, got {$route->transport_mode}");
    }

    // Test Cost Calculation
    $cost = $service->calculateCost($route);
    logInfo("Calculated Cost: {$cost}");
    
    if ($cost != 500) { // Default air cost in seeder
        throw new Exception("Cost calculation mismatch! Expected 500, got {$cost}");
    }

    logInfo("LogisticsService Verified.");
    logInfo("=== Verification Successful ===");
    
} catch (\Exception $e) {
    logError("Verification Failed", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Database Rolled Back.");
    logInfo("=== Test Run Finished ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}

