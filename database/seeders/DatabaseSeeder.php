<?php

namespace Database\Seeders;

use App\Actions\InitializeNewGame;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed global world data (locations, routes, vendors, products)
        $this->call(CoreGameStateSeeder::class);
        $this->call(GraphSeeder::class);

        // Initialize per-user game state (inventory, pipeline, spikes)
        app(InitializeNewGame::class)->handle($user);
    }
}
