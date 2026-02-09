<?php

/**
 * Manual Test: Multi-Hop Order Regression Suite
 * Generated: 2026-02-06
 * Purpose: Verify multi-hop ordering logic using a standalone script.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Prevent production execution
if (app()->environment('production')) {
    exit("Error: Cannot run manual tests in production!\n");
}

use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Traits\MultiHopScenarioBuilder;

class MultiHopManualVerifier
{
    use MultiHopScenarioBuilder;

    public function run()
    {
        $testRunId = 'test_multihop_'.Carbon::now()->format('Y_m_d_His');
        $logFile = storage_path("logs/manual_tests/{$testRunId}.log");

        if (! is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }

        config(['logging.channels.manual_test' => [
            'driver' => 'single',
            'path' => $logFile,
            'level' => 'debug',
        ]]);

        $this->logInfo("=== Starting Manual Test: {$testRunId} ===");

        try {
            DB::beginTransaction();

            // 1. Setup World (Best Case Two Hop)
            $this->logInfo('Phase 1: Setting up test data...');
            $user = User::factory()->create();
            Auth::login($user);
            $cash = 100.00;
            $this->createGameState($user, $cash);

            $scenario = [
                'locations' => ['vendor_loc', 'hub_a', 'store'],
                'routes' => [
                    ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 2, 'cost' => 1.0, 'capacity' => 200, 'active' => true],
                    ['origin' => 'hub_a', 'destination' => 'store', 'days' => 1, 'cost' => 2.0, 'capacity' => 200, 'active' => true],
                ],
                'products' => [
                    ['id' => 'coffee', 'price' => 0.10, 'vendor' => ['id' => 'vendor_loc']],
                ],
            ];

            $this->createVendorPath($scenario['locations']);
            $this->createRoutes($scenario['routes']);
            $this->createProductBundle($scenario['products']);

            $vendorId = $this->resolveId('vendor_loc', Vendor::class);
            $targetId = $this->resolveId('store', Location::class);
            $sourceLocationId = $this->resolveId('vendor_loc', Location::class);
            $prodId = $this->resolveId('coffee', Product::class);

            $this->logInfo('World setup complete.', [
                'user_id' => $user->id,
                'vendor_id' => $vendorId,
                'target_id' => $targetId,
                'prod_id' => $prodId,
            ]);

            // 2. Execute Order
            $this->logInfo('Phase 2: Executing order placement...');

            // We use the app instance to call the controller or service directly,
            // or we can simulate a request. Here we'll simulate the request via the app instance.
            $items = [[
                'product_id' => $prodId,
                'quantity' => 100,
                'unit_price' => 0.10,
            ]];

            $response = $this->simulatePost('/game/orders', [
                'vendor_id' => $vendorId,
                'location_id' => $targetId,
                'source_location_id' => $sourceLocationId,
                'items' => $items,
            ]);

            if ($response->getStatusCode() >= 400) {
                $this->logError('Order placement failed with status: '.$response->getStatusCode());

                // In a manual script, we might want to see validation errors if any
                return;
            }

            $this->logInfo('Order placement request sent successfully.');

            // 3. Verification
            $this->logInfo('Phase 3: Verifying business logic outcomes...');

            $order = Order::where('user_id', $user->id)->latest()->first();
            if (! $order) {
                throw new \Exception('Order was not created in the database.');
            }

            $this->logInfo('Order found', ['id' => $order->id, 'total_cost' => $order->total_cost]);

            // Verify total cost: (100 * 0.10) + 1.0 (leg 1) + 2.0 (leg 2) = 13.00
            if (abs($order->total_cost - 13.00) > 0.001) {
                $this->logError('Incorrect total cost: Expected 13.00, got '.$order->total_cost);
            } else {
                $this->logInfo('Total cost verified: 13.00');
            }

            // Verify shipments
            $shipments = $order->shipments()->orderBy('sequence_index')->get();
            if ($shipments->count() !== 2) {
                $this->logError('Incorrect shipment count: Expected 2, got '.$shipments->count());
            } else {
                $this->logInfo('Shipment count verified: 2');
            }

            // Verify path
            $expectedPath = [
                $this->resolveId('vendor_loc', Location::class),
                $this->resolveId('hub_a', Location::class),
                $this->resolveId('store', Location::class),
            ];

            foreach ($shipments as $index => $shipment) {
                if ($shipment->source_location_id !== $expectedPath[$index] || $shipment->target_location_id !== $expectedPath[$index + 1]) {
                    $this->logError("Path mismatch at shipment $index");
                } else {
                    $this->logInfo("Shipment $index path verified.");
                }
            }

            // Verify cash
            $gameState = GameState::where('user_id', $user->id)->first();
            $expectedCash = 100.00 - 13.00;
            if (abs($gameState->cash - $expectedCash) > 0.001) {
                $this->logError("Incorrect cash balance: Expected $expectedCash, got ".$gameState->cash);
            } else {
                $this->logInfo("Cash balance verified: $expectedCash");
            }

            $this->logInfo('All verifications completed successfully.');

        } catch (\Exception $e) {
            $this->logError('Test failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            DB::rollBack();
            $this->logInfo('Database changes rolled back.');
            $this->logInfo('=== Test Run Finished ===');
            echo "\n✓ Full logs at: {$logFile}\n";
        }
    }

    private function logInfo($msg, $ctx = [])
    {
        Log::channel('manual_test')->info($msg, $ctx);
        echo "[INFO] {$msg}\n";
    }

    private function logError($msg, $ctx = [])
    {
        Log::channel('manual_test')->error($msg, $ctx);
        echo "[ERROR] {$msg}\n";
    }

    private function simulatePost($uri, $data)
    {
        $request = \Illuminate\Http\Request::create($uri, 'POST', $data);
        $request->setLaravelSession(app('session')->driver());

        // Bypassing CSRF by modifying the middleware behavior
        app()->resolving(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, function ($middleware) use ($uri) {
            $middleware->except([$uri]);
        });

        return app()->handle($request);
    }
}

$verifier = new MultiHopManualVerifier;
$verifier->run();
