<?php

/**
 * Manual Test: Advanced Stress Testing (Phase 5)
 * Generated: 2026-01-17
 * Purpose: Verify the system's resilience under cascading disruptions, complex pathfinding stressors,
 *          and recursive resolution of causal chains.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Events\SpikeOccurred;
use App\Models\Alert;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Services\LogisticsService;
use App\Services\SimulationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$testRunId = 'stress_scenarios_v1_'.Carbon::now()->format('Y_m_d_His');
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

function assertCondition($condition, $message)
{
    if (! $condition) {
        logError("Assertion Failed: {$message}");
        throw new \Exception("Assertion Failed: {$message}");
    }
    logInfo("Assertion Passed: {$message}");
}

try {
    DB::beginTransaction();

    logInfo("=== Starting Manual Test: {$testRunId} ===");

    // === SETUP ===
    $user = User::factory()->create(['name' => 'Stress Test User']);
    Auth::login($user);
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);
    $service = new SimulationService($gameState);

    // === SCENARIO A: THE CASCADE ===
    logInfo('Running Scenario A: The Cascade');
    $hub = Location::factory()->create(['type' => 'warehouse', 'name' => 'Stress Hub']);
    $stores = Location::factory()->count(12)->create(['type' => 'store']);

    foreach ($stores as $store) {
        $route = Route::factory()->create([
            'source_id' => $hub->id,
            'target_id' => $store->id,
            'weather_vulnerability' => true,
        ]);

        Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $store->id,
            'quantity' => 5,
        ]);

        $spike = SpikeEvent::create([
            'user_id' => $user->id,
            'type' => 'blizzard',
            'magnitude' => 1.0,
            'duration' => 2,
            'affected_route_id' => $route->id,
            'starts_at_day' => 1,
            'ends_at_day' => 3,
            'is_active' => true,
        ]);
        event(new SpikeOccurred($spike));
    }

    $service->advanceTime(); // Day 1 -> 2
    $alertCount = Alert::where('type', 'isolation')->where('is_resolved', false)->count();
    assertCondition($alertCount >= 12, "Cascading isolation alerts generated ($alertCount)");

    $service->advanceTime(); // Day 2 -> 3 (Spikes expire)
    $service->advanceTime(); // Day 3 -> 4 (Resolution tick)

    $resolvedCount = Alert::where('type', 'isolation')->where('is_resolved', true)->count();
    assertCondition($resolvedCount >= 12, "Cascading alerts automatically resolved ($resolvedCount)");

    // === SCENARIO B: THE DECISION STRESSOR ===
    logInfo('Running Scenario B: The Decision Stressor');
    $vendor = Location::factory()->create(['type' => 'vendor']);
    $warehouse = Location::factory()->create(['type' => 'warehouse']);

    $cheapRoute = Route::factory()->create([
        'source_id' => $vendor->id,
        'target_id' => $warehouse->id,
        'cost' => 100,
        'is_active' => true,
    ]);

    $premiumRoute = Route::factory()->create([
        'source_id' => $vendor->id,
        'target_id' => $warehouse->id,
        'transport_mode' => 'Air',
        'cost' => 1000,
        'is_active' => true,
    ]);

    // Apply breakdown to cheap route
    $breakdown = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 2,
        'affected_route_id' => $cheapRoute->id,
        'starts_at_day' => 4,
        'ends_at_day' => 6,
        'is_active' => true,
    ]);
    event(new SpikeOccurred($breakdown));

    $logistics = app(LogisticsService::class);
    $best = $logistics->findBestRoute($vendor, $warehouse);
    assertCondition($best->first()->id === $premiumRoute->id, 'Pathfinding correctly selects premium route during breakdown');
    assertCondition($logistics->isPremiumRoute($best->first()), 'Route correctly identified as premium');

    // Deactivate Scenario B spike to avoid interference in Scenario C
    $breakdown->update(['is_active' => false]);

    // === SCENARIO C: RECURSIVE RESOLUTION ===
    logInfo('Running Scenario C: Recursive Resolution');

    $isolatedStore = Location::factory()->create(['type' => 'store', 'name' => 'Recursive Store']);
    $storeRoute = Route::factory()->create([
        'source_id' => $warehouse->id,
        'target_id' => $isolatedStore->id,
        'weather_vulnerability' => true,
    ]);

    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $isolatedStore->id,
        'quantity' => 0,
    ]);

    // Root resolution test
    $rootSpike = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 9,
        'affected_route_id' => $storeRoute->id,
        'starts_at_day' => 1,
        'ends_at_day' => 10,
        'is_active' => true,
    ]);
    event(new SpikeOccurred($rootSpike));

    $service->advanceTime();
    $alert = Alert::where('type', 'isolation')->where('location_id', $isolatedStore->id)->where('is_resolved', false)->latest()->first();
    assertCondition($alert !== null, 'Isolation alert generated for recursive store');
    assertCondition($alert->spike_event_id === $rootSpike->id, 'Symptom alert correctly linked to root spike');

    // End root spike
    $rootSpike->update(['ends_at_day' => $gameState->fresh()->day]);
    $service->advanceTime();

    assertCondition($alert->fresh()->is_resolved, 'Ending root spike correctly auto-resolves symptom alerts');

    logInfo('Tests completed successfully');

} catch (\Exception $e) {
    logError('Test failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
} finally {
    DB::rollBack();
    logInfo('Cleanup complete');
    echo "\n✓ Stress tests completed. Logs: {$logFile}\n";
}
