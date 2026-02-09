<?php

namespace App\Services;

use App\Events\SpikeEnded;
use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\SpikeEvent;
use App\Models\SpikeResolution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SpikeResolutionService
{
    /**
     * Resolve a spike early by paying the resolution cost.
     * Only breakdown and blizzard spikes can be resolved early.
     *
     * @throws \InvalidArgumentException If spike type is not resolvable
     * @throws \RuntimeException If insufficient funds
     */
    public function resolveEarly(SpikeEvent $spike): void
    {
        if (! $spike->isResolvable()) {
            throw new \InvalidArgumentException(
                "Spike type '{$spike->type}' cannot be resolved early. Only breakdown and blizzard spikes support early resolution."
            );
        }

        if (! $spike->is_active) {
            throw new \InvalidArgumentException('Spike is not currently active.');
        }

        $cost = (int) $spike->resolution_cost_estimate;
        $gameState = GameState::where('user_id', $spike->user_id)->firstOrFail();

        if ($gameState->cash < $cost) {
            throw new \RuntimeException(
                'Insufficient funds. Required: $'.number_format($cost / 100, 2).
                ', Available: $'.number_format($gameState->cash / 100, 2)
            );
        }

        DB::transaction(function () use ($spike, $gameState, $cost) {
            // Deduct cash
            $gameState->decrement('cash', $cost);

            // Get the current day from game state
            $currentDay = $gameState->day;

            // Update spike with resolution details
            $spike->update([
                'is_active' => false,
                'ends_at_day' => $currentDay, // Prevent re-activation
                'resolved_at' => now(),
                'resolved_by' => 'player',
                'resolution_cost' => $cost,
            ]);

            // Append to action log
            $actionLog = $spike->action_log ?? [];
            $actionLog[] = [
                'timestamp' => now()->toISOString(),
                'action' => 'resolved_early',
                'cost' => $cost,
            ];
            $spike->update(['action_log' => $actionLog]);

            // Create audit trail record
            SpikeResolution::create([
                'user_id' => $spike->user_id,
                'spike_event_id' => $spike->id,
                'action_type' => 'resolve_early',
                'cost_cents' => $cost,
                'effect' => ['spike_deactivated' => true, 'type' => $spike->type],
                'game_day' => $currentDay,
            ]);

            // Trigger rollback via event (reuses existing listener chain)
            event(new SpikeEnded($spike));
        });
    }

    /**
     * Mark a spike as mitigated and log the action.
     * Mitigation is available for all spike types.
     */
    public function mitigate(SpikeEvent $spike, string $action): void
    {
        if (! $spike->is_active) {
            throw new \InvalidArgumentException('Spike is not currently active.');
        }

        DB::transaction(function () use ($spike, $action) {
            $actionLog = $spike->action_log ?? [];
            $actionLog[] = [
                'timestamp' => now()->toISOString(),
                'action' => $action,
            ];

            $meta = $spike->meta ?? [];
            $meta['mitigation_count'] = ($meta['mitigation_count'] ?? 0) + 1;

            if (! isset($meta['original_magnitude'])) {
                $meta['original_magnitude'] = (float) $spike->magnitude;
            }

            $updates = [
                'mitigated_at' => now(),
                'action_log' => $actionLog,
                'meta' => $meta,
            ];

            switch ($spike->type) {
                case 'demand':
                case 'price':
                    $newMagnitude = max(1.0, round(((float) $spike->magnitude) * 0.8, 2));
                    $updates['magnitude'] = $newMagnitude;
                    $meta['mitigated_magnitude'] = $newMagnitude;
                    break;
                case 'delay':
                    $currentDelay = (int) round((float) $spike->magnitude);
                    $newDelay = max(0, $currentDelay - 1);
                    $this->applyDelayMitigation($spike, $newDelay);
                    $updates['magnitude'] = $newDelay;
                    $meta['delay_days'] = $newDelay;
                    break;
                case 'breakdown':
                    $newMagnitude = max(0.0, round(((float) $spike->magnitude) * 0.8, 2));
                    $this->applyBreakdownMitigation($spike, $newMagnitude, $meta);
                    $updates['magnitude'] = $newMagnitude;
                    $meta['mitigated_magnitude'] = $newMagnitude;
                    break;
                case 'blizzard':
                    $this->applyBlizzardMitigation($spike);
                    $meta['mitigated_route'] = true;
                    break;
                default:
                    break;
            }

            $updates['meta'] = $meta;

            $spike->update($updates);

            // Create audit trail record
            $gameState = GameState::where('user_id', $spike->user_id)->first();
            SpikeResolution::create([
                'user_id' => $spike->user_id,
                'spike_event_id' => $spike->id,
                'action_type' => 'mitigate',
                'action_detail' => $action,
                'cost_cents' => 0,
                'effect' => ['type' => $spike->type, 'new_magnitude' => $spike->magnitude],
                'game_day' => $gameState ? $gameState->day : 0,
            ]);
        });
    }

    /**
     * Mark a spike as acknowledged (player viewed it).
     */
    public function acknowledge(SpikeEvent $spike): void
    {
        if ($spike->acknowledged_at) {
            return; // Already acknowledged
        }

        $actionLog = $spike->action_log ?? [];
        $actionLog[] = [
            'timestamp' => now()->toISOString(),
            'action' => 'acknowledged',
        ];

        $spike->update([
            'acknowledged_at' => now(),
            'action_log' => $actionLog,
        ]);

        // Create audit trail record
        $gameState = GameState::where('user_id', $spike->user_id)->first();
        SpikeResolution::create([
            'user_id' => $spike->user_id,
            'spike_event_id' => $spike->id,
            'action_type' => 'acknowledge',
            'cost_cents' => 0,
            'game_day' => $gameState ? $gameState->day : 0,
        ]);
    }

    protected function applyDelayMitigation(SpikeEvent $spike, int $delayDays): void
    {
        $meta = $spike->meta ?? [];
        $affectedOrders = $meta['affected_orders'] ?? [];

        foreach ($affectedOrders as $orderId => $originals) {
            $order = Order::find($orderId);
            if (! $order) {
                continue;
            }

            $updates = [];
            if (isset($originals['original_delivery_day'])) {
                $updates['delivery_day'] = $originals['original_delivery_day'] !== null
                    ? $originals['original_delivery_day'] + $delayDays
                    : null;
            }

            if (array_key_exists('original_delivery_date', $originals)) {
                $updates['delivery_date'] = $originals['original_delivery_date']
                    ? Carbon::parse($originals['original_delivery_date'])->addDays($delayDays)
                    : null;
            }

            if (! empty($updates)) {
                $order->update($updates);
            }
        }
    }

    protected function applyBreakdownMitigation(SpikeEvent $spike, float $newMagnitude, array &$meta): void
    {
        if (! $spike->location_id) {
            return;
        }

        $location = Location::find($spike->location_id);
        if (! $location) {
            return;
        }

        if (! isset($meta['original_max_storage'])) {
            $meta['original_max_storage'] = $location->max_storage;
        }

        $originalCapacity = (int) $meta['original_max_storage'];
        $newCapacity = (int) round($originalCapacity * (1 - $newMagnitude));
        $newCapacity = max(0, $newCapacity);

        $location->update(['max_storage' => $newCapacity]);
    }

    protected function applyBlizzardMitigation(SpikeEvent $spike): void
    {
        $spike->loadMissing('affectedRoute');
        $route = $spike->affectedRoute;

        if ($route && ! $route->is_active) {
            $route->update(['is_active' => true]);
        }
    }
}
