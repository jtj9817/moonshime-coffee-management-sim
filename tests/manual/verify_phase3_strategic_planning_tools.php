<?php

/**
 * Manual Verification Script: Phase 3 - Strategic Planning Tools
 * Generated: 2026-02-10
 *
 * Validates backend Phase 3 outcomes:
 * 1. Scheduled order creation and management controls
 * 2. Planning tick execution on day advance
 * 3. Guarded auto-submit behavior (funds and route capacity)
 * 4. Cron-based cadence fallback support
 *
 * Usage: ./vendor/bin/sail php tests/manual/verify_phase3_strategic_planning_tools.php
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production.\n");
}

use App\Http\Controllers\GameController;
use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Route;
use App\Models\ScheduledOrder;
use App\Models\User;
use App\Models\UserLocation;
use App\Models\UserQuest;
use App\Models\Vendor;
use App\Services\SimulationService;
use App\States\Order\Draft;
use App\States\Order\Pending;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

$testRunId = 'verify_phase3_strategic_planning_tools_'.Carbon::now()->format('Y_m_d_His');
$logFile = storage_path("logs/manual_tests/{$testRunId}.log");

if (! is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

config(['logging.channels.manual_test' => [
    'driver' => 'single',
    'path' => $logFile,
    'level' => 'debug',
]]);

$passed = 0;
$failed = 0;

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

function check(string $label, bool $condition, array $context = []): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        logInfo("[PASS] {$label}", $context);

        return;
    }

    $failed++;
    logError("[FAIL] {$label}", $context);
}

function runScenario(string $name, callable $scenario): void
{
    global $failed;

    logInfo("--- {$name} ---");

    $useSavepoint = DB::transactionLevel() > 0;
    if ($useSavepoint) {
        DB::beginTransaction();
    }

    try {
        $scenario();
        logInfo("Completed scenario: {$name}");
    } catch (\Throwable $throwable) {
        $failed++;
        logError("Scenario exception: {$name}", [
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ]);
    } finally {
        if ($useSavepoint && DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}

/**
 * @return array{
 *   user: User,
 *   gameState: GameState,
 *   vendor: Vendor,
 *   sourceLocation: Location,
 *   targetLocation: Location,
 *   product: Product,
 *   route: Route
 * }
 */
function createScheduledOrderWorld(
    string $suffix,
    int $cash,
    int $day = 1,
    int $routeCapacity = 100
): array {
    $user = User::factory()->create([
        'name' => "Phase 3 Verifier {$suffix}",
        'email' => "verify-phase3-{$suffix}-".uniqid().'@test.com',
    ]);

    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => $day,
        'cash' => $cash,
    ]);

    $vendor = Vendor::factory()->create();
    $sourceLocation = Location::factory()->create(['type' => 'vendor']);
    $targetLocation = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create();

    $route = Route::factory()->create([
        'source_id' => $sourceLocation->id,
        'target_id' => $targetLocation->id,
        'cost' => 250,
        'capacity' => $routeCapacity,
        'transit_days' => 2,
        'is_active' => true,
    ]);

    UserLocation::query()->firstOrCreate([
        'user_id' => $user->id,
        'location_id' => $sourceLocation->id,
    ]);
    UserLocation::query()->firstOrCreate([
        'user_id' => $user->id,
        'location_id' => $targetLocation->id,
    ]);

    return compact(
        'user',
        'gameState',
        'vendor',
        'sourceLocation',
        'targetLocation',
        'product',
        'route',
    );
}

function calculateRewardDeltaFromNewCompletions(int $userId, array $baselineCompletedQuestIds): int
{
    $newCompletedQuestIds = UserQuest::query()
        ->where('user_id', $userId)
        ->where('is_completed', true)
        ->whereNotIn('quest_id', $baselineCompletedQuestIds)
        ->pluck('quest_id')
        ->filter()
        ->all();

    if (empty($newCompletedQuestIds)) {
        return 0;
    }

    return (int) \App\Models\Quest::query()
        ->whereIn('id', $newCompletedQuestIds)
        ->sum('reward_cash_cents');
}

