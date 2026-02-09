<?php

/**
 * Manual Verification Script for Phase 2: Causal Graph & Event Propagation
 * Generated: 2026-01-16
 * Purpose: Verify Event Listeners and Route State changes
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Events\SpikeEnded;
use App\Events\SpikeOccurred;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

$testRunId = 'verify_phase2_'.Carbon::now()->format('Y_m_d_His');
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

    logInfo("=== Starting Phase 2 Verification: {$testRunId} ===");

    // === SETUP ===
    logInfo('Phase 1: Setup Routes...');
    $source = Location::factory()->create();
    $target = Location::factory()->create();

    $vulnerableRoute = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'weather_vulnerability' => true,
        'is_active' => true,
        'transport_mode' => 'Truck',
    ]);

    $safeRoute = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'weather_vulnerability' => false,
        'is_active' => true,
        'transport_mode' => 'Subway',
    ]);

    logInfo("Created Vulnerable Route ID: {$vulnerableRoute->id}");
    logInfo("Created Safe Route ID: {$safeRoute->id}");

    // === EXECUTION ===
    logInfo('Phase 2: Simulate Blizzard Event...');

    // Manually create spike linked to route
    $spike = SpikeEvent::factory()->create([
        'type' => 'blizzard',
        'affected_route_id' => $vulnerableRoute->id,
        'is_active' => true,
    ]);

    logInfo("Created Spike ID: {$spike->id}, Type: {$spike->type}");

    // Dispatch Start Event
    logInfo('Dispatching SpikeOccurred...');
    event(new SpikeOccurred($spike));

    // Check State
    $vulnerableRoute->refresh();
    $safeRoute->refresh();

    logInfo('Vulnerable Route Active: '.($vulnerableRoute->is_active ? 'YES' : 'NO'));
    logInfo('Safe Route Active: '.($safeRoute->is_active ? 'YES' : 'NO'));

    if ($vulnerableRoute->is_active) {
        throw new Exception("Vulnerable route should be INACTIVE!\n");
    }
    if (! $safeRoute->is_active) {
        throw new Exception("Safe route should be ACTIVE!\n");
    }

    // Dispatch End Event
    logInfo('Dispatching SpikeEnded...');
    event(new SpikeEnded($spike));

    // Check State
    $vulnerableRoute->refresh();
    logInfo('Vulnerable Route Active (Restored): '.($vulnerableRoute->is_active ? 'YES' : 'NO'));

    if (! $vulnerableRoute->is_active) {
        throw new Exception("Vulnerable route should be RESTORED to ACTIVE!\n");
    }

    logInfo('=== Verification Successful ===');

} catch (\Exception $e) {
    logError('Verification Failed', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    DB::rollBack();
    logInfo('Database Rolled Back.');
    echo "\n✓ Full logs at: {$logFile}\n";
}
