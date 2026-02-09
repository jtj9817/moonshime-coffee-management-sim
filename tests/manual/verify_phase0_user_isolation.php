<?php

/**
 * Manual Test: Phase 0 Global User Isolation Verification
 * Track: conductor/tracks/arch_remediation_20260207/plan.md
 *
 * Verifies:
 * - Middleware shared props are user-scoped.
 * - Dashboard/list/analytics controller responses are user-scoped.
 * - Cross-user alert mutation is blocked (403).
 * - Logistics route spike effects are scoped by user.
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

use App\Http\Controllers\GameController;
use App\Http\Controllers\LogisticsController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Alert;
use App\Models\DemandEvent;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LogisticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

$testRunId = 'phase0_isolation_'.Carbon::now()->format('Y_m_d_His');
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

function getInertiaProps(object $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return (array) $property->getValue($response);
}

function actingAs(User $user): void
{
    auth()->guard()->setUser($user);
}

function seedUserDataset(User $user, string $prefix): array
{
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => 500000,
        'day' => 5,
    ]);

    $store = Location::factory()->create([
        'name' => "{$prefix} Store",
        'type' => 'store',
        'max_storage' => 1000,
    ]);
    $target = Location::factory()->create([
        'name' => "{$prefix} Target",
        'type' => 'store',
        'max_storage' => 1000,
    ]);
    $product = Product::factory()->create(['name' => "{$prefix} Beans"]);
    $vendor = Vendor::factory()->create(['name' => "{$prefix} Vendor"]);

    $inventory = Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $store->id,
        'product_id' => $product->id,
        'quantity' => 120,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $store->id,
        'status' => 'pending',
        'total_cost' => 5000,
        'created_day' => 4,
        'delivery_day' => 6,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'cost_per_unit' => 100,
    ]);

    $transfer = Transfer::factory()->create([
        'user_id' => $user->id,
        'source_location_id' => $store->id,
        'target_location_id' => $target->id,
        'product_id' => $product->id,
        'quantity' => 8,
    ]);

    $alert = Alert::factory()->create([
        'user_id' => $user->id,
        'type' => 'isolation_probe',
        'message' => "{$prefix} alert",
        'severity' => 'critical',
        'is_read' => false,
    ]);

    $spike = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'demand',
        'magnitude' => 1.5,
        'duration' => 3,
        'starts_at_day' => 4,
        'ends_at_day' => 7,
        'is_active' => true,
    ]);

    $demandEvent = DemandEvent::factory()->create([
        'user_id' => $user->id,
        'day' => 5,
        'location_id' => $store->id,
        'product_id' => $product->id,
        'unit_price' => 350,
        'revenue' => $prefix === 'Alice' ? 1300 : 9100,
        'lost_revenue' => 0,
    ]);

    return compact(
        'gameState',
        'store',
        'target',
        'product',
        'vendor',
        'inventory',
        'order',
        'transfer',
        'alert',
        'spike',
        'demandEvent'
    );
}

$results = ['passed' => 0, 'failed' => 0];
$transactionStarted = false;

try {
    DB::beginTransaction();
    $transactionStarted = true;
    logInfo("=== Starting Phase 0 User Isolation Verification: {$testRunId} ===");

    $alice = User::factory()->create(['name' => 'Isolation Alice']);
    $bob = User::factory()->create(['name' => 'Isolation Bob']);
    $aliceData = seedUserDataset($alice, 'Alice');
    $bobData = seedUserDataset($bob, 'Bob');

    runCheck('middleware shared game props are user-scoped', function () use ($alice, $bob): void {
        $middleware = app(HandleInertiaRequests::class);
        $request = Request::create('/game/dashboard', 'GET');
        $request->setUserResolver(static fn () => $alice);

        $shared = $middleware->share($request);
        $game = $shared['game']();

        $alertsUserIds = collect($game['alerts'])->pluck('user_id')->unique()->values()->all();
        $spikeUserIds = collect($game['activeSpikes'])->pluck('user_id')->unique()->values()->all();

        assertCondition($alertsUserIds === [$alice->id], 'Middleware alerts should contain only Alice data', ['user_ids' => $alertsUserIds]);
        assertCondition($spikeUserIds === [$alice->id], 'Middleware spikes should contain only Alice data', ['user_ids' => $spikeUserIds]);
        assertCondition(! in_array($bob->id, $alertsUserIds, true), 'Middleware alerts leaked Bob data');
        assertCondition(! in_array($bob->id, $spikeUserIds, true), 'Middleware spikes leaked Bob data');
    }, $results);

    runCheck('inventory page returns only authenticated user inventory', function () use ($alice, $bob): void {
        actingAs($alice);
        $props = getInertiaProps(app(GameController::class)->inventory(Request::create('/game/inventory', 'GET')));
        $userIds = collect($props['inventory'])->pluck('user_id')->unique()->values()->all();

        assertCondition($userIds === [$alice->id], 'Inventory page leaked non-Alice user IDs', ['user_ids' => $userIds]);
        assertCondition(! in_array($bob->id, $userIds, true), 'Inventory page leaked Bob inventory');
    }, $results);

    runCheck('ordering page returns only authenticated user orders', function () use ($alice, $bob): void {
        actingAs($alice);
        $props = getInertiaProps(app(GameController::class)->ordering());
        $userIds = collect($props['orders'])->pluck('user_id')->unique()->values()->all();

        assertCondition($userIds === [$alice->id], 'Ordering page leaked non-Alice user IDs', ['user_ids' => $userIds]);
        assertCondition(! in_array($bob->id, $userIds, true), 'Ordering page leaked Bob orders');
    }, $results);

    runCheck('transfers page returns only authenticated user transfers', function () use ($alice, $bob): void {
        actingAs($alice);
        $props = getInertiaProps(app(GameController::class)->transfers());
        $userIds = collect($props['transfers'])->pluck('user_id')->unique()->values()->all();

        assertCondition($userIds === [$alice->id], 'Transfers page leaked non-Alice user IDs', ['user_ids' => $userIds]);
        assertCondition(! in_array($bob->id, $userIds, true), 'Transfers page leaked Bob transfers');
    }, $results);

    runCheck('sku detail does not leak another user inventory row', function () use ($alice, $bob): void {
        $sharedLocation = Location::factory()->create(['name' => 'Shared Isolation Location']);
        $sharedProduct = Product::factory()->create(['name' => 'Shared Isolation Product']);

        Inventory::factory()->create([
            'user_id' => $bob->id,
            'location_id' => $sharedLocation->id,
            'product_id' => $sharedProduct->id,
            'quantity' => 999,
        ]);

        actingAs($alice);
        $props = getInertiaProps(app(GameController::class)->skuDetail($sharedLocation, $sharedProduct));

        assertCondition($props['inventory'] === null, 'SKU detail returned another user inventory record');
    }, $results);

    runCheck('vendors page order count and averages are user-scoped', function () use ($alice, $bob): void {
        $sharedVendor = Vendor::factory()->create(['name' => 'Shared Isolation Vendor']);
        $aliceLocation = Location::factory()->create(['type' => 'store']);
        $bobLocation = Location::factory()->create(['type' => 'store']);

        Order::create([
            'user_id' => $alice->id,
            'vendor_id' => $sharedVendor->id,
            'location_id' => $aliceLocation->id,
            'status' => 'pending',
            'total_cost' => 1100,
        ]);
        Order::create([
            'user_id' => $bob->id,
            'vendor_id' => $sharedVendor->id,
            'location_id' => $bobLocation->id,
            'status' => 'pending',
            'total_cost' => 9900,
        ]);

        actingAs($alice);
        $props = getInertiaProps(app(GameController::class)->vendors());
        $sharedVendorData = collect($props['vendors'])->firstWhere('id', $sharedVendor->id);

        assertCondition($sharedVendorData !== null, 'Shared vendor missing from response');
        assertCondition((int) $sharedVendorData['orders_count'] === 1, 'orders_count should include only Alice order', ['actual' => $sharedVendorData['orders_count']]);
        assertCondition((float) $sharedVendorData['orders_avg_total_cost'] === 1100.0, 'orders_avg_total_cost should include only Alice order', ['actual' => $sharedVendorData['orders_avg_total_cost']]);
    }, $results);

    runCheck('vendor detail orders and metrics are user-scoped', function () use ($alice, $bob): void {
        $sharedVendor = Vendor::factory()->create(['name' => 'Shared Isolation Vendor Detail']);
        $aliceLocation = Location::factory()->create(['type' => 'store']);
        $bobLocation = Location::factory()->create(['type' => 'store']);

        Order::create([
            'user_id' => $alice->id,
            'vendor_id' => $sharedVendor->id,
            'location_id' => $aliceLocation->id,
            'status' => 'pending',
            'total_cost' => 2200,
        ]);
        Order::create([
            'user_id' => $bob->id,
            'vendor_id' => $sharedVendor->id,
            'location_id' => $bobLocation->id,
            'status' => 'pending',
            'total_cost' => 8800,
        ]);

        actingAs($alice);
        $props = getInertiaProps(app(GameController::class)->vendorDetail($sharedVendor));
        $orderUserIds = collect($props['vendor']['orders'])->pluck('user_id')->unique()->values()->all();

        assertCondition($orderUserIds === [$alice->id], 'Vendor detail leaked other user orders', ['user_ids' => $orderUserIds]);
        assertCondition((int) $props['metrics']['totalOrders'] === 1, 'Vendor metrics totalOrders should be scoped', ['actual' => $props['metrics']['totalOrders']]);
        assertCondition((float) $props['metrics']['totalSpent'] === 2200.0, 'Vendor metrics totalSpent should be scoped', ['actual' => $props['metrics']['totalSpent']]);
    }, $results);

    runCheck('analytics aggregates are user-scoped', function () use ($alice): void {
        actingAs($alice);
        $props = getInertiaProps(app(GameController::class)->analytics());

        // Alice revenue in 7-day window is seeded as 1300; Bob has 9100 and must not leak.
        $revenue7Day = $props['overviewMetrics']['revenue7Day'] ?? null;

        assertCondition((float) $revenue7Day === 1300.0, 'Analytics revenue7Day leaked other user demand data', ['actual' => $revenue7Day]);
    }, $results);

    runCheck('markAlertRead blocks cross-user mutation with 403', function () use ($alice, $bobData): void {
        actingAs($alice);

        try {
            app(GameController::class)->markAlertRead($bobData['alert']);
            throw new RuntimeException('Expected markAlertRead to throw 403 for cross-user alert access');
        } catch (HttpException $e) {
            assertCondition($e->getStatusCode() === 403, 'Expected HTTP 403 from markAlertRead', ['status' => $e->getStatusCode()]);
        }
    }, $results);

    runCheck('logistics routes API hides other user spikes', function () use ($alice, $bob): void {
        $source = Location::factory()->create(['type' => 'warehouse']);
        $target = Location::factory()->create(['type' => 'store']);
        $route = Route::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'cost' => 100,
            'is_active' => true,
        ]);

        SpikeEvent::create([
            'user_id' => $bob->id,
            'type' => 'blizzard',
            'affected_route_id' => $route->id,
            'magnitude' => 2.0,
            'duration' => 3,
            'starts_at_day' => 2,
            'ends_at_day' => 5,
            'is_active' => true,
        ]);

        actingAs($alice);
        $request = Request::create('/game/logistics/routes', 'GET', ['source_id' => $source->id]);
        $json = app(LogisticsController::class)->getRoutes($request)->getData(true);
        $routeData = collect($json['data'] ?? [])->firstWhere('id', $route->id);

        assertCondition($routeData !== null, 'Route not found in logistics response');
        assertCondition($routeData['blocked_reason'] === null, 'Expected blocked_reason to be null for Alice');
        assertCondition((int) $routeData['cost'] === 100, 'Expected route cost to remain base cost for Alice', ['actual' => $routeData['cost']]);
    }, $results);

    runCheck('LogisticsService forUser context scopes spike multipliers', function () use ($alice, $bob): void {
        $source = Location::factory()->create(['type' => 'warehouse']);
        $target = Location::factory()->create(['type' => 'store']);
        $route = Route::factory()->create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'cost' => 100,
            'is_active' => true,
        ]);

        SpikeEvent::create([
            'user_id' => $bob->id,
            'type' => 'blizzard',
            'affected_route_id' => $route->id,
            'magnitude' => 2.0,
            'duration' => 2,
            'starts_at_day' => 1,
            'ends_at_day' => 3,
            'is_active' => true,
        ]);

        $service = app(LogisticsService::class);
        $aliceCost = $service->forUser($alice->id)->calculateCost($route);
        $bobCost = $service->forUser($bob->id)->calculateCost($route);

        assertCondition($aliceCost === 100, 'Alice should not be affected by Bob spike', ['actual' => $aliceCost]);
        assertCondition($bobCost === 300, 'Bob should see spike-inflated route cost', ['actual' => $bobCost]);
    }, $results);

    logInfo('Phase 0 isolation verification finished', $results);
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
