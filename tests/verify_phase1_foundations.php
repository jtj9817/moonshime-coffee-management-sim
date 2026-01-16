<?php
/**
 * Manual Verification Script: Phase 1 Foundations
 * Purpose: Verify proper instantiation of DTOs and existence of Interfaces without PHPUnit.
 */

require __DIR__ . '/../vendor/autoload.php';

// 1. Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 2. Environment Safety Check
if (app()->environment('production')) {
    echo "ERROR: Cannot run verification scripts in production!\n";
    exit(1);
}

use App\DTOs\InventoryContextDTO;
use App\DTOs\InventoryAdvisoryDTO;
use App\Interfaces\AiProviderInterface;
use App\Interfaces\RestockStrategyInterface;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

// 3. Setup Logging
$testRunId = 'phase1_verify_' . Carbon::now()->format('Y_m_d_His');
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

echo "--- Starting Verification: Phase 1 Foundations ---\\n";
logInfo("=== Test Run Started: {$testRunId} ===");

try {
    // 4. Test DTO Instantiation
    logInfo("Testing DTO Instantiation...");

    // InventoryContextDTO
    $contextDTO = new InventoryContextDTO(
        productId: 'prod-123',
        locationId: 'loc-456',
        quantity: 100,
        averageDailySales: 5.5
    );
    logInfo("InventoryContextDTO created successfully.", (array) $contextDTO);
    
    if ($contextDTO->productId !== 'prod-123') throw new Exception("InventoryContextDTO property mismatch");

    // InventoryAdvisoryDTO
    $advisoryDTO = new InventoryAdvisoryDTO(
        restockAmount: 50,
        reasoning: 'Manual verification test.'
    );
    logInfo("InventoryAdvisoryDTO created successfully.", (array) $advisoryDTO);

    if ($advisoryDTO->restockAmount !== 50) throw new Exception("InventoryAdvisoryDTO property mismatch");


    // 5. Test Interface Existence
    logInfo("Testing Interface Existence...");

    $interfaces = [
        AiProviderInterface::class,
        RestockStrategyInterface::class
    ];

    foreach ($interfaces as $interface) {
        if (interface_exists($interface)) {
            logInfo("Interface exists: {$interface}");
        } else {
            throw new Exception("Interface missing: {$interface}");
        }
    }

    echo "SUCCESS: All foundation components verified.\n";

} catch (\Exception $e) {
    logError("Verification Failed: " . $e->getMessage());
    exit(1);
} finally {
    logInfo("=== Test Run Completed: {$testRunId} ===");
    echo "Logs available at: {$logFile}\n";
}
