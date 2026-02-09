<?php

/**
 * Manual Test: Phase 3 Analytics Verification
 *
 * Verifies the new analytics metrics:
 * - Storage Utilization
 * - Order Fulfillment
 * - Spike Impact Analysis
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Remove ManualTestRunner and direct TestCase usage
// Helper to extract props from Inertia Response
function getInertiaProps($response)
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

$testRunId = 'verify_analytics_'.Carbon::now()->format('Y_m_d_His');
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

    logInfo("=== Starting Analytics Verification: {$testRunId} ===");

    // === SETUP PHASE ===
    logInfo('Setting up test data...');

    $user = \App\Models\User::factory()->create();
    $location = \App\Models\Location::factory()->create(['name' => 'Test Cafe', 'max_storage' => 1000]);
    $product = \App\Models\Product::factory()->create(['name' => 'Test Coffee']);

    // 1. Storage Utilization Setup
    // 500 units / 1000 capacity = 50%
    \App\Models\Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 500,
    ]);
    logInfo("Created Inventory: 500 units in 'Test Cafe' (Capacity: 1000)");

    // 2. Fulfillment Metrics Setup
    // Total 2 orders, 1 delivered (50% rate). Delivery time: 2 days.
    \App\Models\Order::factory()->create([
        'user_id' => $user->id,
        'status' => \App\States\Order\Delivered::class,
        'created_day' => 1,
        'delivery_day' => 3, // 2 days transit
    ]);
    \App\Models\Order::factory()->create([
        'user_id' => $user->id,
        'status' => \App\States\Order\Pending::class,
        'created_day' => 5,
    ]);
    logInfo('Created Orders: 1 Delivered (2 days transit), 1 Pending');

    // 3. Spike Impact Setup
    // Spike from Day 2-3.
    // Inventory: Day 1 (100), Day 2 (50), Day 3 (10), Day 4 (100)
    $spike = \App\Models\SpikeEvent::factory()->create([
        'user_id' => $user->id,
        'type' => 'demand',
        'location_id' => $location->id,
        'product_id' => $product->id,
        'starts_at_day' => 2,
        'ends_at_day' => 3,
        'name' => 'Test Demand Spike',
    ]);

    DB::table('inventory_history')->insert([
        ['user_id' => $user->id, 'location_id' => $location->id, 'product_id' => $product->id, 'day' => 2, 'quantity' => 50, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'location_id' => $location->id, 'product_id' => $product->id, 'day' => 3, 'quantity' => 10, 'created_at' => now(), 'updated_at' => now()],
    ]);
    logInfo("Created Spike: 'Test Demand Spike' (Day 2-3) with low inventory history");

    // === EXECUTION PHASE ===
    logInfo('Executing Analytics Request...');

    Auth::login($user);
    $controller = new \App\Http\Controllers\GameController;
    $response = $controller->analytics();

    $props = getInertiaProps($response);

    // Inspect Results
    logInfo('--- Inspection Results ---');

    // Storage Utilization
    $storage = collect($props['storageUtilization'])->firstWhere('location_id', $location->id);
    if ($storage) {
        logInfo('Storage Utilization: '.json_encode($storage));
        if ($storage['percentage'] === 50.0) {
            logInfo('✅ Storage Utilization Correct (50%)');
        } else {
            logError("❌ Storage Utilization Incorrect: {$storage['percentage']}%");
        }
    } else {
        logError('❌ Storage Utilization missing for location');
    }

    // Fulfillment Metrics
    $fulfillment = $props['fulfillmentMetrics'];
    logInfo('Fulfillment Metrics: '.json_encode($fulfillment));
    if ($fulfillment['fulfillmentRate'] == 50 && $fulfillment['averageDeliveryTime'] == 2.0) {
        logInfo('✅ Fulfillment Metrics Correct (50%, 2.0 days)');
    } else {
        logError('❌ Fulfillment Metrics Incorrect');
    }

    // Spike Impact
    $impactAnalysis = collect($props['spikeImpactAnalysis'])->firstWhere('id', $spike->id);
    if ($impactAnalysis) {
        logInfo('Spike Impact: '.json_encode($impactAnalysis));
        $impact = $impactAnalysis['impact'];
        if ($impact && $impact['min_inventory'] == 10 && $impact['avg_inventory'] == 30.0) {
            // Avg of 50 and 10 is 30.
            logInfo('✅ Spike Impact Correct (Min: 10, Avg: 30.0)');
        } else {
            logError('❌ Spike Impact Incorrect');
        }
    } else {
        logError('❌ Spike Impact Analysis missing for spike');
    }

    logInfo('Tests completed successfully');

} catch (\Exception $e) {
    logError('Test failed', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo('Database transaction rolled back (Clean state restored)');
    logInfo('=== Test Run Finished ===');
    echo "\n✓ Full logs at: {$logFile}\n";
}
