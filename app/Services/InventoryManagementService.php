<?php

namespace App\Services;

use App\Models\Inventory;
use App\Interfaces\RestockStrategyInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryManagementService
{
    public function __construct(
        protected RestockStrategyInterface $restockStrategy,
        protected InventoryMathService $mathService
    ) {}

    /**
     * Change the active restock strategy.
     */
    public function setStrategy(RestockStrategyInterface $strategy): void
    {
        $this->restockStrategy = $strategy;
    }

    /**
     * Restock inventory based on the active strategy or a fixed amount.
     */
    public function restock(Inventory $inventory, ?int $quantity = null, array $params = []): int
    {
        return DB::transaction(function () use ($inventory, $quantity, $params) {
            $amount = $quantity ?? $this->restockStrategy->calculateReorderAmount($inventory, $params);

            if ($amount <= 0) {
                return 0;
            }

            $inventory->increment('quantity', $amount);
            $inventory->update(['last_restocked_at' => now()]);

            return $amount;
        });
    }

    /**
     * Consume inventory (e.g., fulfilling an order).
     */
    public function consume(Inventory $inventory, int $quantity): void
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Consumption quantity must be positive.");
        }

        DB::transaction(function () use ($inventory, $quantity) {
            if ($inventory->quantity < $quantity) {
                // For now, we allow negative inventory (backorder) or throw exception?
                // Spec implies "Management Sim", usually you can't sell what you don't have.
                // However, blocking the thread might be annoying.
                // Let's prevent negative stock for now.
                throw new InvalidArgumentException("Insufficient stock to consume {$quantity} units.");
            }

            $inventory->decrement('quantity', $quantity);
        });
    }

    /**
     * Record wasted inventory (e.g., spoiled goods).
     */
    public function waste(Inventory $inventory, int $quantity, string $reason = 'spoilage'): void
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Waste quantity must be positive.");
        }

        DB::transaction(function () use ($inventory, $quantity) {
             if ($inventory->quantity < $quantity) {
                throw new InvalidArgumentException("Cannot waste more than available stock.");
            }

            $inventory->decrement('quantity', $quantity);
            
            // Future: Log waste event to database (Audit Log)
        });
    }
}