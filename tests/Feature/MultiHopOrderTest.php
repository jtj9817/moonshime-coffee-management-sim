<?php

use App\Models\User;
use App\Models\Order;
use App\Models\Location;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\GameState;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\LogisticsService;
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
                'items_cost' => 10.00,
                'logistics_cost' => 3.00,
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
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 2, 'cost' => 1.00, 'capacity' => 200, 'active' => true, 'transport_mode' => 'truck'],
                ['origin' => 'hub_a', 'destination' => 'hub_b', 'days' => 2, 'cost' => 1.50, 'capacity' => 200, 'active' => true, 'transport_mode' => 'rail'],
                ['origin' => 'hub_b', 'destination' => 'hub_c', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true, 'transport_mode' => 'van'],
                ['origin' => 'hub_c', 'destination' => 'store', 'days' => 1, 'cost' => 0.50, 'capacity' => 200, 'active' => true, 'transport_mode' => 'bike'],
                // Alternative path (more expensive)
                ['origin' => 'vendor_loc', 'destination' => 'alt_a', 'days' => 1, 'cost' => 2.00, 'capacity' => 200, 'active' => true, 'transport_mode' => 'truck'],
                ['origin' => 'alt_a', 'destination' => 'alt_b', 'days' => 1, 'cost' => 2.00, 'capacity' => 200, 'active' => true, 'transport_mode' => 'van'],
                ['origin' => 'alt_b', 'destination' => 'store', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true, 'transport_mode' => 'bike'],
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
                'items_cost' => 40.00,
                'logistics_cost' => 4.00,
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
                'items_cost' => 10.00,
                'logistics_cost' => 4.00,
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
                'items_cost' => 10.00,
                'logistics_cost' => 2.00,
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
                'items_cost' => 10.00,
                'logistics_cost' => 1.00,
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
    'edge_empty_items_list' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 1, 'cost' => 1.0, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'store', 'days' => 1, 'cost' => 1.0, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [],
                'user_cash' => 100.00
            ],
            'expected' => [
                'error_field' => 'items',
            ],
        ],
    ],
    'edge_zero_quantity' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 1, 'cost' => 1.0, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'store', 'days' => 1, 'cost' => 1.0, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 0]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'error_field' => 'items.0.quantity',
            ],
        ],
    ],
    'edge_rounding_unit_price_3dp' => [
        [
            'locations' => ['vendor_loc', 'hub_a', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'hub_a', 'days' => 1, 'cost' => 0.60, 'capacity' => 200, 'active' => true],
                ['origin' => 'hub_a', 'destination' => 'store', 'days' => 1, 'cost' => 0.40, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                // Request rounds unit_price to 2 decimals in StoreOrderRequest::prepareForValidation()
                ['id' => 'coffee', 'price' => 1.236, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 10]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'items_cost' => 12.40, // 10 * 1.24 (rounded from 1.236)
                'logistics_cost' => 1.00,
                'total_cost' => 13.40,
                'shipment_count' => 2,
                'transit_days' => 2,
                'path' => ['vendor_loc', 'hub_a', 'store'],
            ],
        ],
    ],
    'edge_invalid_vendor_id' => [
        [
            'locations' => ['missing_vendor', 'store'],
            'routes' => [],
            'products' => [
                // Ensure product exists so the failure is scoped to vendor_id.
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'missing_vendor', // Vendor is never created for this alias.
                'target_alias' => 'store',
                'items' => [['product_alias' => 'coffee', 'qty' => 1]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'error_field' => 'vendor_id',
            ],
        ],
    ],
    'edge_invalid_product_id' => [
        [
            'locations' => ['vendor_loc', 'store'],
            'routes' => [
                ['origin' => 'vendor_loc', 'destination' => 'store', 'days' => 1, 'cost' => 1.00, 'capacity' => 200, 'active' => true],
            ],
            'products' => [
                // Ensure vendor exists so the failure is scoped to items.*.product_id.
                ['id' => 'coffee', 'price' => 1.00, 'vendor' => ['id' => 'vendor_loc']]
            ],
            'order' => [
                'vendor_alias' => 'vendor_loc',
                'target_alias' => 'store',
                'items' => [[
                    'product_id' => '00000000-0000-0000-0000-000000000000',
                    'unit_price' => 1.00,
                    'qty' => 1,
                ]],
                'user_cash' => 100.00
            ],
            'expected' => [
                'error_field' => 'items.0.product_id',
            ],
        ],
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
        $prodId = $item['product_id'] ?? $this->resolveId($item['product_alias'], Product::class);
        $unitPrice = $item['unit_price'] ?? Product::find($prodId)?->unit_price;

        // For negative scenarios (e.g. invalid product_id), we want to hit validation rather than fatal here.
        if ($unitPrice === null) {
            $unitPrice = 0.0;
        }

        $items[] = [
            'product_id' => $prodId,
            'quantity' => $item['qty'],
            'unit_price' => $unitPrice
        ];
    }

    $ordersBefore = Order::where('user_id', $user->id)->count();
    $orderIdsBefore = Order::where('user_id', $user->id)->pluck('id');
    $shipmentsBefore = Shipment::whereIn('order_id', $orderIdsBefore)->count();

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
        
        // Assert no order was created for this user
        expect(Order::where('user_id', $user->id)->count())->toBe($ordersBefore);
        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
            'vendor_id' => $vendorId,
            'location_id' => $targetId,
        ]);

        // Assert no shipments were created for this user's orders
        $orderIdsAfter = Order::where('user_id', $user->id)->pluck('id');
        expect(Shipment::whereIn('order_id', $orderIdsAfter)->count())->toBe($shipmentsBefore);
        
        // Assert cash unchanged
        $gameState = GameState::where('user_id', $user->id)->first();
        expect($gameState->cash)->toEqual($cash);

    } else {
        // Positive Case
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'vendor_id' => $vendorId,
            'location_id' => $targetId,
            'total_cost' => $scenario['expected']['total_cost'],
            'total_transit_days' => $scenario['expected']['transit_days'],
        ]);

        $order = Order::where('user_id', $user->id)->latest()->first();
        expect($order)->not->toBeNull();
        expect($order->status)->toBeInstanceOf(\App\States\Order\Pending::class);
        expect($order->vendor_id)->toEqual($vendorId);
        expect($order->location_id)->toEqual($targetId);
        
        // Check shipments count
        $shipments = $order->shipments()->with('route')->orderBy('sequence_index')->get();
        $this->assertCount($scenario['expected']['shipment_count'], $shipments);
        $expectedSequence = range(0, $scenario['expected']['shipment_count'] - 1);
        expect($shipments->pluck('sequence_index')->all())->toEqual($expectedSequence);

        // Shipment statuses should follow the creation convention (first leg in_transit, rest pending).
        if ($shipments->isNotEmpty()) {
            expect($shipments->first()->status)->toEqual('in_transit');
            foreach ($shipments->slice(1) as $shipment) {
                expect($shipment->status)->toEqual('pending');
            }
        }

        // Continuity invariant: each shipment leg flows into the next.
        foreach ($shipments as $idx => $shipment) {
            $next = $shipments->get($idx + 1);
            if ($next) {
                expect($shipment->target_location_id)->toEqual($next->source_location_id);
            }
        }

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

        // Totals invariant: order.total_cost == items_total + logistics_total (rounded to 2dp).
        $itemsCost = round($order->items()->get()->sum(fn ($i) => $i->quantity * $i->cost_per_unit), 2);
        $logistics = app(LogisticsService::class);
        $logisticsCost = round($shipments->sum(fn ($s) => $s->route ? $logistics->calculateCost($s->route) : 0.0), 2);

        if (isset($scenario['expected']['items_cost'])) {
            expect($itemsCost)->toEqual($scenario['expected']['items_cost']);
        }
        if (isset($scenario['expected']['logistics_cost'])) {
            expect($logisticsCost)->toEqual($scenario['expected']['logistics_cost']);
        }

        expect(round($itemsCost + $logisticsCost, 2))->toEqual(round((float) $order->total_cost, 2));
        expect(round((float) $order->total_cost, 2))->toEqual($scenario['expected']['total_cost']);

        // Transit-days invariant: order.total_transit_days == sum(route.transit_days) across shipments.
        expect((int) $shipments->sum(fn ($s) => $s->route?->transit_days ?? 0))->toEqual($scenario['expected']['transit_days']);

        // Cash invariant: cash decreases by order total when the order is accepted.
        $expectedCashAfter = round($cash - $scenario['expected']['total_cost'], 2);
        $gameStateAfter = GameState::where('user_id', $user->id)->first();
        expect(round((float) $gameStateAfter->cash, 2))->toEqual($expectedCashAfter);

    }

})->with($scenarios);
