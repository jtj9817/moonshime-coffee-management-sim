<?php

/**
 * Manual Test: Architectural Cleanup & Standardisation Verification
 * Generated: 2026-01-17
 * Purpose: Verify KPI deduplication, Pathfinding is_premium flag, and TDD updates.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Services\LogisticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Log;

$testRunId = 'standardization_verify_'.Carbon::now()->format('Y_m_d_His');
$logFile = storage_path("logs/manual_tests/{$testRunId}.log");

if (! is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

config(['logging.channels.manual_test' => [
    'driver' => 'single',
    'path' => $logFile,
    'level' => 'debug',
]]);

function logInfo($msg, $ctx = [])
{
    Log::channel('manual_test')->info($msg, $ctx);
    echo "[INFO] {$msg}\n";
}

function logError($msg, $ctx = [])
{
    Log::channel('manual_test')->error($msg, $ctx);
    echo "[ERROR] {$msg}\n";
}

try {
    DB::beginTransaction();

    logInfo("=== Starting Standardization Verification: {$testRunId} ===");

    // === 1. Verify KPI Deduplication ===
    logInfo('Verifying KPI deduplication in GameController...');
    $controller = app(\App\Http\Controllers\GameController::class);

    // Use reflection to access protected calculateKPIs method
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('calculateKPIs');
    $method->setAccessible(true);

    $kpis = $method->invoke($controller);

    $labels = array_column($kpis, 'label');
    if (in_array('Logistics Health', $labels)) {
        logError("FAILED: 'Logistics Health' still present in generic KPIs array.");
    } else {
        logInfo("PASSED: 'Logistics Health' removed from generic KPIs array.");
    }

    // === 2. Verify Pathfinding is_premium flag ===
    logInfo("Verifying pathfinding 'is_premium' logic...");

    $locA = Location::factory()->create(['name' => 'Verify Source']);
    $locB = Location::factory()->create(['name' => 'Verify Target']);

    // Create standard route
    $standard = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'Truck',
        'cost' => 100,
        'is_active' => true,
    ]);

    // Create premium alternative
    $premium = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'Air',
        'cost' => 500,
        'is_active' => true,
    ]);

    $logisticsService = app(LogisticsService::class);

    // Case A: Standard is picked
    $path = $logisticsService->findBestRoute($locA, $locB);
    $isPremium = $logisticsService->isPremiumRoute($path->first());
    logInfo('Direct Route (Truck): '.($isPremium ? 'PREMIUM' : 'STANDARD'));

    if ($isPremium) {
        logError('FAILED: Truck route should not be premium.');
    } else {
        logInfo('PASSED: Truck route correctly identified as standard.');
    }

    // Case B: Spike hits standard, premium is picked
    logInfo('Triggering spike on standard route...');
    SpikeEvent::factory()->create([
        'type' => 'strike',
        'is_active' => true,
        'affected_route_id' => $standard->id,
        'magnitude' => 10.0, // Cost becomes 1100
    ]);

    $path = $logisticsService->findBestRoute($locA, $locB);
    $isPremium = $logisticsService->isPremiumRoute($path->first());
    logInfo('Alternative Route (Air): '.($isPremium ? 'PREMIUM' : 'STANDARD'));

    if (! $isPremium) {
        logError('FAILED: Air route should be premium when chosen over truck.');
    } else {
        logInfo('PASSED: Air route correctly identified as premium.');
    }

    // === 3. Verify TDD Documentation ===
    logInfo("Verifying TDD content for 'Informational Blocking'...");
    $tddPath = base_path('docs/technical-design-document.md');
    $tddContent = file_get_contents($tddPath);

    if (str_contains($tddContent, 'Informational Blocking')) {
        logInfo("PASSED: TDD contains 'Informational Blocking' standard.");
    } else {
        logError("FAILED: TDD is missing 'Informational Blocking' standard.");
    }

    logInfo('All manual verification checks completed.');

} catch (\Exception $e) {
    logError('Verification failed', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    DB::rollBack();
    logInfo('Database state restored (Transaction Rolled Back).');
    logInfo('=== Verification Finished ===');
    echo "\n✓ Full logs at: {$logFile}\n";
}
