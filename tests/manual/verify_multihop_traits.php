<?php
/**
 * Manual Test: Multi-Hop Scenario Builder Trait Verification
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Location;
use App\Models\Route;
use App\Models\Product;
use App\Models\GameState;
use App\Models\Vendor;
use Tests\Traits\MultiHopScenarioBuilder;

// Prevent production execution
if (app()->environment('production')) {
    die("Error: Cannot run manual tests in production!\n");
}

function logInfo($msg) {
    echo "[INFO] {$msg}\n";
}

function logError($msg) {
    echo "[ERROR] {$msg}\n";
}

// Wrapper class to expose protected trait methods
class ScenarioBuilderWrapper {
    use MultiHopScenarioBuilder;

    public function testCreateVendorPath(array $locations) {
        $this->createVendorPath($locations);
    }

    public function testCreateRoutes(array $configs) {
        $this->createRoutes($configs);
    }

    public function testCreateProductBundle(array $products) {
        $this->createProductBundle($products);
    }

    public function testCreateGameState(User $user, float $cash) {
        return $this->createGameState($user, $cash);
    }

    public function getResolvedId(string $alias, string $model) {
        return $this->resolveId($alias, $model);
    }
}

try {
    DB::beginTransaction();
    logInfo("=== Starting Manual Test: Multi-Hop Scenario Builder ===");

    $builder = new ScenarioBuilderWrapper();
    $user = User::factory()->create(['email' => 'builder_test@example.com']);

    // 1. Test createVendorPath
    logInfo("Testing createVendorPath...");
    $locations = ['loc-a', 'loc-b', 'loc-hq'];
    $builder->testCreateVendorPath($locations);

    // Resolve IDs
    $idA = $builder->getResolvedId('loc-a', Location::class);
    $idB = $builder->getResolvedId('loc-b', Location::class);
    $idHQ = $builder->getResolvedId('loc-hq', Location::class);

    $count = Location::whereIn('id', [$idA, $idB, $idHQ])->count();
    if ($count !== 3) throw new Exception("Expected 3 locations, found {$count}");
    logInfo("✓ Locations created successfully");

    // 2. Test createRoutes
    logInfo("Testing createRoutes...");
    $routeConfigs = [
        ['origin' => 'loc-a', 'destination' => 'loc-b', 'days' => 2, 'cost' => 50],
        ['origin' => 'loc-b', 'destination' => 'loc-hq', 'days' => 1, 'cost' => 30],
    ];
    $builder->testCreateRoutes($routeConfigs);

    $routeCount = Route::where('is_active', true)->count();
    
    $r1 = Route::where('source_id', $idA)->where('target_id', $idB)->first();
    $r2 = Route::where('source_id', $idB)->where('target_id', $idHQ)->first();

    if (!$r1 || !$r2) throw new Exception("Routes not created correctly");
    if ($r1->transit_days != 2 || $r1->cost != 50) throw new Exception("Route 1 attributes mismatch");
    logInfo("✓ Routes created successfully");

    // 3. Test createProductBundle
    logInfo("Testing createProductBundle...");
    $products = [
        [
            'id' => 'bean-test',
            'name' => 'Test Bean',
            'category' => 'beans',
            'vendor' => ['id' => 'vend-test', 'name' => 'Test Supplier'],
            'price' => 15.0
        ]
    ];
    $builder->testCreateProductBundle($products);

    $prodId = $builder->getResolvedId('bean-test', Product::class);
    $vendId = $builder->getResolvedId('vend-test', Vendor::class);

    $prod = Product::find($prodId);
    if (!$prod) throw new Exception("Product not created");
    
    $vendor = Vendor::find($vendId);
    if (!$vendor) throw new Exception("Vendor not created");

    $pivot = $prod->vendors()->where('vendor_id', $vendId)->first();
    if (!$pivot) throw new Exception("Product-Vendor link not created");
    
    // Check product price
    if ((float)$prod->unit_price !== 15.0) throw new Exception("Product unit_price mismatch: " . $prod->unit_price);
    
    logInfo("✓ Product bundle created successfully");

    // 4. Test createGameState
    logInfo("Testing createGameState...");
    $gs = $builder->testCreateGameState($user, 500.0);
    
    if ($gs->cash != 500.0) throw new Exception("GameState cash mismatch: {$gs->cash}");
    if ($gs->user_id !== $user->id) throw new Exception("GameState user mismatch");
    
    logInfo("✓ GameState created successfully");

    logInfo("SUCCESS: All trait methods verified.");

} catch (Exception $e) {
    logError("Test failed: " . $e->getMessage());
    logError($e->getTraceAsString());
} finally {
    DB::rollBack();
    logInfo("Cleanup completed (Transaction Rolled Back)");
}
