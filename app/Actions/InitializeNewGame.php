<?php

namespace App\Actions;

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\SpikeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Reusable action for initializing a new game for a user.
 * Seeds inventory, pipeline activity (orders/transfers), and spikes.
 */
class InitializeNewGame
{
    /**
     * Initialize a new game for a user, including seeding all per-user state.
     */
    public function handle(User $user): GameState
    {
        $logger = Log::channel('game-initialization');
        $logger->info('InitializeNewGame: Starting game initialization', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        try {
            return DB::transaction(function () use ($user, $logger) {
                $gameState = GameState::firstOrCreate(
                    ['user_id' => $user->id],
                    ['cash' => 1000000, 'xp' => 0, 'day' => 1]
                );

                $logger->info('InitializeNewGame: GameState created', [
                    'user_id' => $user->id,
                    'starting_cash' => 1000000,
                    'starting_day' => 1,
                    'was_recently_created' => $gameState->wasRecentlyCreated,
                ]);

                // Only seed state if this is a fresh game (day 1)
                if ($gameState->wasRecentlyCreated || $gameState->day === 1) {
                    // Check if already seeded (idempotency)
                    $existingInventoryCount = Inventory::where('user_id', $user->id)->count();
                    $existingTransferCount = Transfer::where('user_id', $user->id)->count();

                    if ($existingInventoryCount > 0 || $existingTransferCount > 0) {
                        $logger->warning('InitializeNewGame: User already has inventory or transfers, skipping seeding', [
                            'user_id' => $user->id,
                            'existing_inventory_count' => $existingInventoryCount,
                            'existing_transfer_count' => $existingTransferCount,
                        ]);

                        $this->syncLocationOwnership($user, $logger);

                        return $gameState;
                    }

                    $this->seedInitialInventory($user, $logger);
                    $this->syncLocationOwnership($user, $logger);
                    $this->seedPipelineActivity($user, $gameState, $logger);
                    app(SpikeSeeder::class)->seedInitialSpikes($gameState);

                    $logger->info('InitializeNewGame: Game initialization complete', [
                        'user_id' => $user->id,
                    ]);
                } else {
                    $this->syncLocationOwnership($user, $logger);

                    $logger->info('InitializeNewGame: Game already initialized, skipping seeding', [
                        'user_id' => $user->id,
                        'current_day' => $gameState->day,
                    ]);
                }

                return $gameState;
            });
        } catch (\Exception $e) {
            $logger->error('InitializeNewGame: Initialization failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Seed starting inventory across locations with core SKUs.
     */
    protected function seedInitialInventory(User $user, $logger): void
    {
        $logger->info('InitializeNewGame: Starting inventory seeding', ['user_id' => $user->id]);

        // Get all stores and warehouses
        $stores = Location::where('type', 'store')->get();
        $warehouse = Location::where('type', 'warehouse')->first();

        if ($stores->isEmpty()) {
            $logger->error('InitializeNewGame: Cannot seed inventory - no stores found', [
                'user_id' => $user->id,
                'hint' => 'GraphSeeder may not have been run',
            ]);
            throw new RuntimeException('Cannot initialize game: No stores found. Please ensure GraphSeeder has been run.');
        }

        if (! $warehouse) {
            $logger->error('InitializeNewGame: Cannot seed inventory - no warehouse found', [
                'user_id' => $user->id,
                'hint' => 'GraphSeeder may not have been run',
            ]);
            throw new RuntimeException('Cannot initialize game: No warehouse found. Please ensure GraphSeeder has been run.');
        }

        $products = Product::all();
        if ($products->isEmpty()) {
            $logger->error('InitializeNewGame: Cannot seed inventory - no products found', [
                'user_id' => $user->id,
                'hint' => 'CoreGameStateSeeder may not have been run',
            ]);
            throw new RuntimeException('Cannot initialize game: No products found. Please ensure CoreGameStateSeeder has been run.');
        }

        $logger->info('InitializeNewGame: Inventory dependencies validated', [
            'user_id' => $user->id,
            'stores_count' => $stores->count(),
            'warehouse_count' => 1,
            'products_count' => $products->count(),
        ]);

        $primaryStore = $stores->first();
        $inventoryCreated = 0;
        $perishableCount = 0;
        $nonPerishableCount = 0;

        foreach ($products as $product) {
            // Primary store: full stock
            Inventory::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'location_id' => $primaryStore->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => $product->is_perishable ? 30 : 80,
                    'last_restocked_at' => now(),
                ]
            );
            $inventoryCreated++;
            if ($product->is_perishable) {
                $perishableCount++;
            } else {
                $nonPerishableCount++;
            }

            // Warehouse: bulk non-perishables, limited perishables
            Inventory::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'location_id' => $warehouse->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => $product->is_perishable ? 20 : 200,
                    'last_restocked_at' => now(),
                ]
            );
            $inventoryCreated++;

            // Secondary stores: lower baseline stock (creates transfer opportunities)
            foreach ($stores->skip(1) as $secondaryStore) {
                Inventory::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'location_id' => $secondaryStore->id,
                        'product_id' => $product->id,
                    ],
                    [
                        // Lower quantities than primary store
                        'quantity' => $product->is_perishable ? 10 : 25,
                        'last_restocked_at' => now(),
                    ]
                );
                $inventoryCreated++;
            }
        }

        $logger->info('InitializeNewGame: Inventory seeding completed', [
            'user_id' => $user->id,
            'total_inventory_entries' => $inventoryCreated,
            'perishable_products' => $perishableCount,
            'non_perishable_products' => $nonPerishableCount,
            'primary_store_id' => $primaryStore->id,
            'warehouse_id' => $warehouse->id,
        ]);
    }

    /**
     * Seed in-transit orders and transfers arriving Days 2-4.
     */
    protected function seedPipelineActivity(User $user, GameState $gameState, $logger): void
    {
        $logger->info('InitializeNewGame: Starting pipeline activity seeding', ['user_id' => $user->id]);

        $vendor = Vendor::first();
        $store = Location::where('type', 'store')->first();
        $warehouse = Location::where('type', 'warehouse')->first();
        $product = Product::first();

        if (! $vendor) {
            $logger->error('InitializeNewGame: Cannot seed pipeline - no vendor found', [
                'user_id' => $user->id,
                'hint' => 'CoreGameStateSeeder may not have been run',
            ]);
            throw new RuntimeException('Cannot initialize game: No vendor found. Please ensure CoreGameStateSeeder has been run.');
        }

        if (! $store) {
            $logger->error('InitializeNewGame: Cannot seed pipeline - no store found', [
                'user_id' => $user->id,
            ]);
            throw new RuntimeException('Cannot initialize game: No store found.');
        }

        if (! $warehouse) {
            $logger->error('InitializeNewGame: Cannot seed pipeline - no warehouse found', [
                'user_id' => $user->id,
            ]);
            throw new RuntimeException('Cannot initialize game: No warehouse found.');
        }

        if (! $product) {
            $logger->error('InitializeNewGame: Cannot seed pipeline - no product found', [
                'user_id' => $user->id,
            ]);
            throw new RuntimeException('Cannot initialize game: No product found.');
        }

        // Seed a shipped order arriving Day 3 (using multi-hop service)
        $vendorLocation = Location::where('type', 'vendor')->first();
        if ($vendorLocation && $store) {
            $logistics = app(\App\Services\LogisticsService::class);
            $path = $logistics->findBestRoute($vendorLocation, $store);

            if ($path && $path->isNotEmpty()) {
                app(\App\Services\OrderService::class)->createOrder(
                    user: $user,
                    vendor: $vendor,
                    targetLocation: $store,
                    items: [[
                        'product_id' => $product->id,
                        'quantity' => 50,
                        'cost_per_unit' => 100, // $1.00 in cents
                    ]],
                    path: $path
                );

                $logger->info('InitializeNewGame: Created vendor order', [
                    'user_id' => $user->id,
                    'vendor_id' => $vendor->id,
                    'product_id' => $product->id,
                    'quantity' => 50,
                    'target_location_id' => $store->id,
                ]);
            } else {
                $logger->warning('InitializeNewGame: No route found from vendor to store, order not created', [
                    'user_id' => $user->id,
                    'vendor_location_id' => $vendorLocation->id,
                    'store_location_id' => $store->id,
                    'hint' => 'GraphSeeder may have created disconnected graph',
                ]);
            }
        }

        // Seed an in-transit transfer arriving Day 2
        $transfer1 = Transfer::firstOrCreate(
            [
                'user_id' => $user->id,
                'source_location_id' => $warehouse->id,
                'target_location_id' => $store->id,
                'product_id' => $product->id,
                'delivery_day' => $gameState->day + 1,
            ],
            [
                'quantity' => 25,
                'status' => 'in_transit',
            ]
        );

        $logger->info('InitializeNewGame: Created transfer arriving Day 2', [
            'user_id' => $user->id,
            'transfer_id' => $transfer1->id,
            'quantity' => 25,
            'delivery_day' => $gameState->day + 1,
        ]);

        // Seed another transfer arriving Day 4 for variety
        $secondProduct = Product::where('id', '!=', $product->id)->first();
        if ($secondProduct) {
            $transfer2 = Transfer::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'source_location_id' => $warehouse->id,
                    'target_location_id' => $store->id,
                    'product_id' => $secondProduct->id,
                    'delivery_day' => $gameState->day + 3,
                ],
                [
                    'quantity' => 15,
                    'status' => 'in_transit',
                ]
            );

            $logger->info('InitializeNewGame: Created transfer arriving Day 4', [
                'user_id' => $user->id,
                'transfer_id' => $transfer2->id,
                'product_id' => $secondProduct->id,
                'quantity' => 15,
                'delivery_day' => $gameState->day + 3,
            ]);
        }

        $logger->info('InitializeNewGame: Pipeline activity seeding completed', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Ensure user has explicit access rows for owned gameplay locations.
     */
    protected function syncLocationOwnership(User $user, $logger): void
    {
        if (! Schema::hasTable('user_locations')) {
            return;
        }

        $inventoryLocationIds = Inventory::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('location_id');
        $vendorLocationIds = Location::query()
            ->where('type', 'vendor')
            ->pluck('id');
        $locationIds = $inventoryLocationIds
            ->merge($vendorLocationIds)
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $locationIds->map(fn (string $locationId): array => [
            'user_id' => $user->id,
            'location_id' => $locationId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('user_locations')->insertOrIgnore($rows);

        $logger->info('InitializeNewGame: Synced location ownership', [
            'user_id' => $user->id,
            'locations_granted' => count($rows),
        ]);
    }
}
