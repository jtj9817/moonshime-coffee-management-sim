<?php

use App\Actions\InitializeNewGame;
use App\Http\Controllers\GameController;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Route;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "--- Regression: Reset Game Missing Seed Data ---\n";
echo "WARNING: This script deletes locations/products/vendors/routes/product_vendor.\n\n";

$databaseName = DB::connection()->getDatabaseName();
echo "Database: {$databaseName}\n\n";

$purgeSeedData = function (): void {
    Schema::disableForeignKeyConstraints();

    DB::table('product_vendor')->delete();
    Inventory::query()->delete();
    Transfer::query()->delete();
    Order::query()->delete();
    Route::query()->delete();
    Location::query()->delete();
    Product::query()->delete();
    Vendor::query()->delete();

    Schema::enableForeignKeyConstraints();
};

$resetUserState = function (User $user): void {
    GameState::updateOrCreate(
        ['user_id' => $user->id],
        ['day' => 10, 'cash' => 5.00, 'xp' => 0]
    );
};

$runReset = function (string $label, User $user) use ($resetUserState): void {
    $resetUserState($user);

    Auth::login($user);

    $controller = app(GameController::class);
    $initializer = app(InitializeNewGame::class);

    $response = $controller->resetGame($initializer);
    $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 'n/a';

    echo "    {$label} reset completed. Response status: {$status}\n";
};

$runResetExpectFailure = function (string $label, User $user, string $expectedSubstring) use ($runReset): void {
    echo "[{$label}] Expect failure containing: {$expectedSubstring}\n";

    try {
        $runReset($label, $user);
        echo "    FAIL: reset succeeded unexpectedly.\n\n";
    } catch (\RuntimeException $e) {
        $message = $e->getMessage();
        $result = str_contains($message, $expectedSubstring) ? 'PASS' : 'FAIL';
        echo "    {$result}: {$message}\n\n";
    } catch (\Throwable $e) {
        echo '    FAIL: unexpected exception type '.get_class($e)." - {$e->getMessage()}\n\n";
    }
};

$runResetExpectSuccess = function (string $label, User $user) use ($runReset): void {
    echo "[{$label}] Expect success\n";

    try {
        $runReset($label, $user);

        $gameState = GameState::where('user_id', $user->id)->first();
        $inventoryCount = Inventory::where('user_id', $user->id)->count();

        $dayOk = $gameState && $gameState->day === 1;
        $inventoryOk = $inventoryCount > 0;

        echo '    Day reset to 1: '.($dayOk ? 'PASS' : 'FAIL')."\n";
        echo '    Inventory seeded: '.($inventoryOk ? 'PASS' : "FAIL ({$inventoryCount})")."\n\n";
    } catch (\Throwable $e) {
        echo "    FAIL: reset failed unexpectedly - {$e->getMessage()}\n\n";
    }
};

$user = User::factory()->create([
    'email' => 'reset_regress_'.uniqid().'@example.com',
]);

echo "User ID: {$user->id}\n\n";

// Step 0: clear global seed data to guarantee missing dependencies
$purgeSeedData();

// Step 1: No locations -> should fail with "No stores found"
$runResetExpectFailure('1', $user, 'No stores found');

// Step 2: Add store + warehouse only -> should fail with "No products found"
Location::factory()->create(['type' => 'store']);
Location::factory()->create(['type' => 'warehouse']);
$runResetExpectFailure('2', $user, 'No products found');

// Step 3: Add products only -> should fail with "No vendor found"
Product::factory()->count(2)->create();
$runResetExpectFailure('3', $user, 'No vendor found');

// Step 4: Add vendor (+ vendor location) -> should succeed
Vendor::factory()->create();
Location::factory()->create(['type' => 'vendor']);
$runResetExpectSuccess('4', $user);

echo "Regression run complete.\n";
