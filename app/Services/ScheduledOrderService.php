<?php

namespace App\Services;

use App\Events\OrderPlaced;
use App\Models\GameState;
use App\Models\ScheduledOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduledOrderService
{
    public function __construct(
        protected LogisticsService $logistics,
        protected OrderService $orderService,
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
            $estimatedTotal = $this->orderService->calculateOrderTotalCost(
                $user,
                $schedule->vendor,
                $items,
                $path,
            );
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
            DB::transaction(function () use ($schedule, $user, $items, $path, $day, $nextRunDay): void {
                $lockedSchedule = ScheduledOrder::query()
                    ->whereKey($schedule->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedSchedule instanceof ScheduledOrder) {
                    return;
                }

                if (! $lockedSchedule->is_active || $lockedSchedule->next_run_day > $day) {
                    return;
                }

                if (! $lockedSchedule->vendor || ! $lockedSchedule->location) {
                    throw new \RuntimeException('Scheduled order references missing vendor/source/destination.');
                }

                $order = $this->orderService->createOrder(
                    user: $user,
                    vendor: $lockedSchedule->vendor,
                    targetLocation: $lockedSchedule->location,
                    items: $items,
                    path: $path,
                    autoSubmit: $lockedSchedule->auto_submit,
                );

                if ($lockedSchedule->auto_submit) {
                    event(new OrderPlaced($order));
                }

                $lockedSchedule->update([
                    'last_run_day' => $day,
                    'next_run_day' => $nextRunDay,
                    'failure_reason' => null,
                ]);
            });
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
