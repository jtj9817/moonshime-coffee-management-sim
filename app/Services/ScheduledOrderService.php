<?php

namespace App\Services;

use App\Events\OrderPlaced;
use App\Models\GameState;
use App\Models\Location;
use App\Models\ScheduledOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ScheduledOrderService
{
    public function __construct(
        protected LogisticsService $logistics,
        protected OrderService $orderService,
        protected PricingService $pricingService,
    ) {}

    /**
     * Process due scheduled orders for the current user/day.
     */
    public function processDueSchedules(GameState $gameState, int $day): void
    {
        ScheduledOrder::with(['vendor', 'sourceLocation', 'location'])
            ->where('user_id', $gameState->user_id)
            ->where('is_active', true)
            ->where('next_run_day', '<=', $day)
            ->orderBy('next_run_day')
            ->get()
            ->each(fn (ScheduledOrder $schedule) => $this->processSchedule($schedule, $gameState, $day));
    }

    /**
     * Execute a single schedule and advance its run cursor.
     */
    protected function processSchedule(ScheduledOrder $schedule, GameState $gameState, int $day): void
    {
        $nextRunDay = $this->resolveNextRunDay($schedule, $day);
        $user = $gameState->user;

        if (! $user instanceof User) {
            $this->markFailure($schedule, $day, $nextRunDay, 'User context missing for scheduled order.');

            return;
        }

        if (! $schedule->vendor || ! $schedule->sourceLocation || ! $schedule->location) {
            $this->markFailure($schedule, $day, $nextRunDay, 'Scheduled order references missing vendor/source/destination.');

            return;
        }

        $path = $this->logistics
            ->forUser($schedule->user_id)
            ->findBestRoute($schedule->sourceLocation, $schedule->location);

        if (! $path || $path->isEmpty()) {
            $this->markFailure($schedule, $day, $nextRunDay, 'No active route is available for scheduled order.');

            return;
        }

        $items = $this->normalizeItems($schedule->items ?? []);
        if (empty($items)) {
            $this->markFailure($schedule, $day, $nextRunDay, 'Scheduled order has no valid line items.');

            return;
        }

        $totalQuantity = collect($items)->sum('quantity');
        $minCapacity = $path->min('capacity');

        if ($schedule->auto_submit && $minCapacity !== null && $totalQuantity > (int) $minCapacity) {
            $this->markFailure(
                $schedule,
                $day,
                $nextRunDay,
                "Route capacity ({$minCapacity}) is below scheduled quantity ({$totalQuantity})."
            );

            return;
        }

        if ($schedule->auto_submit) {
            $estimatedTotal = $this->estimateTotalCost($user, $schedule, $path, $items);
            if ($gameState->cash < $estimatedTotal) {
                $this->markFailure(
                    $schedule,
                    $day,
                    $nextRunDay,
                    "Insufficient funds for auto-submit ({$estimatedTotal} cents required)."
                );

                return;
            }
        }

        try {
            $order = $this->orderService->createOrder(
                user: $user,
                vendor: $schedule->vendor,
                targetLocation: $schedule->location,
                items: $items,
                path: $path,
                autoSubmit: $schedule->auto_submit,
            );

            if ($schedule->auto_submit) {
                event(new OrderPlaced($order));
            }

            $schedule->update([
                'last_run_day' => $day,
                'next_run_day' => $nextRunDay,
                'failure_reason' => null,
            ]);
        } catch (\Throwable $throwable) {
            $this->markFailure($schedule, $day, $nextRunDay, $throwable->getMessage());
        }
    }

    /**
     * Convert JSON payload to OrderService item contract.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{product_id: string, quantity: int, cost_per_unit: int}>
     */
    protected function normalizeItems(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => isset($item['product_id'], $item['quantity']))
            ->map(fn ($item) => [
                'product_id' => (string) $item['product_id'],
                'quantity' => max(0, (int) ($item['quantity'] ?? 0)),
                'cost_per_unit' => max(0, (int) ($item['unit_price'] ?? 0)),
            ])
            ->filter(fn (array $item) => $item['quantity'] > 0)
            ->values()
            ->all();
    }

    /**
     * Estimate total in cents, including pricing multipliers and logistics.
     *
     * @param  array<int, array{product_id: string, quantity: int, cost_per_unit: int}>  $items
     */
    protected function estimateTotalCost(
        User $user,
        ScheduledOrder $schedule,
        Collection $path,
        array $items
    ): int {
        $itemsCost = collect($items)->sum(function (array $item) use ($user, $schedule) {
            $multiplier = $this->pricingService->getPriceMultiplierFor(
                $user,
                $item['product_id'],
                $schedule->vendor_id,
            );

            return (int) round($item['quantity'] * $item['cost_per_unit'] * $multiplier);
        });

        $shippingCost = (int) $path->sum(fn ($route) => $this->logistics->calculateCost($route));

        return (int) $itemsCost + $shippingCost;
    }

    /**
     * Resolve next run day from interval or cron fallback.
     */
    protected function resolveNextRunDay(ScheduledOrder $schedule, int $day): int
    {
        if ($schedule->interval_days && $schedule->interval_days > 0) {
            return $day + (int) $schedule->interval_days;
        }

        $cronInterval = $this->resolveCronIntervalDays($schedule->cron_expression);

        return $day + ($cronInterval ?? 1);
    }

    /**
     * Minimal cron support for `@every Nd` patterns.
     */
    protected function resolveCronIntervalDays(?string $cronExpression): ?int
    {
        if (! $cronExpression) {
            return null;
        }

        if (preg_match('/^@every\\s+(\\d+)d$/', trim($cronExpression), $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return null;
    }

    protected function markFailure(
        ScheduledOrder $schedule,
        int $day,
        int $nextRunDay,
        string $message
    ): void {
        $schedule->update([
            'last_run_day' => $day,
            'next_run_day' => $nextRunDay,
            'failure_reason' => Str::limit($message, 255),
        ]);
    }
}
