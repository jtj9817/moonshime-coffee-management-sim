<?php
/**
 * Manual Verification Script for Phase 3: Advanced Algorithms (BFS & Dijkstra)
 * Generated: 2026-01-16
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Route;
use App\Models\Location;
use App\Services\LogisticsService;

$testRunId = 'verify_phase3_' . Carbon::now()->format('Y_m_d_His');
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
    
    logInfo("=== Starting Phase 3 Verification: {$testRunId} ===");
    
    $service = new LogisticsService();

    // === SETUP ===
    logInfo("Phase 1: Setup Topology...");
    // A (Warehouse) -> B -> C (Target)
    // A -> D -> C (Target)
    
    $a = Location::factory()->create(['name' => 'A', 'type' => 'warehouse']);
    $b = Location::factory()->create(['name' => 'B', 'type' => 'hub']);
    $c = Location::factory()->create(['name' => 'C', 'type' => 'store']);
    $d = Location::factory()->create(['name' => 'D', 'type' => 'hub']);
    
    // Path 1 (Cost 20)
    $r1 = Route::factory()->create(['source_id' => $a->id, 'target_id' => $b->id, 'weights' => ['cost' => 10], 'is_active' => true]);
    $r2 = Route::factory()->create(['source_id' => $b->id, 'target_id' => $c->id, 'weights' => ['cost' => 10], 'is_active' => true]);
    
    // Path 2 (Cost 50)
    $r3 = Route::factory()->create(['source_id' => $a->id, 'target_id' => $d->id, 'weights' => ['cost' => 40], 'is_active' => true]);
    $r4 = Route::factory()->create(['source_id' => $d->id, 'target_id' => $c->id, 'weights' => ['cost' => 10], 'is_active' => true]);

    // === BFS VERIFICATION ===
    logInfo("Phase 2: BFS Reachability...");
    $reachable = $service->checkReachability($c);
    logInfo("Reachability (Expect True): " . ($reachable ? 'TRUE' : 'FALSE'));
    
    if (!$reachable) throw new Exception("BFS failed to find Warehouse A!");

    // Test Isolation
    $r1->update(['is_active' => false]);
    $r3->update(['is_active' => false]);
    // Now A is disconnected
    $reachable = $service->checkReachability($c);
    logInfo("Reachability (Expect False): " . ($reachable ? 'TRUE' : 'FALSE'));
    if ($reachable) throw new Exception("BFS found path when none exists!");
    
    // Restore
    $r1->update(['is_active' => true]);
    $r3->update(['is_active' => true]);

    // === DIJKSTRA VERIFICATION ===
    logInfo("Phase 3: Dijkstra Pathfinding...");
    
    $path = $service->findBestRoute($a, $c);
    logInfo("Path Found Count: " . $path->count());
    
    $totalCost = 0;
    foreach ($path as $route) {
        $totalCost += $service->calculateCost($route);
        logInfo(" - Route: {$route->source->name} -> {$route->target->name} (Cost: {$service->calculateCost($route)})");
    }
    logInfo("Total Cost: {$totalCost}");
    
    if ($totalCost !== 20) throw new Exception("Dijkstra failed! Expected Cost 20, got {$totalCost}");
    
    // Test Blockage
    logInfo("Blocking Path 1 (A->B)...");
    $r1->update(['is_active' => false]);
    
    $path = $service->findBestRoute($a, $c);
    
    $totalCost = 0;
    foreach ($path as $route) {
        $totalCost += $service->calculateCost($route);
    }
    logInfo("New Total Cost (Expect 50): {$totalCost}");
    
    if ($totalCost !== 50) throw new Exception("Dijkstra failed rerouting! Expected Cost 50, got {$totalCost}");

    logInfo("=== Verification Successful ===");
    
} catch (\Exception $e) {
    logError("Verification Failed", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} finally {
    DB::rollBack();
    logInfo("Database Rolled Back.");
    echo "\n✓ Full logs at: {$logFile}\n";
}

