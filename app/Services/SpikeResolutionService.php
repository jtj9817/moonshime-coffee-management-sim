<?php

namespace App\Services;

use App\Events\SpikeEnded;
use App\Models\GameState;
use App\Models\SpikeEvent;
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
        if (!$spike->isResolvable()) {
            throw new \InvalidArgumentException(
                "Spike type '{$spike->type}' cannot be resolved early. Only breakdown and blizzard spikes support early resolution."
            );
        }

        if (!$spike->is_active) {
            throw new \InvalidArgumentException('Spike is not currently active.');
        }

        $cost = $spike->resolution_cost_estimate;
        $costDollars = round($cost / 100, 2);
        $gameState = GameState::where('user_id', $spike->user_id)->firstOrFail();

        if ($gameState->cash < $costDollars) {
            throw new \RuntimeException(
                "Insufficient funds. Required: $" . number_format($costDollars, 2) .
                ", Available: $" . number_format((float) $gameState->cash, 2)
            );
        }

        DB::transaction(function () use ($spike, $gameState, $cost, $costDollars) {
            // Deduct cash
            $gameState->decrement('cash', $costDollars);

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
        if (!$spike->is_active) {
            throw new \InvalidArgumentException('Spike is not currently active.');
        }

        $actionLog = $spike->action_log ?? [];
        $actionLog[] = [
            'timestamp' => now()->toISOString(),
            'action' => $action,
        ];

        $spike->update([
            'mitigated_at' => now(),
            'action_log' => $actionLog,
        ]);
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
    }
}
