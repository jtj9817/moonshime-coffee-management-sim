<?php
/**
 * Verification Script: Phase 2 - Core Math & Strategies
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\InventoryMathService;
use App\Services\Strategies\JustInTimeStrategy;
use App\Services\Strategies\SafetyStockStrategy;
use App\Models\Inventory;

echo "--- Starting Verification: Phase 2 Core Math & Strategies ---\\n";

// 1. Test Math Service
echo ">> Testing InventoryMathService...\\n";
$math = new InventoryMathService();
$eoq = $math->calculateEOQ(1000, 50, 5);
echo "   - EOQ (D=1000, S=50, H=5): {$eoq} (Expected: 142)\\n";

$ss = $math->calculateSafetyStock(2, 5, 1, 10, 1.645);
echo "   - Safety Stock: {$ss} (Expected: 19)\\n";

// 2. Test JIT Strategy
echo ">> Testing JustInTimeStrategy...\\n";
$jit = new JustInTimeStrategy();
$inv = new Inventory(['quantity' => 10]);
$jitAmount = $jit->calculateReorderAmount($inv, ['daily_demand' => 5, 'lead_time' => 3]);
echo "   - JIT Reorder (Qty=10, D=5, LT=3): {$jitAmount} (Expected: 5)\\n";

// 3. Test Safety Stock Strategy
echo ">> Testing SafetyStockStrategy...\\n";
$safetyStrat = new SafetyStockStrategy($math);
$inv2 = new Inventory(['quantity' => 50]);
$safetyAmount = $safetyStrat->calculateReorderAmount($inv2, [
    'avg_daily_usage' => 10,
    'avg_lead_time' => 5,
    'daily_usage_std_dev' => 2,
    'lead_time_std_dev' => 1,
    'service_level' => 0.95
]);
echo "   - SafetyStock Reorder (Qty=50, ROP=69, SS=19): {$safetyAmount} (Expected: 38)\\n";

echo "--- Verification Complete ---\\n";
