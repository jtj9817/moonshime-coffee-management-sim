<?php

namespace Database\Seeders;

use App\Models\GameState;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(CoreGameStateSeeder::class);
        $this->call(GraphSeeder::class);

        // Ensure a GameState exists for the seeded user before seeding spikes
        $user = User::first();
        GameState::firstOrCreate(
            ['user_id' => $user->id],
            ['cash' => 1000000, 'xp' => 0, 'day' => 1]
        );

        $this->call(SpikeSeeder::class);
    }
}

