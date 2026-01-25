<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\SpikeEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia;

class MultiUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_see_other_users_alerts_and_spikes(): void
    {
        // Setup
        $this->seed(\Database\Seeders\CoreGameStateSeeder::class);
        $this->seed(\Database\Seeders\GraphSeeder::class);

        // Create two users
        $userA = User::factory()->create(['name' => 'User A', 'email' => 'usera@example.com']);
        $userB = User::factory()->create(['name' => 'User B', 'email' => 'userb@example.com']);

        // Create data for User B
        // 1. Unread Alerts for User B
        Alert::factory()->count(5)->create([
            'user_id' => $userB->id,
            'is_read' => false,
            'severity' => 'critical',
            'type' => 'system',
            'message' => 'This is for User B only',
        ]);

        // 2. Active Spike for User B
        SpikeEvent::factory()->create([
            'user_id' => $userB->id,
            'is_active' => true,
            'type' => 'demand',
            'magnitude' => 1.5,
        ]);

        // Act: User A visits the dashboard
        $response = $this->actingAs($userA)->get('/game/dashboard');

        // Assert
        $response->assertInertia(fn (AssertableInertia $page) => $page
            // Check that User A sees 0 alerts (since all created ones belong to User B)
            ->has('game.alerts', 0)
            
            // Check that User A sees 0 active spikes
            ->has('game.activeSpikes', 0)
            
            // Check User A's reputation is base value (85) because they have 0 alerts
            // If leaked, it would be lower due to User B's 5 alerts
            ->where('game.state.reputation', 85)
        );
    }
}
