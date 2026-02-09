<?php

/**
 * Manual Test: Phase 0 Monetary Canonicalization Verification
 * Track: conductor/tracks/arch_remediation_20260207/plan.md
 *
 * Verifies:
 * - Starting cash invariant (1000000 cents) across initialize/fallback/reset paths.
 * - Monetary casts use integer cents.
 * - Core money arithmetic remains cent-based.
 * - No regression to legacy 10000.00 initialization values in key paths.
 */

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    fwrite(STDERR, "Error: Cannot run manual tests in production.\n");
    exit(1);
}

use App\Actions\InitializeNewGame;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Events\TimeAdvanced;
use App\Http\Controllers\GameController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Listeners\ApplyStorageCosts;
use App\Listeners\DeductCash;
use App\Models\DemandEvent;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$testRunId = 'phase0_money_'.Carbon::now()->format('Y_m_d_His');
$logFile = storage_path("logs/manual_tests/{$testRunId}.log");

if (! is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

config(['logging.channels.manual_test' => [
    'driver' => 'single',
    'path' => $logFile,
    'level' => 'debug',
]]);

function logInfo(string $message, array $context = []): void
{
    Log::channel('manual_test')->info($message, $context);
    echo "[INFO] {$message}\n";
}

function logError(string $message, array $context = []): void
{
    Log::channel('manual_test')->error($message, $context);
    echo "[ERROR] {$message}\n";
}

function assertCondition(bool $condition, string $message, array $context = []): void
{
    if (! $condition) {
        throw new RuntimeException($message.(empty($context) ? '' : ' '.json_encode($context)));
    }
}

function runCheck(string $name, callable $callback, array &$results): void
{
    try {
        $callback();
        $results['passed']++;
        logInfo("[PASS] {$name}");
    } catch (Throwable $e) {
        $results['failed']++;
        logError("[FAIL] {$name}", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}

function ensureCoreSeedData(): void
{
    if (Product::count() > 0 && Location::where('type', 'store')->exists() && Location::where('type', 'warehouse')->exists()) {
        return;
    }

    logInfo('Core seed data missing. Running CoreGameStateSeeder + GraphSeeder...');
    app(\Database\Seeders\CoreGameStateSeeder::class)->run();
    app(\Database\Seeders\GraphSeeder::class)->run();
}

$results = ['passed' => 0, 'failed' => 0];
$transactionStarted = false;

try {
    DB::beginTransaction();
    $transactionStarted = true;
    logInfo("=== Starting Phase 0 Monetary Canonicalization Verification: {$testRunId} ===");

    runCheck('seed requirements available', function (): void {
        ensureCoreSeedData();
        assertCondition(Product::count() > 0, 'Expected products to exist after seed');
        assertCondition(Location::where('type', 'store')->exists(), 'Expected at least one store location');
        assertCondition(Location::where('type', 'warehouse')->exists(), 'Expected at least one warehouse location');
    }, $results);

    runCheck('InitializeNewGame sets integer starting cash to 1000000', function (): void {
        $user = User::factory()->create();
        $gameState = app(InitializeNewGame::class)->handle($user);

        assertCondition(is_int($gameState->cash), 'Expected game state cash to be integer', ['actual_type' => gettype($gameState->cash)]);
        assertCondition($gameState->cash === 1000000, 'Expected starting cash to equal 1000000 cents', ['actual' => $gameState->cash]);
    }, $results);

    runCheck('HandleInertiaRequests fallback game state uses 1000000 cents', function (): void {
        $user = User::factory()->create();
        $request = Request::create('/game/dashboard', 'GET');
        $request->setUserResolver(static fn () => $user);

        $shared = app(HandleInertiaRequests::class)->share($request);
        $gamePayload = $shared['game']();
        $cash = $gamePayload['state']['cash'] ?? null;

        assertCondition(is_int($cash), 'Expected middleware cash to be integer', ['actual_type' => gettype($cash)]);
        assertCondition($cash === 1000000, 'Expected middleware fallback cash to equal 1000000', ['actual' => $cash]);
    }, $results);

    runCheck('resetGame restores cash/day invariants', function (): void {
        ensureCoreSeedData();
        $user = User::factory()->create();
        app(InitializeNewGame::class)->handle($user);

        $gameState = GameState::where('user_id', $user->id)->firstOrFail();
        $gameState->update(['cash' => 12345, 'day' => 9, 'xp' => 99]);

        auth()->guard()->setUser($user);
        app(GameController::class)->resetGame(app(InitializeNewGame::class));
        $gameState->refresh();

        assertCondition($gameState->cash === 1000000, 'Expected reset cash to be 1000000', ['actual' => $gameState->cash]);
        assertCondition($gameState->day === 1, 'Expected reset day to be 1', ['actual' => $gameState->day]);
        assertCondition(is_int($gameState->cash), 'Expected reset cash type to remain integer', ['actual_type' => gettype($gameState->cash)]);
    }, $results);

    runCheck('monetary model casts return integer cents', function (): void {
        $gs = GameState::factory()->create(['cash' => 1000000]);
        $order = Order::factory()->create(['total_cost' => 5050]);
        $orderItem = OrderItem::factory()->create(['cost_per_unit' => 250]);
        $product = Product::factory()->create(['unit_price' => 450, 'storage_cost' => 15]);
        $route = Route::factory()->create(['cost' => 150]);
        $event = DemandEvent::factory()->create([
            'unit_price' => 350,
            'revenue' => 700,
            'lost_revenue' => 350,
        ]);

        assertCondition(is_int($gs->fresh()->cash), 'GameState cash cast should be int');
        assertCondition(is_int($order->fresh()->total_cost), 'Order total_cost cast should be int');
        assertCondition(is_int($orderItem->fresh()->cost_per_unit), 'OrderItem cost_per_unit cast should be int');
        assertCondition(is_int($product->fresh()->unit_price), 'Product unit_price cast should be int');
        assertCondition(is_int($product->fresh()->storage_cost), 'Product storage_cost cast should be int');
        assertCondition(is_int($route->fresh()->cost), 'Route cost cast should be int');
        assertCondition(is_int($event->fresh()->unit_price), 'DemandEvent unit_price cast should be int');
        assertCondition(is_int($event->fresh()->revenue), 'DemandEvent revenue cast should be int');
        assertCondition(is_int($event->fresh()->lost_revenue), 'DemandEvent lost_revenue cast should be int');
    }, $results);

    runCheck('DeductCash listener performs cent arithmetic with integers', function (): void {
        $user = User::factory()->create();
        $gameState = GameState::factory()->create([
            'user_id' => $user->id,
            'cash' => 120000,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_cost' => 4500,
        ]);

        $listener = app(DeductCash::class);
        $listener->handle(new OrderPlaced($order));
        $gameState->refresh();
        assertCondition($gameState->cash === 115500, 'OrderPlaced should decrement cash by total_cost', ['actual' => $gameState->cash]);
        assertCondition(is_int($gameState->cash), 'Cash should remain integer after decrement');

        $listener->handle(new OrderCancelled($order));
        $gameState->refresh();
        assertCondition($gameState->cash === 120000, 'OrderCancelled should restore cash', ['actual' => $gameState->cash]);
        assertCondition(is_int($gameState->cash), 'Cash should remain integer after increment');
    }, $results);

    runCheck('ApplyStorageCosts listener deducts integer cent totals', function (): void {
        $user = User::factory()->create();
        $gameState = GameState::factory()->create([
            'user_id' => $user->id,
            'cash' => 200000,
        ]);
        $location = Location::factory()->create(['type' => 'store']);
        $product = Product::factory()->create(['storage_cost' => 25]);
        Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 12,
        ]);

        app(ApplyStorageCosts::class)->handle(new TimeAdvanced(2, $gameState));
        $gameState->refresh();

        // 12 * 25 = 300 cents
        assertCondition($gameState->cash === 199700, 'Expected storage-cost deduction to be 300 cents', ['actual' => $gameState->cash]);
        assertCondition(is_int($gameState->cash), 'Cash should remain integer after storage deduction');
    }, $results);

    runCheck('key monetary source files do not contain legacy 10000.00 initializer', function (): void {
        $paths = [
            app_path('Actions/InitializeNewGame.php'),
            app_path('Http/Middleware/HandleInertiaRequests.php'),
            app_path('Http/Controllers/GameController.php'),
        ];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            assertCondition($contents !== false, 'Unable to read source file', ['path' => $path]);
            assertCondition(strpos($contents, '10000.00') === false, 'Found legacy 10000.00 initializer', ['path' => $path]);
            assertCondition(strpos($contents, '1000000') !== false, 'Expected 1000000 invariant marker missing', ['path' => $path]);
        }
    }, $results);

    logInfo('Phase 0 money verification finished', $results);
} catch (Throwable $e) {
    $results['failed']++;
    logError('Fatal error in verification script', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    if ($transactionStarted) {
        DB::rollBack();
    }

    logInfo('Cleanup complete (transaction rolled back)');
    logInfo("=== Finished: {$testRunId} ===");
    echo "\nSummary: {$results['passed']} passed, {$results['failed']} failed\n";
    echo "Log: {$logFile}\n";
}

exit($results['failed'] > 0 ? 1 : 0);
