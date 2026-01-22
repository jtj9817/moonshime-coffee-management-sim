<?php

namespace App\Console\Commands;

use App\Actions\InitializeNewGame;
use App\Models\GameState;
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

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        $this->info("Checking game state for user: {$user->name} ({$user->email})");

        if ($user->gameState) {
            $this->displayUserData($user);
            return Command::SUCCESS;
        }

        return $this->initializeUser($user);
    }

    /**
     * Initialize the game for the user.
     */
    protected function initializeUser(User $user)
    {
        $this->info('No existing game state found. initializing...');

        try {
            // 1. Run CoreGameStateSeeder to ensure global data exists
            $this->info('Running CoreGameStateSeeder...');
            $this->call('db:seed', [
                '--class' => CoreGameStateSeeder::class,
                '--force' => true,
            ]);

            // 2. Run InitializeNewGame action
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
                        $gameState->xp
                    ]]
                );
            });

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to initialize game: " . $e->getMessage());
            Log::error("InitializeUserGame Command Failed: " . $e->getMessage(), ['exception' => $e]);
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
                $gameState->created_at->toDateTimeString()
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
