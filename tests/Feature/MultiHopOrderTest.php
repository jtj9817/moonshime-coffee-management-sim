<?php

use App\Models\User;
use App\Models\Order;
use App\Models\Location;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\GameState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\MultiHopScenarioBuilder;

uses(RefreshDatabase::class);
uses(MultiHopScenarioBuilder::class);

$scenarios = [
    'best_case_two_hop' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 2, 'cost' => 1.0, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'store', 'days' => 1, 'cost' => 2.0, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 0.10, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 100]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'total_cost' => 13.00, // (100 * 0.10) + 1.0 + 2.0 = 13.00
                'shipment_count' => 2,
                'transit_days' => 3,
                'path' => ['vendor_loc', 'hub_a', 'store'],
            ]
        ]
    ],
    'average_case_four_hop_mixed_modes' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'hub_b', 'hub_c', 'store', 'alt_a', 'alt_b'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 2, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'hub_b', 'days' => 2, 'cost' => 1.50, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_b', 'destination' => 'hub_c', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_c', 'destination' => 'store', 'days' => 1, 'cost' => 0.50, 'capacity' => 200, 'active' => true],
                // Alternative path (more expensive)
                ['origin' => 'vendor_loc', 'destination' => 'alt_a', 'days' => 1, 'cost' => 2.00, 'capacity' => 200, 'active' => true],
                ['origin' => 'alt_a', 'destination' => 'alt_b', 'days' => 1, 'cost' => 2.00, 'capacity' => 200, 'active' => true],
                ['origin' => 'alt_b', 'destination' => 'store', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 1.25, 'vendor' => ['id' => 'vendor_loc']],
                ['id' => 'tea', 'price' => 0.50, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [
                    ['product_alias' => 'coffee', 'qty' => 20],
                    ['product_alias' => 'tea', 'qty' => 30]
                ],
                'user_cash' => 100.00
            ],
            'expected' => [
                'total_cost' => 44.00, // (20 * 1.25) + (30 * 0.50) + 1.0 + 1.5 + 1.0 + 0.5 = 25 + 15 + 4 = 44.00
                'shipment_count' => 4,
                'transit_days' => 6,
                'path' => ['vendor_loc', 'hub_a', 'hub_b', 'hub_c', 'store'],
            ]
        ]
    ],
    'worst_case_eight_hop_capacity_edge' => [
        [
            'locations' => ['vendor_loc', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'h1', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
                ['origin' => 'h1', 'destination' => 'h2', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
                ['origin' => 'h2', 'destination' => 'h3', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
                ['origin' => 'h3', 'destination' => 'h4', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
                ['origin' => 'h4', 'destination' => 'h5', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
                ['origin' => 'h5', 'destination' => 'h6', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
                ['origin' => 'h6', 'destination' => 'h7', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
                ['origin' => 'h7', 'destination' => 'store', 'days' => 1, 'cost' => 0.50, 'capacity' => 50, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 0.20, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 50]],
                'user_cash' => 15.00
            ],
            'expected' => [
                'total_cost' => 14.00, // (50 * 0.20) + (8 * 0.50) = 10.0 + 4.0 = 14.00
                'shipment_count' => 8,
                'transit_days' => 8,
                'path' => ['vendor_loc', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7', 'store'],
            ]
        ]
    ],
    'edge_no_route' => [
        [
            'locations' => ['vendor_loc', 'store'],
            'routes' => [], // No routes
            'products' => [
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 10]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'error_field' => 'location_id'
            ]
        ]
    ],
    'edge_inactive_route_mid_path' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'hub_b', 'store', 'alt_a'],
            'routes' => [
                // Primary path (broken)
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 1, 'cost' => 0.50, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'hub_b', 'days' => 1, 'cost' => 0.50, 'capacity' => 200, 'active' => false], // INACTIVE
                ['origin' => 'hub_b', 'destination' => 'store', 'days' => 1, 'cost' => 0.50, 'capacity' => 200, 'active' => true],
                // Alternative path (working)
                ['origin' => 'vendor_loc', 'destination' => 'alt_a', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
                ['origin' => 'alt_a', 'destination' => 'store', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 10]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'total_cost' => 12.00, // (10 * 1.00) + 1.0 + 1.0 = 12.00
                'shipment_count' => 2,
                'transit_days' => 2,
                'path' => ['vendor_loc', 'alt_a', 'store'],
            ]
        ]
    ],
    'edge_cycle_present' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'hub_b', 'store'],
            'routes' => [
                // Cycle: loc -> a -> b -> loc
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 1, 'cost' => 0.20, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'hub_b', 'days' => 1, 'cost' => 0.20, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_b', 'destination' => 'vendor_loc', 'days' => 1, 'cost' => 0.20, 'capacity' => 200, 'active' => true],
                // Valid path
                ['origin' => 'vendor_loc', 'destination' => 'store', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 10]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'total_cost' => 11.00, // (10 * 1.00) + 1.00
                'shipment_count' => 1,
                'transit_days' => 1,
                'path' => ['vendor_loc', 'store'],
            ]
        ]
    ],
    'edge_capacity_exceeded' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 1, 'cost' => 1.00, 'capacity' => 50, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'store', 'days' => 1, 'cost' => 1.00, 'capacity' => 50, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 0.50, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 60]], // Exceeds 50
                'user_cash' => 100.00
            ],
            'expected' => [
                'error_field' => 'items'
            ]
        ]
    ],
    'edge_insufficient_cash' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'store', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 1.80, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 10]],
                'user_cash' => 19.99 // Total is (10 * 1.80) + 2.0 = 20.00
            ],
            'expected' => [
                'error_field' => 'total' // Or whatever field validates cash
            ]
        ]
    ],
    'edge_source_equals_target' => [
        [
            'locations' => ['vendor_loc'],
            'routes' => [],
            'products' => [
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'vendor_loc',
                'items' => [['product_alias' => 'coffee', 'qty' => 10]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'error_field' => 'location_id'
            ]
        ]
    ],
];

