<?php

/**
 * Manual Test Script: Core Services Wiring & Integration
 * Purpose: Verifies Laravel container bindings and basic service integration logic.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Interfaces\AiProviderInterface;
use App\Interfaces\RestockStrategyInterface;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Services\InventoryManagementService;
use App\Services\InventoryMathService;
use App\Services\PrismAiService;
use App\Services\Strategies\JustInTimeStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$testRunId = 'test_wiring_'.Carbon::now()->format('Y_m_d_His');
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

    logInfo('=== Starting Manual Test: Core Services Wiring ===');

    // 1. Container Verification Phase
    logInfo('Phase 1: Container Binding Verification');

    $math1 = app(InventoryMathService::class);
    $math2 = app(InventoryMathService::class);
    if ($math1 instanceof InventoryMathService && $math1 === $math2) {
        logInfo('✓ InventoryMathService is bound as singleton.');
    } else {
        throw new \Exception('InventoryMathService binding failed.');
    }

    $aiProvider = app(AiProviderInterface::class);
    if ($aiProvider instanceof PrismAiService) {
        logInfo('✓ AiProviderInterface is bound to PrismAiService.');
    } else {
        throw new \Exception('AiProviderInterface binding failed.');
    }

    $strategy = app(RestockStrategyInterface::class);
    if ($strategy instanceof JustInTimeStrategy) {
        logInfo('✓ RestockStrategyInterface is bound to JustInTimeStrategy.');
    } else {
        throw new \Exception('RestockStrategyInterface binding failed.');
    }

    // 2. Integration Test Phase
    logInfo('Phase 2: Integration Logic Test');

    $mgmt = app(InventoryManagementService::class);

    logInfo('Setting up test data...');
    $location = Location::factory()->create(['name' => 'Wiring Test Hub']);
    $product = Product::factory()->create(['name' => 'Wiring Test Bean']);
    $inventory = Inventory::factory()->create([
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    logInfo('Executing restock operation...');
    $amount = $mgmt->restock($inventory, null, ['daily_demand' => 5, 'lead_time' => 4]);
    $inventory->refresh();

    logInfo('Restock Result', ['amount' => $amount, 'new_quantity' => $inventory->quantity]);

    if ($amount === 10 && $inventory->quantity === 20) {
        logInfo('✓ Integration logic verified successfully.');
    } else {
        throw new \Exception('Integration logic mismatch: Expected amount 10 and quantity 20.');
    }

    logInfo('Tests completed successfully');

} catch (\Exception $e) {
    logError('Test failed', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    // Optional: Log trace for deep debugging
    Log::channel('manual_test')->debug($e->getTraceAsString());
} finally {
    DB::rollBack();
    logInfo('Cleanup: Database transaction rolled back.');
    logInfo('=== Test Run Finished ===');
    echo "\n✓ Full logs available at: {$logFile}\n";
}
