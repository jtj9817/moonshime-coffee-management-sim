<?php
/**
 * Manual Test: Starting Cash Verification
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

use Illuminate\Support\Facades\{DB, Log};
use App\Models\User;
use App\Models\GameState;
use App\Actions\InitializeNewGame;
use Carbon\Carbon;

$testRunId = 'cash_verification_' . Carbon::now()->format('Y_m_d_His');
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
    
    logInfo("=== Starting Manual Test: {$testRunId} ===");
    
    // === SETUP PHASE ===
    logInfo("Setting up test data...");
    
    // Create a temporary user
    $user = User::factory()->create([
        'name' => 'Cash Test User',
        'email' => 'cash_test_' . time() . '@example.com',
    ]);
    logInfo("Created temporary user", ['id' => $user->id]);

    // Ensure core data is seeded (if not already)
    // We assume core data exists for this verification, but we can check if products exist.
    if (\App\Models\Product::count() === 0) {
        logInfo("Seeding core game state...");
        $seeder = new \Database\Seeders\CoreGameStateSeeder();
        $seeder->run();
        
        logInfo("Seeding graph...");
        $graphSeeder = new \Database\Seeders\GraphSeeder();
        $graphSeeder->run();
    }

    // === EXECUTION PHASE ===
    logInfo("Running InitializeNewGame action...");
    
    $action = app(InitializeNewGame::class);
    $gameState = $action->handle($user);
    
    logInfo("Game initialized", [
        'user_id' => $user->id,
        'cash' => $gameState->cash,
    ]);

    // === VERIFICATION PHASE ===
    $expectedCash = 1000000.00;
    
    if (abs($gameState->cash - $expectedCash) < 0.01) {
        logInfo("SUCCESS: Starting cash is correct: " . number_format($gameState->cash, 2));
    } else {
        throw new Exception("FAILURE: Starting cash is incorrect. Expected {$expectedCash}, got {$gameState->cash}");
    }
    
    logInfo("Tests completed successfully");
    
} catch (\Exception $e) {
    logError("Test failed", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo "\n[FAILURE] " . $e->getMessage() . "\n";
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Cleanup completed (Database transaction rolled back)");
    logInfo("=== Test Run Finished ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}
