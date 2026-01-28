<?php

use App\Models\User;
use App\Models\Order;
use App\Models\Route;
use App\Models\Location;
use App\Models\Vendor;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\MultiHopScenarioBuilder;

uses(RefreshDatabase::class);
uses(MultiHopScenarioBuilder::class);

$scenarios = [
    'Direct Route Preferred' => [
        [
            'locations' => ['vend-a', 'loc-hq'],
            'routes' => [
                ['origin' => 'vend-a', 'destination' => 'loc-hq', 'days' => 1, 'cost' => 10.0, 'capacity' => 500],
            ],
            'products' => [
                ['id' => 'bean-a', 'price' => 20.0, 'vendor' => ['id' => 'vend-a']]
            ],
            'order' => [
                'vendor_alias' => 'vend-a',
                'target_alias' => 'loc-hq',
                'items' => [['product_alias' => 'bean-a', 'qty' => 10]]
            ],
            'expected' => [
                'total_cost' => 210.0, // (10 * 20.0) + 10.0
                'shipment_count' => 1,
                'transit_days' => 1,
            ]
        ]
    ],
    'Multi-Hop Path Cheaper' => [
        [
            'locations' => ['vend-b', 'hub-1', 'loc-hq'],
            'routes' => [
                ['origin' => 'vend-b', 'destination' => 'loc-hq', 'days' => 1, 'cost' => 100.0, 'capacity' => 500],
                ['origin' => 'vend-b', 'destination' => 'hub-1', 'days' => 1, 'cost' => 5.0, 'capacity' => 500],
                ['origin' => 'hub-1', 'destination' => 'loc-hq', 'days' => 1, 'cost' => 5.0, 'capacity' => 500],
            ],
            'products' => [
                ['id' => 'bean-b', 'price' => 20.0, 'vendor' => ['id' => 'vend-b']]
            ],
            'order' => [
                'vendor_alias' => 'vend-b',
                'target_alias' => 'loc-hq',
                'items' => [['product_alias' => 'bean-b', 'qty' => 10]]
            ],
            'expected' => [
                'total_cost' => 210.0, // (10 * 20.0) + 5.0 + 5.0 = 210.0
                'shipment_count' => 2,
                'transit_days' => 2,
            ]
        ]
    ],
];

it('processes multihop order scenarios', function (array $scenario) {
    // 1. Setup World
    $user = User::factory()->create();
    $this->createGameState($user, 1000.0);

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
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'vendor_id' => $vendorId,
        'location_id' => $targetId,
        'total_cost' => $scenario['expected']['total_cost'],
        'total_transit_days' => $scenario['expected']['transit_days'],
    ]);

    $order = Order::where('user_id', $user->id)->latest()->first();
    // Refresh to get any latest state
    
    // Check shipments count
    $this->assertCount($scenario['expected']['shipment_count'], $order->shipments);

})->with($scenarios);
