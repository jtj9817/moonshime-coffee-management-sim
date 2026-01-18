<?php
/**
 * Manual Test: Backend API Enhancements
 * Generated: 2026-01-17
 * Purpose: Verify Route Retrieval API and Order Cancellation API
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
use Carbon\Carbon;
use App\Models\User;
use App\Models\Location;
use App\Models\Route;
use App\Models\Order;
use App\Models\GameState;
use App\States\Order\Shipped;
use App\States\Order\Cancelled;
use Illuminate\Http\Request;

$testRunId = 'test_backend_api_' . Carbon::now()->format('Y_m_d_His');
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
    
    $user = User::factory()->create(['name' => 'Verification User']);
    logInfo("Created user: {$user->id}");

    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 10000,
        'day' => 1
    ]);
    logInfo("Created game state for user");

    $locA = Location::factory()->create(['name' => 'Verify Source']);
    $locB = Location::factory()->create(['name' => 'Verify Target']);
    logInfo("Created locations: {$locA->id} -> {$locB->id}");

    $route = Route::factory()->create([
        'source_id' => $locA->id,
        'target_id' => $locB->id,
        'transport_mode' => 'truck',
        'cost' => 100,
        'transit_days' => 1,
        'is_active' => true
    ]);
    logInfo("Created route: {$route->id}");

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'location_id' => $locA->id,
        'status' => Shipped::class,
        'total_cost' => 500
    ]);
    logInfo("Created order in Shipped state: {$order->id}");
    
    // === EXECUTION PHASE ===
    logInfo("Running tests...");
    
    // 1. Verify Route Retrieval
    logInfo("Test 1: Route Retrieval API");
    $controller = app(\App\Http\Controllers\LogisticsController::class);
    $request = Request::create('/game/logistics/routes', 'GET', ['source_id' => $locA->id]);
    $response = $controller->getRoutes($request);
    
    $data = json_decode($response->getContent(), true);
    if (count($data['data']) === 1 && $data['data'][0]['id'] === $route->id) {
        logInfo("Route retrieval successful and filtering works.");
    } else {
        logError("Route retrieval failed or filter ignored.");
    }

    // 2. Verify Order Cancellation
    logInfo("Test 2: Order Cancellation API");
    // Simulate authentication
    auth()->login($user);
    
    $gameController = app(\App\Http\Controllers\GameController::class);
    $cancelResponse = $gameController->cancelOrder($order);
    
    $cancelData = json_decode($cancelResponse->getContent(), true);
    if ($cancelData['success'] === true) {
        logInfo("Order cancellation API call returned success.");
        
        $order->refresh();
        if ($order->status instanceof Cancelled) {
            logInfo("Order status updated to Cancelled.");
        } else {
            logError("Order status NOT updated to Cancelled.");
        }
        
        $gameState->refresh();
        if ($gameState->cash == 10500) {
            logInfo("Cash refund verified: 10000 -> 10500");
        } else {
            logError("Cash refund failed. Current cash: {$gameState->cash}");
        }
    } else {
        logError("Order cancellation API call failed: " . ($cancelData['message'] ?? 'Unknown error'));
    }
    
    logInfo("Tests completed successfully");
    
} catch (\Exception $e) {
    logError("Test failed", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Cleanup completed (Database transaction rolled back)");
    logInfo("=== Test Run Finished ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}