it('processes multihop order scenarios', function (array $scenario) {
    // 1. Setup World
    $user = User::factory()->create();
    $cash = $scenario['order']['user_cash'] ?? 1000.0;
    $this->createGameState($user, $cash);

    $this->createVendorPath($scenario['locations']);
    $this->createRoutes($scenario['routes']);
    $this->createProductBundle($scenario['products']);

    // 2. Resolve Aliases
    $vendorId = $this->resolveId($scenario['order']['vendor_alias'], Vendor::class);
    $targetId = $this->resolveId($scenario['order']['target_alias'], Location::class);
    $sourceLocationId = $this->resolveId($scenario['order']['vendor_alias'], Location::class);

    $items = [];
    foreach ($scenario['order']['items'] as $item) {
        $prodId = $this->resolveId($item['product_alias'], Product::class);
        $product = Product::find($prodId);
        $items[] = [
            'product_id' => $prodId,
            'quantity' => $item['qty'],
            'unit_price' => $product->unit_price
        ];
    }

    // 3. Execute Order
    $response = $this->actingAs($user)
        ->post('/game/orders', [
            'vendor_id' => $vendorId,
            'location_id' => $targetId,
            'source_location_id' => $sourceLocationId,
            'items' => $items
        ]);

    // 4. Verification
    if (isset($scenario['expected']['error_field'])) {
        // Negative Case
        $response->assertSessionHasErrors($scenario['expected']['error_field']);
        
        // Assert no order was created
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('shipments', 0);
        
        // Assert cash unchanged
        $gameState = GameState::where('user_id', $user->id)->first();
        expect($gameState->cash)->toEqual($cash);

    } else {
        // Positive Case
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'vendor_id' => $vendorId,
            'location_id' => $targetId,
            'total_cost' => $scenario['expected']['total_cost'],
            'total_transit_days' => $scenario['expected']['transit_days'],
        ]);

        $order = Order::where('user_id', $user->id)->latest()->first();
        
        // Check shipments count
        $shipments = $order->shipments()->orderBy('sequence_index')->get();
        $this->assertCount($scenario['expected']['shipment_count'], $shipments);
        $expectedSequence = range(0, $scenario['expected']['shipment_count'] - 1);
        expect($shipments->pluck('sequence_index')->all())->toEqual($expectedSequence);

        if (isset($scenario['expected']['path'])) {
            $expectedPath = array_map(
                fn (string $alias) => $this->resolveId($alias, Location::class),
                $scenario['expected']['path']
            );
            $expectedLegs = count($expectedPath) - 1;
            expect($expectedLegs)->toEqual($scenario['expected']['shipment_count']);

            foreach ($shipments as $index => $shipment) {
                expect($shipment->source_location_id)->toEqual($expectedPath[$index]);
                expect($shipment->target_location_id)->toEqual($expectedPath[$index + 1]);
            }
        }

    }

})->with($scenarios);
