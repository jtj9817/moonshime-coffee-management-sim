<?php

/**
 * Manual Test: Backend Metrics, Logistics API, and Alternative Pathfinding
 * Generated: 2026-01-17
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Http\Controllers\LogisticsController;
use App\Models\Location;
use App\Models\Route as GameRoute;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

$testRunId = 'test_logistics_v2_'.Carbon::now()->format('Y_m_d_His');
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

    logInfo("=== Starting Manual Test V2: {$testRunId} ===");

    // === SETUP PHASE ===
    logInfo('Setting up complex logistics scenario...');

    $user = User::factory()->create(['name' => 'Test Conductor']);
    $locA = Location::factory()->create(['name' => 'Hub Alpha']);
    $locB = Location::factory()->create(['name' => 'Hub Beta']);
    $locC = Location::factory()->create(['name' => 'Cafe Gamma']);

    // Direct route Alpha -> Gamma (Blocked)
    $routeDirect = GameRoute::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locC->id,
        'weights' => ['cost' => 100],
        'is_active' => false, // BLOCKED
    ]);

    // Multi-step route: Alpha -> Beta -> Gamma
    $routeAlphaBeta = GameRoute::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'weights' => ['cost' => 200],
        'is_active' => true,
    ]);

    $routeBetaGamma = GameRoute::factory()->create([
        'source_id' => $locB->id,
        'target_id' => $locC->id,
        'weights' => ['cost' => 150],
        'is_active' => true,
    ]);

    logInfo('Data setup completed');

    // === EXECUTION PHASE ===
    logInfo('Running tests...');

    // 1. Verify Logistics Pathfinding finds the multi-step alternative
    logInfo('Testing LogisticsController@getPath for alternative Alpha -> Gamma...');
    $logisticsController = app(LogisticsController::class);
    $api_request = Request::create('/api/logistics/path', 'GET', [
        'source_id' => $locA->id,
        'target_id' => $locC->id,
    ]);

    $apiResponse = $logisticsController->getPath($api_request);
    $data = json_decode($apiResponse->getContent(), true);

    logInfo('Logistics API Response received', $data);

    if (isset($data['success']) && $data['success'] && count($data['path']) == 2 && $data['total_cost'] == 350) {
        logInfo('✓ Multi-step alternative path found correctly (Cost: 350, Steps: 2)');
    } else {
        logError('✗ Alternative pathfinding incorrect.', [
            'expected_cost' => 350,
            'expected_steps' => 2,
            'got_cost' => $data['total_cost'] ?? 'N/A',
            'got_steps' => count($data['path'] ?? []),
        ]);
    }

    // 2. Verify that a truly blocked destination returns reachable: false
    logInfo('Testing reachability for isolated location...');
    $locIsolated = Location::factory()->create(['name' => 'Isolated Island']);
    $iso_request = Request::create('/api/logistics/path', 'GET', [
        'source_id' => $locA->id,
        'target_id' => $locIsolated->id,
    ]);
    $isoResponse = $logisticsController->getPath($iso_request);
    $isoData = json_decode($isoResponse->getContent(), true);

    if (isset($isoData['reachable']) && $isoData['reachable'] === false) {
        logInfo('✓ Isolated location correctly reported as unreachable');
    } else {
        logError('✗ Isolated location reachability check failed');
    }

    logInfo('Tests completed successfully');

} catch (\Exception $e) {
    logError('Test failed', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo('Cleanup completed (Database transaction rolled back)');
    logInfo('=== Test Run Finished ===');
    echo "\n✓ Full logs at: {$logFile}\n";
}
