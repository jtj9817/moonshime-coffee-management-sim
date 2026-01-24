<?php

use App\Models\User;
use App\Models\GameState;
use App\Models\Location;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

info("=== Starting Analytics Data Integrity Verification ===");

// 1. Setup
info("1. Setting up test data...");
$user = User::factory()->create([
    'email' => 'analytics_debug_' . uniqid() . '@example.com',
]);
$gameState = GameState::factory()->create(['user_id' => $user->id, 'day' => 1]);

$location = Location::factory()->create(['name' => 'Debug Warehouse', 'max_storage' => 1000]);
$product = Product::factory()->create(['name' => 'Debug Coffee', 'unit_price' => 15.50]);

// Seed Initial Inventory
Inventory::factory()->create([
    'user_id' => $user->id,
    'location_id' => $location->id,
    'product_id' => $product->id,
    'quantity' => 100,
]);

info("   User created: {$user->email}");
info("   Initial Inventory: 100 units of {$product->name} at {$location->name}");

// 2. Simulate Time Passing (to generate history)
info("2. Simulating 3 days of history...");
$historyData = [
    ['day' => 1, 'quantity' => 100],
    ['day' => 2, 'quantity' => 90], // Sold 10
    ['day' => 3, 'quantity' => 150], // Restocked 60
];

foreach ($historyData as $data) {
    DB::table('inventory_history')->insert([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'day' => $data['day'],
        'quantity' => $data['quantity'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// 3. Verify getInventoryTrends
info("3. Verifying getInventoryTrends...");
$controller = app(\App\Http\Controllers\GameController::class);

// Mock authentication
auth()->login($user);

// Use reflection to access protected method
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('getInventoryTrends');
$method->setAccessible(true);
$trends = $method->invoke($controller);

if (count($trends) === 3) {
    info("   ✅ Trends count correct (3 days).");
} else {
    error("   ❌ Trends count incorrect. Expected 3, got " . count($trends));
}

$day3 = collect($trends)->firstWhere('day', 3);
if ($day3 && $day3['value'] === 150) {
    info("   ✅ Day 3 value correct (150).");
} else {
    error("   ❌ Day 3 value incorrect. Expected 150, got " . ($day3['value'] ?? 'null'));
}

// 4. Verify getLocationComparison
info("4. Verifying getLocationComparison...");
$methodLoc = $reflection->getMethod('getLocationComparison');
$methodLoc->setAccessible(true);
$comparison = $methodLoc->invoke($controller);

$locData = collect($comparison)->firstWhere('name', 'Debug Warehouse');

if ($locData) {
    info("   ✅ Location found.");
    
    // Inventory Value: 100 units (current inventory) * 15.50 = 1550
    $expectedValue = 1550.0;
    if (abs($locData['inventoryValue'] - $expectedValue) < 0.01) {
         info("   ✅ Inventory Value correct ($1550.00).");
    } else {
         error("   ❌ Inventory Value incorrect. Expected {$expectedValue}, got {$locData['inventoryValue']}");
    }

    // Utilization: 100 / 1000 = 10%
    if ($locData['utilization'] === 10.0) {
         info("   ✅ Utilization correct (10%).");
    } else {
         error("   ❌ Utilization incorrect. Expected 10.0, got {$locData['utilization']}");
    }
} else {
    error("   ❌ Location not found in comparison data.");
}

// 5. Teardown
info("5. Cleaning up...");
// DB rollback handled by test environment usually, but manually cleaning up if needed
// For this script, we rely on the fact it's running in a transient environment or we explicitly delete
// But manual scripts in `tests/manual` should preferably self-clean.
Inventory::where('user_id', $user->id)->delete();
DB::table('inventory_history')->where('user_id', $user->id)->delete();
GameState::where('user_id', $user->id)->delete();
$user->delete();
$location->delete();
$product->delete();

info("=== Verification Complete ===");
