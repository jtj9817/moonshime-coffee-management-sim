<?php

namespace App\Providers;

use App\Interfaces\AiProviderInterface;
use App\Interfaces\RestockStrategyInterface;
use App\Services\InventoryManagementService;
use App\Services\InventoryMathService;
use App\Services\PrismAiService;
use App\Services\Strategies\JustInTimeStrategy;
use App\Services\Strategies\SafetyStockStrategy;
use Illuminate\Support\ServiceProvider;

class GameServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
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
        //
    }
}
