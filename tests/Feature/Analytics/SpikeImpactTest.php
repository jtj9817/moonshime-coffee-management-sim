<?php

namespace Tests\Feature\Analytics;

use App\Models\SpikeEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SpikeImpactTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_provides_spike_impact_analysis()
    {
        $user = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $user->id]);
        $location = \App\Models\Location::factory()->create(['name' => 'Affected Cafe']);
        $product = \App\Models\Product::factory()->create(['name' => 'Espresso']);

        // Create a past spike: Day 2 to 4
        $spike = SpikeEvent::factory()->create([
            'user_id' => $user->id,
            'type' => 'demand',
            'location_id' => $location->id,
            'product_id' => $product->id,
            'starts_at_day' => 2,
            'duration' => 3, // Ends at 2 + 3 = 5? Or inclusive? Usually duration is added.
            // If logic says ends_at = start + duration, then 2+3=5.
            'ends_at_day' => 4, // Let's set it explicitly for clarity in factory if possible, or update it
        ]);
        $spike->update(['ends_at_day' => 4]); // Ensure explicit end day

        // Seed Inventory History
        // Day 1: 100 (Pre-spike)
        DB::table('inventory_history')->insert([
            'user_id' => $user->id, 'location_id' => $location->id, 'product_id' => $product->id,
            'day' => 1, 'quantity' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Day 2: 80 (Spike Start)
        DB::table('inventory_history')->insert([
            'user_id' => $user->id, 'location_id' => $location->id, 'product_id' => $product->id,
            'day' => 2, 'quantity' => 80, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Day 3: 20 (Deep Impact)
        DB::table('inventory_history')->insert([
            'user_id' => $user->id, 'location_id' => $location->id, 'product_id' => $product->id,
            'day' => 3, 'quantity' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Day 4: 10 (End)
        DB::table('inventory_history')->insert([
            'user_id' => $user->id, 'location_id' => $location->id, 'product_id' => $product->id,
            'day' => 4, 'quantity' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Day 5: 90 (Recovery)
        DB::table('inventory_history')->insert([
            'user_id' => $user->id, 'location_id' => $location->id, 'product_id' => $product->id,
            'day' => 5, 'quantity' => 90, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('game.analytics'));

        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('game/analytics')
            ->has('spikeImpactAnalysis', 1)
            ->where('spikeImpactAnalysis.0.id', $spike->id)
            ->where('spikeImpactAnalysis.0.product_name', 'Espresso')
            ->where('spikeImpactAnalysis.0.impact.min_inventory', 10) // Lowest during Day 2-4
            ->where('spikeImpactAnalysis.0.impact.avg_inventory', 36.7) // (80+20+10)/3 = 36.666... -> 36.7
        );
    }
}
