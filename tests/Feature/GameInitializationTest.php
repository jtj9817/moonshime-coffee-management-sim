<?php

namespace Tests\Feature;

use App\Actions\InitializeNewGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameInitializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_game_starts_with_one_million_cents(): void
    {
        // Setup necessary dependencies for InitializeNewGame
        $this->seed(\Database\Seeders\CoreGameStateSeeder::class);
        $this->seed(\Database\Seeders\GraphSeeder::class);

        $user = User::factory()->create();

        $action = app(InitializeNewGame::class);
        $gameState = $action->handle($user);

        // 1,000,000 cents = $10,000.00
        // The spec states: "New games are currently initialized with 10,000.00 ($100.00) instead of the intended 1,000,000 cents ($10,000.00)."
        // So we expect 1000000.
        $this->assertEquals(1000000, $gameState->cash, 'Starting cash should be 1,000,000 cents ($10,000.00)');
    }

    public function test_dashboard_inertia_response_has_correct_starting_cash(): void
    {
        $this->seed(\Database\Seeders\CoreGameStateSeeder::class);
        $this->seed(\Database\Seeders\GraphSeeder::class);

        $user = User::factory()->create();

        // Acting as the user, visit the dashboard
        $response = $this->actingAs($user)->get('/game/dashboard');

        // Assert Inertia prop 'game.state.cash' is 1000000
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('game.state.cash', 1000000)
        );
    }
}