try {
    if (! Schema::hasTable('scheduled_orders')) {
        throw new RuntimeException(
            'Missing required table: scheduled_orders. Run migrations first (for Sail: php artisan sail --args=artisan --args=migrate).'
        );
    }

    DB::beginTransaction();
    logInfo("=== Test Run Started: {$testRunId} ===");
    logInfo('Phase 1: Setup verification data and baseline worlds');

    $controllerWorld = createScheduledOrderWorld('controller', 100000, 4);
    $ownerWorld = createScheduledOrderWorld('owner', 100000, 4);
    $intruderWorld = createScheduledOrderWorld('intruder', 100000, 4);
    $autoSubmitWorld = createScheduledOrderWorld('auto-submit-ok', 100000);
    $insufficientFundsWorld = createScheduledOrderWorld('insufficient-funds', 500);
    $draftWorld = createScheduledOrderWorld('draft-mode', 500);
    $capacityWorld = createScheduledOrderWorld('capacity-guard', 100000, 1, 5);
    $cronWorld = createScheduledOrderWorld('cron-cadence', 100000);

    $controller = app(GameController::class);

    runScenario('Frontend Scenario Planner artifacts are wired into Phase 3 pages', function (): void {
        $plannerService = base_path('resources/js/services/scenarioPlanner.ts');
        $plannerComponent = base_path('resources/js/components/game/ScenarioPlanner.tsx');
        $orderingPage = base_path('resources/js/pages/game/ordering.tsx');
        $orderDialog = base_path('resources/js/components/game/new-order-dialog.tsx');
        $transfersPage = base_path('resources/js/pages/game/transfers.tsx');

        check('Scenario planner service exists', is_file($plannerService));
        check('Scenario planner component exists', is_file($plannerComponent));

        $serviceSource = is_file($plannerService) ? (string) file_get_contents($plannerService) : '';
        check(
            'Scenario service exports calculateScenarioPlan',
            str_contains($serviceSource, 'calculateScenarioPlan')
        );
        check(
            'Scenario service exports buildForecastProjection',
            str_contains($serviceSource, 'buildForecastProjection')
        );

        $orderingSource = is_file($orderingPage) ? (string) file_get_contents($orderingPage) : '';
        $dialogSource = is_file($orderDialog) ? (string) file_get_contents($orderDialog) : '';
        $transfersSource = is_file($transfersPage) ? (string) file_get_contents($transfersPage) : '';

        check('Ordering page renders standalone ScenarioPlanner', str_contains($orderingSource, '<ScenarioPlanner />'));
        check('Ordering dialog includes mini-calc ScenarioPlanner', str_contains($dialogSource, '<ScenarioPlanner'));
        check('Transfers page includes mini-calc ScenarioPlanner', str_contains($transfersSource, '<ScenarioPlanner'));
    });

    runScenario('Controller creates scheduled order from ordering flow', function () use ($controller, $controllerWorld): void {
        Auth::setUser($controllerWorld['user']);

        $request = Request::create('/game/orders/scheduled', 'POST', [
            'vendor_id' => $controllerWorld['vendor']->id,
            'source_location_id' => $controllerWorld['sourceLocation']->id,
            'location_id' => $controllerWorld['targetLocation']->id,
            'interval_days' => 7,
            'auto_submit' => true,
            'items' => [[
                'product_id' => $controllerWorld['product']->id,
                'quantity' => 9,
                'unit_price' => 180,
            ]],
        ]);
        $request->setUserResolver(fn () => $controllerWorld['user']);
        app()->instance('request', $request);
        $controller->storeScheduledOrder($request);

        $schedule = ScheduledOrder::where('user_id', $controllerWorld['user']->id)
            ->latest('created_at')
            ->first();

        check('Scheduled order is created', $schedule !== null);
        check('Default next_run_day is current day + 1', $schedule?->next_run_day === 5, [
            'next_run_day' => $schedule?->next_run_day,
        ]);
        check('interval_days persisted', $schedule?->interval_days === 7);
        check('auto_submit persisted', $schedule?->auto_submit === true);
    });

    runScenario('Controller enforces ownership for toggle/delete', function () use ($controller, $ownerWorld, $intruderWorld): void {
        $ownedSchedule = ScheduledOrder::create([
            'user_id' => $ownerWorld['user']->id,
            'vendor_id' => $ownerWorld['vendor']->id,
            'source_location_id' => $ownerWorld['sourceLocation']->id,
            'location_id' => $ownerWorld['targetLocation']->id,
            'items' => [[
                'product_id' => $ownerWorld['product']->id,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
            'interval_days' => 7,
            'next_run_day' => 5,
            'auto_submit' => false,
            'is_active' => true,
        ]);

        Auth::setUser($intruderWorld['user']);
        $toggleDenied = false;
        try {
            $controller->toggleScheduledOrder($ownedSchedule);
        } catch (HttpException $exception) {
            $toggleDenied = $exception->getStatusCode() === 403;
        }
        check('Intruder cannot toggle another user schedule', $toggleDenied);

        $deleteDenied = false;
        try {
            $controller->destroyScheduledOrder($ownedSchedule);
        } catch (HttpException $exception) {
            $deleteDenied = $exception->getStatusCode() === 403;
        }
        check('Intruder cannot delete another user schedule', $deleteDenied);

        Auth::setUser($ownerWorld['user']);
        $controller->toggleScheduledOrder($ownedSchedule);
        check(
            'Owner can toggle own schedule',
            $ownedSchedule->fresh()?->is_active === false
        );

        $controller->destroyScheduledOrder($ownedSchedule->fresh());
        check(
            'Owner can delete own schedule',
            ScheduledOrder::whereKey($ownedSchedule->id)->exists() === false
        );
    });

    runScenario('Auto-submit schedule creates pending order and deducts cash', function () use ($autoSubmitWorld): void {
        ScheduledOrder::create([
            'user_id' => $autoSubmitWorld['user']->id,
            'vendor_id' => $autoSubmitWorld['vendor']->id,
            'source_location_id' => $autoSubmitWorld['sourceLocation']->id,
            'location_id' => $autoSubmitWorld['targetLocation']->id,
            'items' => [[
                'product_id' => $autoSubmitWorld['product']->id,
                'quantity' => 10,
                'unit_price' => 200,
            ]],
            'interval_days' => 7,
            'next_run_day' => 2,
            'auto_submit' => true,
            'is_active' => true,
        ]);

        Auth::setUser($autoSubmitWorld['user']);
        $simulation = new SimulationService($autoSubmitWorld['gameState']);
        $cashBefore = $autoSubmitWorld['gameState']->cash;
        $baselineCompletedQuestIds = UserQuest::query()
            ->where('user_id', $autoSubmitWorld['user']->id)
            ->where('is_completed', true)
            ->pluck('quest_id')
            ->filter()
            ->all();
        $simulation->advanceTime();

        $order = Order::where('user_id', $autoSubmitWorld['user']->id)->latest()->first();
        $schedule = ScheduledOrder::where('user_id', $autoSubmitWorld['user']->id)->firstOrFail()->fresh();
        $gameState = $autoSubmitWorld['gameState']->fresh();
        $rewardDelta = calculateRewardDeltaFromNewCompletions(
            $autoSubmitWorld['user']->id,
            $baselineCompletedQuestIds
        );

        check('Order created from due auto-submit schedule', $order !== null);
        check('Auto-submitted order status is pending', $order?->status instanceof Pending, [
            'status' => $order?->status ? get_class($order->status) : null,
        ]);
        check('Schedule records last_run_day', $schedule->last_run_day === 2);
        check('Schedule advances next_run_day by interval', $schedule->next_run_day === 9);
        check('Schedule has no failure_reason on success', $schedule->failure_reason === null);
        check('Cash reflects order deduction plus any quest rewards', $order !== null && $gameState->cash === ($cashBefore - $order->total_cost + $rewardDelta), [
            'cash_before' => $cashBefore,
            'cash_after' => $gameState->cash,
            'order_total' => $order?->total_cost,
            'reward_delta' => $rewardDelta,
        ]);
    });

    runScenario('Auto-submit fails cleanly when funds are insufficient', function () use ($insufficientFundsWorld): void {
        ScheduledOrder::create([
            'user_id' => $insufficientFundsWorld['user']->id,
            'vendor_id' => $insufficientFundsWorld['vendor']->id,
            'source_location_id' => $insufficientFundsWorld['sourceLocation']->id,
            'location_id' => $insufficientFundsWorld['targetLocation']->id,
            'items' => [[
                'product_id' => $insufficientFundsWorld['product']->id,
                'quantity' => 3,
                'unit_price' => 200,
            ]],
            'interval_days' => 3,
            'next_run_day' => 2,
            'auto_submit' => true,
            'is_active' => true,
        ]);

        Auth::setUser($insufficientFundsWorld['user']);
        $simulation = new SimulationService($insufficientFundsWorld['gameState']);
        $cashBefore = $insufficientFundsWorld['gameState']->cash;
        $baselineCompletedQuestIds = UserQuest::query()
            ->where('user_id', $insufficientFundsWorld['user']->id)
            ->where('is_completed', true)
            ->pluck('quest_id')
            ->filter()
            ->all();
        $simulation->advanceTime();

        $schedule = ScheduledOrder::where('user_id', $insufficientFundsWorld['user']->id)->firstOrFail()->fresh();
        $gameState = $insufficientFundsWorld['gameState']->fresh();
        $rewardDelta = calculateRewardDeltaFromNewCompletions(
            $insufficientFundsWorld['user']->id,
            $baselineCompletedQuestIds
        );

        check('No order is created when funds are insufficient', Order::where('user_id', $insufficientFundsWorld['user']->id)->count() === 0);
        check('Insufficient funds path does not deduct order cash (except quest rewards)', $gameState->cash === ($cashBefore + $rewardDelta), [
            'cash_before' => $cashBefore,
            'cash' => $gameState->cash,
            'reward_delta' => $rewardDelta,
        ]);
        check('Failure reason mentions insufficient funds', str_contains((string) $schedule->failure_reason, 'Insufficient funds'), [
            'failure_reason' => $schedule->failure_reason,
        ]);
        check('Schedule still advances to next cadence after failure', $schedule->next_run_day === 5);
    });

    runScenario('Non-auto-submit schedule creates draft order without cash deduction', function () use ($draftWorld): void {
        ScheduledOrder::create([
            'user_id' => $draftWorld['user']->id,
            'vendor_id' => $draftWorld['vendor']->id,
            'source_location_id' => $draftWorld['sourceLocation']->id,
            'location_id' => $draftWorld['targetLocation']->id,
            'items' => [[
                'product_id' => $draftWorld['product']->id,
                'quantity' => 3,
                'unit_price' => 200,
            ]],
            'interval_days' => 3,
            'next_run_day' => 2,
            'auto_submit' => false,
            'is_active' => true,
        ]);

        Auth::setUser($draftWorld['user']);
        $simulation = new SimulationService($draftWorld['gameState']);
        $cashBefore = $draftWorld['gameState']->cash;
        $baselineCompletedQuestIds = UserQuest::query()
            ->where('user_id', $draftWorld['user']->id)
            ->where('is_completed', true)
            ->pluck('quest_id')
            ->filter()
            ->all();
        $simulation->advanceTime();

        $order = Order::where('user_id', $draftWorld['user']->id)->latest()->first();
        $gameState = $draftWorld['gameState']->fresh();
        $rewardDelta = calculateRewardDeltaFromNewCompletions(
            $draftWorld['user']->id,
            $baselineCompletedQuestIds
        );

        check('Draft order is created for non-auto-submit schedule', $order !== null);
        check('Order status is draft when auto_submit is false', $order?->status instanceof Draft, [
            'status' => $order?->status ? get_class($order->status) : null,
        ]);
        check('Draft execution does not deduct order cash (except quest rewards)', $gameState->cash === ($cashBefore + $rewardDelta), [
            'cash_before' => $cashBefore,
            'cash' => $gameState->cash,
            'reward_delta' => $rewardDelta,
        ]);
    });

    runScenario('Auto-submit enforces route capacity guard', function () use ($capacityWorld): void {
        ScheduledOrder::create([
            'user_id' => $capacityWorld['user']->id,
            'vendor_id' => $capacityWorld['vendor']->id,
            'source_location_id' => $capacityWorld['sourceLocation']->id,
            'location_id' => $capacityWorld['targetLocation']->id,
            'items' => [[
                'product_id' => $capacityWorld['product']->id,
                'quantity' => 9,
                'unit_price' => 200,
            ]],
            'interval_days' => 2,
            'next_run_day' => 2,
            'auto_submit' => true,
            'is_active' => true,
        ]);

        Auth::setUser($capacityWorld['user']);
        $simulation = new SimulationService($capacityWorld['gameState']);
        $simulation->advanceTime();

        $schedule = ScheduledOrder::where('user_id', $capacityWorld['user']->id)->firstOrFail()->fresh();

        check('Capacity guard prevents order creation', Order::where('user_id', $capacityWorld['user']->id)->count() === 0);
        check('Capacity failure reason is recorded', str_contains((string) $schedule->failure_reason, 'Route capacity'), [
            'failure_reason' => $schedule->failure_reason,
        ]);
        check('Capacity failure still advances next_run_day', $schedule->next_run_day === 4, [
            'next_run_day' => $schedule->next_run_day,
        ]);
    });

    runScenario('Cron cadence fallback (@every Nd) advances schedule cursor', function () use ($cronWorld): void {
        ScheduledOrder::create([
            'user_id' => $cronWorld['user']->id,
            'vendor_id' => $cronWorld['vendor']->id,
            'source_location_id' => $cronWorld['sourceLocation']->id,
            'location_id' => $cronWorld['targetLocation']->id,
            'items' => [[
                'product_id' => $cronWorld['product']->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
            'interval_days' => null,
            'cron_expression' => '@every 2d',
            'next_run_day' => 2,
            'auto_submit' => false,
            'is_active' => true,
        ]);

        Auth::setUser($cronWorld['user']);
        $simulation = new SimulationService($cronWorld['gameState']);
        $simulation->advanceTime(); // day 2 (due)
        $scheduleDay2 = ScheduledOrder::where('user_id', $cronWorld['user']->id)->firstOrFail()->fresh();

        check('Cron schedule runs on first due day', Order::where('user_id', $cronWorld['user']->id)->count() === 1);
        check('Cron schedule sets next_run_day using 2-day interval', $scheduleDay2->next_run_day === 4, [
            'next_run_day' => $scheduleDay2->next_run_day,
        ]);

        $simulation->advanceTime(); // day 3 (not due)
        check('Cron schedule does not run on non-due day', Order::where('user_id', $cronWorld['user']->id)->count() === 1);

        $simulation->advanceTime(); // day 4 (due again)
        $scheduleDay4 = ScheduledOrder::where('user_id', $cronWorld['user']->id)->firstOrFail()->fresh();

        check('Cron schedule runs again on second due day', Order::where('user_id', $cronWorld['user']->id)->count() === 2);
        check('Cron schedule keeps advancing by 2 days', $scheduleDay4->next_run_day === 6, [
            'next_run_day' => $scheduleDay4->next_run_day,
        ]);
    });

} catch (\Throwable $throwable) {
    $failed++;
    logError('Fatal verification error', [
        'message' => $throwable->getMessage(),
        'file' => $throwable->getFile(),
        'line' => $throwable->getLine(),
    ]);
} finally {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    logInfo("=== Test Run Completed: {$testRunId} ===", [
        'passed' => $passed,
        'failed' => $failed,
        'log_file' => $logFile,
    ]);

    echo "\n=== Results: {$passed} passed, {$failed} failed ===\n";
    echo "Database rollback complete.\n";
    echo "Full logs: {$logFile}\n";
}

exit($failed > 0 ? 1 : 0);
