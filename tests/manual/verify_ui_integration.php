<?php

/**
 * Manual Test Script for UI Integration (Logistics)
 * Generated: 2026-01-16
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$testRunId = 'test_ui_logistics_'.Carbon::now()->format('Y_m_d_His');
$logFile = storage_path("logs/manual_tests/{$testRunId}.log");

if (! is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

config(['logging.channels.manual_test' => [
    'driver' => 'single',
    'path' => $logFile,
    'level' => 'debug',
]]);

function logInfo($message, $context = [])
{
    Log::channel('manual_test')->info($message, $context);
    echo "[INFO] {$message}\n";
}

function logError($message, $context = [])
{
    Log::channel('manual_test')->error($message, $context);
    echo "[ERROR] {$message}\n";
}

try {
    DB::beginTransaction();
    logInfo("=== Test Run Started: {$testRunId} ===");

    logInfo('Phase 1: Data Setup');

    $locA = Location::factory()->create(['name' => 'Store A', 'type' => 'store']);
    $locB = Location::factory()->create(['name' => 'Warehouse B', 'type' => 'warehouse']);
    $locC = Location::factory()->create(['name' => 'Hub C', 'type' => 'hub']);

    logInfo('Created locations', ['A' => $locA->id, 'B' => $locB->id, 'C' => $locC->id]);

    // Create a path: A -> C -> B
    Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locC->id,
        'is_active' => true,
        'weights' => ['cost' => 5],
    ]);

    Route::factory()->create([
        'source_id' => $locC->id,
        'target_id' => $locB->id,
        'is_active' => true,
        'weights' => ['cost' => 5],
    ]);

    // Create a direct path: A -> B (but inactive)
    Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'is_active' => false,
        'weights' => ['cost' => 2],
    ]);

    logInfo('Routes created');

    logInfo('Phase 2: Test Execution');

    $service = app(LogisticsService::class);

    logInfo('Testing getLogisticsHealth()');
    $health = $service->getLogisticsHealth();
    logInfo("Logistics Health: {$health}%");

    logInfo('Testing findBestRoute(Store A -> Warehouse B)');
    $path = $service->findBestRoute($locA, $locB);

    if ($path && $path->count() === 2) {
        logInfo('Test passed: Optimal path found with 2 segments (via Hub C)');
        $totalCost = $path->sum(fn ($r) => $service->calculateCost($r));
        logInfo("Total Cost: {$totalCost}");
    } else {
        logError('Test failed: Path not found or incorrect length', ['count' => $path ? $path->count() : 0]);
    }

    logInfo('Testing Logistics Health calculation logic');
    $total = Route::count();
    $active = Route::where('is_active', true)->count();
    $expectedHealth = ($active / $total) * 100;

    if (abs($health - $expectedHealth) < 0.01) {
        logInfo('Test passed: Health calculation matches database state');
    } else {
        logError('Test failed: Health mismatch', ['actual' => $health, 'expected' => $expectedHealth]);
    }

    logInfo('Phase 3: Cleanup (Automatic via Rollback)');
    DB::rollBack();
    logInfo('Database rolled back');

} catch (\Exception $e) {
    DB::rollBack();
    logError('Test failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
} finally {
    logInfo("=== Test Run Completed: {$testRunId} ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}
