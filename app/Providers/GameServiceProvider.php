<?php

namespace App\Providers;

use App\Events\OrderPlaced;
use App\Events\OrderDelivered;
use App\Events\OrderCancelled;
use App\Events\SpikeOccurred;
use App\Events\TimeAdvanced;
use App\Events\TransferCompleted;
use App\Events\StockoutOccurred;
use App\Interfaces\AiProviderInterface;
use App\Interfaces\RestockStrategyInterface;
use App\Listeners\DeductCash;
use App\Listeners\GenerateAlert;
use App\Listeners\UpdateInventory;
use App\Listeners\UpdateMetrics;
use App\Listeners\DecayPerishables;
use App\Listeners\ProcessDeliveries;
use App\Listeners\GenerateSpike;
use App\Listeners\CreateDailyReport;
use App\Listeners\GenerateStockoutAlert;
use App\Services\InventoryManagementService;
use App\Services\InventoryMathService;
use App\Services\PrismAiService;
use App\Services\Strategies\JustInTimeStrategy;
use App\Services\Strategies\SafetyStockStrategy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

use App\Services\SimulationService;
use App\Models\GameState;
use App\Models\User;

use App\Events\SpikeEnded;
use App\Listeners\ApplySpikeEffect;
use App\Listeners\RollbackSpikeEffect;

use App\Listeners\ApplyStorageCosts;

class GameServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind GameState as singleton based on auth user
        $this->app->singleton(GameState::class, function ($app) {
            $user = auth()->user();
            if (!$user) {
                // Fallback for tests or console if needed
                return new GameState(['day' => 1, 'cash' => 1000000, 'xp' => 0]);
            }
            return GameState::firstOrCreate(
                ['user_id' => $user->id],
                ['cash' => 1000000, 'xp' => 0, 'day' => 1]
            );
        });

        // Bind Simulation Service
        $this->app->singleton(SimulationService::class, function ($app) {
            return new SimulationService($app->make(GameState::class));
        });

        // Bind AI Provider
        $this->app->bind(AiProviderInterface::class, PrismAiService::class);

        // Bind stateless math service as singleton
        $this->app->singleton(InventoryMathService::class, function ($app) {
            return new InventoryMathService;
        });

        // Default Strategy for now (Just In Time)
        // In the future, this might be dynamic based on User Settings (Policy)
        $this->app->bind(RestockStrategyInterface::class, JustInTimeStrategy::class);

        // Bind Strategies explicitly for easier resolution when switching
        $this->app->bind(JustInTimeStrategy::class, function ($app) {
            return new JustInTimeStrategy;
        });

        $this->app->bind(SafetyStockStrategy::class, function ($app) {
            return new SafetyStockStrategy(
                $app->make(InventoryMathService::class)
            );
        });

        // Bind Management Service
        $this->app->bind(InventoryManagementService::class, function ($app) {
            return new InventoryManagementService(
                $app->make(RestockStrategyInterface::class),
                $app->make(InventoryMathService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Simulation Chain
        Event::listen(TimeAdvanced::class, [DecayPerishables::class, 'onTimeAdvanced']);
        Event::listen(TimeAdvanced::class, ApplyStorageCosts::class);
        Event::listen(TimeAdvanced::class, CreateDailyReport::class);
        Event::listen(TimeAdvanced::class, \App\Listeners\SnapshotInventoryLevels::class);

        // DAG Chain for OrderPlaced
        Event::listen(OrderPlaced::class, DeductCash::class);
        Event::listen(OrderPlaced::class, GenerateAlert::class);
        Event::listen(OrderPlaced::class, UpdateMetrics::class);

        // DAG Chain for OrderDelivered
        Event::listen(OrderDelivered::class, UpdateInventory::class);
        Event::listen(OrderDelivered::class, UpdateMetrics::class);

        // DAG Chain for OrderCancelled
        Event::listen(OrderCancelled::class, DeductCash::class);

        // DAG Chain for TransferCompleted
        Event::listen(TransferCompleted::class, GenerateAlert::class);
        Event::listen(TransferCompleted::class, UpdateInventory::class);
        Event::listen(TransferCompleted::class, UpdateMetrics::class);

        // DAG Chain for SpikeOccurred
        Event::listen(SpikeOccurred::class, GenerateAlert::class);
        Event::listen(SpikeOccurred::class, ApplySpikeEffect::class);

        // Stockout alerts
        Event::listen(StockoutOccurred::class, GenerateStockoutAlert::class);

        // DAG Chain for SpikeEnded
        Event::listen(SpikeEnded::class, RollbackSpikeEffect::class);
    }
}
