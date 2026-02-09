<?php

use App\Actions\InitializeNewGame;
use App\Http\Controllers\GameController;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '--- Starting Manual Verification: Backend Reset Endpoint ---\\n';

// 1. Setup
echo '[1] Setting up dirty state...\\n';
$user = User::factory()->create(['email' => 'reset_tester_'.uniqid().'@example.com']);
Auth::login($user);

// Create or update GameState (factory might create one via user observer if exists, so use firstOrCreate or update)
$gameState = GameState::firstOrCreate(['user_id' => $user->id]);
$gameState->update([
    'day' => 100,
    'cash' => 500,
    'xp' => 9999,
]);

// Create some dirty data
Order::factory()->count(5)->create(['user_id' => $user->id]);
// Create a specific inventory item to track
$inventory = Inventory::factory()->create([
    'user_id' => $user->id,
    'quantity' => 999,
    'product_id' => \App\Models\Product::first()->id,
    'location_id' => \App\Models\Location::first()->id,
]);

echo "    User ID: {$user->id}\\n";
echo "    Current Day: {$gameState->day}\\n";
echo "    Current Cash: {$gameState->cash}\\n";
echo '    Orders: '.Order::where('user_id', $user->id)->count().'\\n';
echo '    Inventory Item Quantity: '.$inventory->quantity.'\\n';

// 2. Execute Reset
echo '\\n[2] Executing Reset...\\n';
try {
    $controller = app(GameController::class);
    $initializer = app(InitializeNewGame::class);

    // We are mocking the request handling by calling the method directly
    // This assumes the method logic doesn't depend on Request object properties other than auth
    $response = $controller->resetGame($initializer);

    echo '    Reset call completed. Response status: '.$response->getStatusCode().'\\n';
} catch (\Exception $e) {
    echo '    ERROR: '.$e->getMessage().'\\n';
    exit(1);
}

// 3. Verify
echo '\\n[3] Verifying Clean State...\\n';
$gameState->refresh();

$dayOk = $gameState->day === 1;
$cashOk = (int) $gameState->cash === 1000000;
// InitializeNewGame might seed some orders (e.g. In Transit), so count might not be 0.
// But our "dirty" orders should be gone.
// Since we didn't track IDs, we just check if count is consistent with a fresh game (usually low < 5)
// and specifically that our previous state is gone.
// Actually, InitializeNewGame seeds specific pipeline activity.
$ordersCount = Order::where('user_id', $user->id)->count();
$ordersCleared = $ordersCount < 5; // We created 5 dirty ones. Fresh game usually has 0 or 1.

// Check specific inventory item we created
$inventoryReset = Inventory::where('user_id', $user->id)
    ->where('product_id', $inventory->product_id)
    ->where('location_id', $inventory->location_id)
    ->first();

$inventoryResetOk = $inventoryReset && $inventoryReset->quantity != 999;

echo '    Day is 1: '.($dayOk ? 'PASS' : "FAIL ({$gameState->day})").'\\n';
echo '    Cash is 1M: '.($cashOk ? 'PASS' : "FAIL ({$gameState->cash})").'\\n';
echo "    Orders Count: $ordersCount ".($ordersCleared ? 'PASS' : 'FAIL').'\\n';
echo '    Inventory Reset: '.($inventoryResetOk ? "PASS ({$inventoryReset->quantity})" : 'FAIL').'\\n';

if ($dayOk && $cashOk && $ordersCleared && $inventoryResetOk) {
    echo '\\nVERIFICATION SUCCESSFUL\\n';

    // Cleanup
    echo '[4] Cleaning up...\\n';
    $user->delete();
} else {
    echo '\\nVERIFICATION FAILED\\n';
    exit(1);
}
