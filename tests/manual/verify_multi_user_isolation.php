<?php
/**
 * Manual Test: Multi-User Isolation Verification
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Alert;
use App\Models\SpikeEvent;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

// Prevent production execution
if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

$testRunId = 'test_' . Carbon::now()->format('Y_m_d_His');
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
    
    logInfo("=== Starting Manual Test: Multi-User Isolation ===");
    
    // === SETUP PHASE ===
    logInfo("Creating test users...");
    $userA = User::factory()->create(['name' => 'User A (Test)'])
    $userB = User::factory()->create(['name' => 'User B (Test)'])
    
    logInfo("Creating data for User B...");
    Alert::factory()->count(5)->create([
        'user_id' => $userB->id,
        'is_read' => false,
        'severity' => 'critical',
        'type' => 'system',
        'message' => 'User B Alert',
    ]);
    
    SpikeEvent::factory()->create([
        'user_id' => $userB->id,
        'is_active' => true,
        'type' => 'demand',
        'magnitude' => 1.5,
    ]);
    
    // === VERIFICATION PHASE ===
    logInfo("Verifying User A's view...");
    
    // Instantiate Middleware
    $middleware = new HandleInertiaRequests();
    
    // Create Request for User A
    $request = Request::create('/game/dashboard', 'GET');
    $request->setUserResolver(function () use ($userA) {
        return $userA;
    });
    
    // Execute share method logic
    $sharedProps = $middleware->share($request);
    
    // Inspect 'game' prop
    // sharedProps['game'] is a Closure, need to execute it
    $gameData = $sharedProps['game']();
    
    $alertCount = $gameData['alerts']->count();
    $spikeCount = $gameData['activeSpikes']->count();
    $reputation = $gameData['state']['reputation'];
    
    logInfo("User A sees {$alertCount} alerts (Expected: 0)");
    logInfo("User A sees {$spikeCount} active spikes (Expected: 0)");
    logInfo("User A reputation: {$reputation} (Expected: 85)");
    
    if ($alertCount === 0 && $spikeCount === 0 && $reputation === 85) {
        logInfo("SUCCESS: Data is properly isolated.");
    } else {
        logError("FAILURE: Data leakage detected.");
        throw new Exception("Verification failed");
    }
    
} catch (\Exception $e) {
    logError("Test failed", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} finally {
    // === CLEANUP PHASE ===
    DB::rollBack();
    logInfo("Cleanup completed (Transaction Rolled Back)");
    logInfo("=== Test Run Finished ===");
    echo "\n✓ Full logs at: {$logFile}\n";
}
