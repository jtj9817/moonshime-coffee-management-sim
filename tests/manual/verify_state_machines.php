<?php

/**
 * Manual Test: State Machines Lifecycle
 * Purpose: Verifies Order and Transfer state machines, including cash validation.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Models\GameState;
use App\Models\Order;
use App\Models\Transfer;
use App\Models\User;
use App\States\Order\Delivered;
use App\States\Order\Pending;
use App\States\Order\Shipped;
use App\States\Transfer\Completed;
use App\States\Transfer\InTransit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$testRunId = 'state_machine_test_'.Carbon::now()->format('Y_m_d_His');
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

    logInfo("=== Starting State Machine Manual Test: {$testRunId} ===");

    // === SETUP PHASE ===
    logInfo('Setting up test user and game state...');
    $user = User::factory()->create(['name' => 'State Test User']);
    Auth::login($user);

    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 500000, // $5,000.00
    ]);
    logInfo("User created: {$user->email}");
    logInfo("Initial Cash: {$gameState->cash}");

    // === EXECUTION PHASE: ORDER ===
    logInfo('Phase 1: Order State Machine');

    logInfo('Creating draft order (cost: 200,000)...');
    $order = Order::factory()->create(['total_cost' => 200000]);
    logInfo("Initial status: {$order->status}");

    logInfo('Attempting transition: Draft -> Pending (Should succeed)');
    $order->status->transitionTo(Pending::class);
    logInfo("New status: {$order->fresh()->status}");

    logInfo('Attempting transition: Pending -> Shipped');
    $order->status->transitionTo(Shipped::class);
    logInfo("New status: {$order->fresh()->status}");

    logInfo('Attempting transition: Shipped -> Delivered');
    $order->status->transitionTo(Delivered::class);
    logInfo("New status: {$order->fresh()->status}");

    // Test Insufficient Funds
    logInfo('Testing insufficient funds validation...');
    $poorOrder = Order::factory()->create(['total_cost' => 1000000]); // $10,000
    try {
        logInfo('Attempting transition for expensive order (cost: 1,000,000)...');
        $poorOrder->status->transitionTo(Pending::class);
        logError('FAIL: Transition succeeded despite insufficient funds!');
    } catch (\RuntimeException $e) {
        logInfo('SUCCESS: Caught expected exception: '.$e->getMessage());
    }

    // === EXECUTION PHASE: TRANSFER ===
    logInfo('Phase 2: Transfer State Machine');

    logInfo('Creating draft transfer...');
    $transfer = Transfer::factory()->create();
    logInfo("Initial status: {$transfer->status}");

    logInfo('Attempting transition: Draft -> InTransit');
    $transfer->status->transitionTo(InTransit::class);
    logInfo("New status: {$transfer->fresh()->status}");

    logInfo('Attempting transition: InTransit -> Completed');
    $transfer->status->transitionTo(Completed::class);
    logInfo("New status: {$transfer->fresh()->status}");

    logInfo('All state machine tests completed successfully.');

} catch (\Exception $e) {
    logError('Test failed', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo('Cleanup: Transaction rolled back successfully.');
    logInfo('=== Test Run Finished ===');
    echo "\n✓ Full logs at: {$logFile}\n";
}
