<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\States\Order\Cancelled;
use App\States\Order\Delivered;
use App\States\Order\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FulfillmentMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_provides_fulfillment_metrics()
    {
        $user = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $user->id]);
        
        // Order 1: Delivered, took 2 days (1 to 3)
        \App\Models\Order::factory()->create([
            'user_id' => $user->id,
            'status' => Delivered::class,
            'created_day' => 1,
            'delivery_day' => 3,
        ]);
        
        // Order 2: Delivered, took 5 days (5 to 10)
        \App\Models\Order::factory()->create([
            'user_id' => $user->id,
            'status' => Delivered::class,
            'created_day' => 5,
            'delivery_day' => 10,
        ]);
        
        // Order 3: Pending
        \App\Models\Order::factory()->create([
            'user_id' => $user->id,
            'status' => Pending::class,
            'created_day' => 10,
        ]);

        // Order 4: Cancelled
        \App\Models\Order::factory()->create([
            'user_id' => $user->id,
            'status' => Cancelled::class,
            'created_day' => 10,
        ]);
        
        // Other user's order (should be ignored)
        $otherUser = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $otherUser->id]);
        \App\Models\Order::factory()->create([
            'user_id' => $otherUser->id,
            'status' => Delivered::class,
            'created_day' => 1,
            'delivery_day' => 100, // outlier
        ]);

        $response = $this->actingAs($user)
            ->get(route('game.analytics'));

        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('game/analytics')
            ->has('fulfillmentMetrics')
            ->where('fulfillmentMetrics.totalOrders', 4)
            ->where('fulfillmentMetrics.deliveredOrders', 2)
            ->where('fulfillmentMetrics.fulfillmentRate', 50) // 2/4 * 100
            ->where('fulfillmentMetrics.averageDeliveryTime', 3.5) // (2+5)/2
        );
    }
}
