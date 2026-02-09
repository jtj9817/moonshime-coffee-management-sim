<?php

/**
 * Manual Test: Chaos Engine (SpikeEventFactory)
 * Generated: 2026-01-16
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
use App\Models\Product;
use App\Models\SpikeEvent;
use App\Services\SpikeEventFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$testRunId = 'chaos_engine_'.Carbon::now()->format('Y_m_d_His');
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

    logInfo("=== Starting Chaos Engine Manual Test: {$testRunId} ===");

    // === SETUP PHASE ===
    logInfo('Phase 1: Setup test data');
    $location = Location::factory()->create(['name' => 'Manual Test Location', 'max_storage' => 1000]);
    $product = Product::factory()->create(['name' => 'Manual Test Product']);
    logInfo('Created location and product', ['location_id' => $location->id, 'product_id' => $product->id]);

    // === EXECUTION PHASE ===
    logInfo('Phase 2: Spike Generation');
    $factory = new SpikeEventFactory;

    $spike = $factory->generate(10); // Current day 10
    if (! $spike) {
        logError('Failed to generate spike event');
    } else {
        logInfo('Generated spike event', $spike->toArray());
    }

    logInfo('Phase 3: Testing Breakdown Spike');
    $breakdownSpike = SpikeEvent::create([
        'type' => 'breakdown',
        'magnitude' => 0.5, // 50% reduction
        'duration' => 3,
        'location_id' => $location->id,
        'starts_at_day' => 11,
        'ends_at_day' => 14,
        'is_active' => false,
    ]);

    logInfo("Initial capacity: {$location->max_storage}");
    $factory->apply($breakdownSpike);
    $location->refresh();
    logInfo("Capacity after apply: {$location->max_storage}");

    if ($location->max_storage == 500) {
        logInfo('Breakdown Spike Apply: SUCCESS');
    } else {
        logError("Breakdown Spike Apply: FAILED. Expected 500, got {$location->max_storage}");
    }

    $factory->rollback($breakdownSpike);
    $location->refresh();
    logInfo("Capacity after rollback: {$location->max_storage}");

    if ($location->max_storage == 1000) {
        logInfo('Breakdown Spike Rollback: SUCCESS');
    } else {
        logError("Breakdown Spike Rollback: FAILED. Expected 1000, got {$location->max_storage}");
    }

    logInfo('Phase 4: Testing Demand Spike (Passive)');
    $demandSpike = SpikeEvent::create([
        'type' => 'demand',
        'magnitude' => 2.0,
        'duration' => 2,
        'product_id' => $product->id,
        'starts_at_day' => 11,
        'ends_at_day' => 13,
        'is_active' => false,
    ]);

    $factory->apply($demandSpike);
    if ($demandSpike->refresh()->is_active) {
        logInfo('Demand Spike Apply: SUCCESS (is_active = true)');
    } else {
        logError('Demand Spike Apply: FAILED');
    }

    $factory->rollback($demandSpike);
    if (! $demandSpike->refresh()->is_active) {
        logInfo('Demand Spike Rollback: SUCCESS (is_active = false)');
    } else {
        logError('Demand Spike Rollback: FAILED');
    }

    logInfo('Tests completed successfully');

} catch (\Exception $e) {
    logError('Test failed', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo('Cleanup: Database transaction rolled back.');
    logInfo('=== Test Run Finished ===');
    echo "\n✓ Full logs at: {$logFile}\n";
}
