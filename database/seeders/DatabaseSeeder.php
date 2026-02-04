<?php

namespace Database\Seeders;

use App\Actions\InitializeNewGame;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $logger = Log::channel('game-initialization');
        $logger->info('DatabaseSeeder: Starting complete seeding chain');

        $startTime = microtime(true);

        try {
            // Create test user
            $logger->info('DatabaseSeeder: Creating test user');
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            // Seed global world data (locations, routes, vendors, products)
            $logger->info('DatabaseSeeder: Running CoreGameStateSeeder');
            $this->call(CoreGameStateSeeder::class);

            $logger->info('DatabaseSeeder: Running GraphSeeder');
            $this->call(GraphSeeder::class);

            $logger->info('DatabaseSeeder: Running InventorySeeder');
            $this->call(InventorySeeder::class);

            $logger->info('DatabaseSeeder: Running QuestSeeder');
            $this->call(QuestSeeder::class);

            // Initialize per-user game state (inventory, pipeline, spikes)
            $logger->info('DatabaseSeeder: Running InitializeNewGame for test user');
            app(InitializeNewGame::class)->handle($user);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $logger->info('DatabaseSeeder: Complete seeding chain finished successfully', [
                'duration_ms' => $duration,
                'test_user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $logger->error('DatabaseSeeder: Seeding chain failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
