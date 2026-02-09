<?php

namespace App\Console\Commands;

use App\Actions\InitializeNewGame;
use App\Models\Location;
use App\Models\Product;
use App\Models\Route;
use App\Models\User;
use Database\Seeders\CoreGameStateSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InitializeUserGame extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:initialize-user {email : The email address of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize game state for a user if it does not exist, or display existing data.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return Command::FAILURE;
        }

        $this->info("Checking game state for user: {$user->name} ({$user->email})");

        // 1. Ensure Global Game Data Exists
        $this->ensureGlobalDataExists();

        // 2. Initialize User State if needed
        if ($user->gameState) {
            $this->displayUserData($user);

            return Command::SUCCESS;
        }

        return $this->initializeUser($user);
    }

    /**
     * Check if global game data matches expectations, otherwise seed it.
     */
    protected function ensureGlobalDataExists()
    {
        $productCount = Product::count();
        $locationCount = Location::count();
        $routeCount = Route::count();

        $this->info('Checking global world data...');

        if ($productCount === 0 || $locationCount === 0 || $routeCount === 0) {
            $this->warn('Global game data is incomplete or missing. Seeding world...');

            if ($productCount === 0) {
                $this->info('- Seeding Products & Vendors (CoreGameStateSeeder)...');
                $this->call('db:seed', [
                    '--class' => CoreGameStateSeeder::class,
                    '--force' => true,
                ]);
            }

            if ($locationCount === 0 || $routeCount === 0) {
                $this->info('- Seeding Logistics Network (GraphSeeder)...');
                $this->call('db:seed', [
                    '--class' => \Database\Seeders\GraphSeeder::class,
                    '--force' => true,
                ]);
            }

            $this->info('Global world data seeded successfully.');
        } else {
            $this->info("Global world data looks good (Products: {$productCount}, Locations: {$locationCount}, Routes: {$routeCount}).");
        }
    }

    /**
     * Initialize the game for the user.
     */
    protected function initializeUser(User $user)
    {
        $this->info('No existing game state found. initializing...');

        try {
            // Note: Global seeding is now handled by ensureGlobalDataExists()

            // Run InitializeNewGame action
            $this->info('Running InitializeNewGame action...');
            $action = app(InitializeNewGame::class);

            DB::transaction(function () use ($action, $user) {
                $gameState = $action->handle($user);

                $this->info('Game initialized successfully!');
                $this->table(
                    ['User ID', 'Cash', 'Day', 'XP'],
                    [[
                        $user->id,
                        number_format($gameState->cash, 2),
                        $gameState->day,
                        $gameState->xp,
                    ]]
                );
            });

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to initialize game: '.$e->getMessage());
            Log::error('InitializeUserGame Command Failed: '.$e->getMessage(), ['exception' => $e]);

            return Command::FAILURE;
        }
    }

    /**
     * Display associated data for the user.
     */
    protected function displayUserData(User $user)
    {
        $this->warn('User already has a game state. Game is initialized.');

        // Game State
        $this->info('--- User Game State ---');
        $gameState = $user->gameState;
        $this->table(
            ['Cash', 'Day', 'XP', 'Created At'],
            [[
                number_format($gameState->cash, 2),
                $gameState->day,
                $gameState->xp,
                $gameState->created_at->toDateTimeString(),
            ]]
        );

        // Global Data Summary
        $this->info('--- Global Game World Status ---');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Products', Product::count()],
                ['Locations', Location::count()],
                ['Routes', Route::count()],
            ]
        );

        $this->newLine();
        $this->info('✓ Game is fully initialized for this user.');
    }
}
