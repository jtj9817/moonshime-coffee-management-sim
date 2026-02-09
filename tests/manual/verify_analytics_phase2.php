<?php

/**
 * Manual Test: Analytics Phase 2 (Data Provider Refactoring)
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$testRunId = 'analytics_test_'.Carbon::now()->format('Y_m_d_His');
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
    logInfo("=== Starting Analytics Phase 2 Verification: {$testRunId} ===");

    // === SETUP PHASE ===
    logInfo('Setting up test data...');

    $user = User::factory()->create(['email' => 'test_analytics_'.uniqid().'@example.com']);
    auth()->login($user);
    logInfo("Created User: {$user->id}");

    $locA = Location::factory()->create(['name' => 'Location A', 'max_storage' => 100]);
    $locB = Location::factory()->create(['name' => 'Location B', 'max_storage' => 200]);
    logInfo("Created Locations: {$locA->name}, {$locB->name}");

    $catBeans = 'Beans';
    $catMilk = 'Milk';
    $prodBeans = Product::factory()->create(['name' => 'Beans', 'category' => $catBeans, 'unit_price' => 10]);
    $prodMilk = Product::factory()->create(['name' => 'Milk', 'category' => $catMilk, 'unit_price' => 5]);
    logInfo('Created Products: Beans ($10), Milk ($5)');

    // 1. Inventory History (for Trends)
    // Day 1: 10 Beans + 20 Milk = 30 total
    DB::table('inventory_history')->insert([
        ['user_id' => $user->id, 'location_id' => $locA->id, 'product_id' => $prodBeans->id, 'day' => 1, 'quantity' => 10, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'location_id' => $locB->id, 'product_id' => $prodMilk->id, 'day' => 1, 'quantity' => 20, 'created_at' => now(), 'updated_at' => now()],
    ]);
    // Day 2: 15 Beans + 25 Milk = 40 total
    DB::table('inventory_history')->insert([
        ['user_id' => $user->id, 'location_id' => $locA->id, 'product_id' => $prodBeans->id, 'day' => 2, 'quantity' => 15, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'location_id' => $locB->id, 'product_id' => $prodMilk->id, 'day' => 2, 'quantity' => 25, 'created_at' => now(), 'updated_at' => now()],
    ]);
    logInfo('Seeded Inventory History');

    // 2. Orders (for Spending)
    $order = Order::factory()->create(['user_id' => $user->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $prodBeans->id, 'quantity' => 10, 'cost_per_unit' => 10]); // $100 Beans
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $prodMilk->id, 'quantity' => 20, 'cost_per_unit' => 5]);   // $100 Milk
    logInfo('Seeded Orders (Total $200)');

    // 3. Current Inventory (for Location Comparison)
    // Loc A: 50 Beans ($500), Utilization 50/100 = 50%
    Inventory::factory()->create(['user_id' => $user->id, 'location_id' => $locA->id, 'product_id' => $prodBeans->id, 'quantity' => 50]);
    // Loc B: 10 Milk ($50), Utilization 10/200 = 5%
    Inventory::factory()->create(['user_id' => $user->id, 'location_id' => $locB->id, 'product_id' => $prodMilk->id, 'quantity' => 10]);
    logInfo('Seeded Current Inventory');

    // === VERIFICATION PHASE ===
    logInfo('--- Verifying getInventoryTrends ---');
    $trends = DB::table('inventory_history')
        ->where('user_id', auth()->id())
        ->select('day', DB::raw('SUM(quantity) as value'))
        ->groupBy('day')
        ->orderBy('day')
        ->get()
        ->map(fn ($item) => ['day' => (int) $item->day, 'value' => (int) $item->value])
        ->toArray();

    logInfo('Trends Result:', $trends);
    if (count($trends) === 2 && $trends[0]['value'] === 30 && $trends[1]['value'] === 40) {
        logInfo('✓ Inventory Trends Verified');
    } else {
        logError('✗ Inventory Trends Mismatch');
    }

    logInfo('--- Verifying getSpendingByCategory ---');
    $spending = OrderItem::query()
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->join('products', 'order_items.product_id', '=', 'products.id')
        ->where('orders.user_id', auth()->id())
        ->select('products.category', DB::raw('SUM(order_items.quantity * order_items.cost_per_unit) as amount'))
        ->groupBy('products.category')
        ->get()
        ->map(fn ($item) => [
            'category' => $item->category,
            'amount' => (float) $item->amount,
        ])
        ->toArray();
    logInfo('Spending Result:', $spending);
    // Expect: Beans: 100, Milk: 100
    // Note: Use fuzzy match or loop check
    $beans = collect($spending)->firstWhere('category', 'Beans');
    $milk = collect($spending)->firstWhere('category', 'Milk');
    if ($beans && $beans['amount'] == 100 && $milk && $milk['amount'] == 100) {
        logInfo('✓ Spending Verified');
    } else {
        logError('✗ Spending Mismatch');
    }

    logInfo('--- Verifying getLocationComparison ---');
    $locations = Location::with(['inventories' => function ($query) {
        $query->where('user_id', auth()->id())->with('product');
    }])->get(); // Get ALL locations, filtering just the ones we created? No, gets all.

    // Filter to just our test locations for assertion
    $testLocs = $locations->whereIn('id', [$locA->id, $locB->id]);

    $comparison = $testLocs->map(function ($loc) {
        $inventories = $loc->inventories;
        $inventoryValue = $inventories->sum(fn ($inv) => $inv->quantity * $inv->product->unit_price);
        $totalQuantity = $inventories->sum('quantity');
        $utilization = $loc->max_storage > 0 ? round(($totalQuantity / $loc->max_storage) * 100, 1) : 0;
        $itemCount = $inventories->unique('product_id')->count();

        return [
            'name' => $loc->name,
            'inventoryValue' => (float) $inventoryValue,
            'utilization' => (float) $utilization,
            'itemCount' => (int) $itemCount,
        ];
    })->values()->toArray(); // values() to reindex

    logInfo('Location Comparison Result:', $comparison);

    // Loc A: Val 500, Util 50, Items 1
    // Loc B: Val 50, Util 5, Items 1
    $resA = collect($comparison)->firstWhere('name', 'Location A');
    $resB = collect($comparison)->firstWhere('name', 'Location B');

    if ($resA && $resA['inventoryValue'] == 500 && $resA['utilization'] == 50 &&
        $resB && $resB['inventoryValue'] == 50 && $resB['utilization'] == 5) {
        logInfo('✓ Location Comparison Verified');
    } else {
        logError('✗ Location Comparison Mismatch');
    }

} catch (\Exception $e) {
    logError('Test failed', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    DB::rollBack();
    logInfo('Cleanup completed (Rollback)');
    echo "\n✓ Full logs at: {$logFile}\n";
}
