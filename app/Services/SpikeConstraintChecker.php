<?php

namespace App\Services;

use App\Models\GameState;
use App\Models\SpikeEvent;

/**
 * Enforces constraints for spike generation:
 * - Max concurrent active spikes (cap = 2)
 * - Type cooldown (same type cannot repeat within 2 days)
 */
class SpikeConstraintChecker
{
    public const MAX_ACTIVE_SPIKES = 2;

    public const TYPE_COOLDOWN_DAYS = 2;

    public const ALL_SPIKE_TYPES = ['demand', 'delay', 'price', 'breakdown', 'blizzard'];

    /**
     * Check if a spike can be scheduled starting on a given day with a given duration.
     * The cap must be enforced against the spike's full window to avoid future overlap.
     */
    public function canScheduleSpike(GameState $gameState, int $startDay, int $duration): bool
    {
        $endDay = $startDay + $duration;

        // Check each day in the spike's active window
        for ($day = $startDay; $day < $endDay; $day++) {
            $count = $this->getSpikeCountCoveringDay($gameState->user_id, $day);
            if ($count >= self::MAX_ACTIVE_SPIKES) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get count of spikes covering a specific day (scheduled or active).
     * A spike covers a day if starts_at_day <= day < ends_at_day.
     */
    public function getSpikeCountCoveringDay(int $userId, int $day): int
    {
        return SpikeEvent::where('user_id', $userId)
            ->where('starts_at_day', '<=', $day)
            ->where('ends_at_day', '>', $day)
            ->count();
    }

    /**
     * Get list of allowed spike types for a given start day.
     * Respects both historical cooldowns and already-scheduled spikes.
     */
    public function getAllowedTypes(GameState $gameState, int $startDay): array
    {
        $cooldowns = $gameState->spike_cooldowns ?? [];

        // Get types of scheduled spikes within the cooldown window (past and future)
        $scheduledTypes = SpikeEvent::where('user_id', $gameState->user_id)
            ->whereBetween('starts_at_day', [
                $startDay - self::TYPE_COOLDOWN_DAYS,
                $startDay + self::TYPE_COOLDOWN_DAYS,
            ])
            ->pluck('type')
            ->unique()
            ->toArray();

        return collect(self::ALL_SPIKE_TYPES)
            ->reject(function (string $type) use ($cooldowns, $startDay, $scheduledTypes) {
                // Reject if in scheduled cooldown
                if (in_array($type, $scheduledTypes)) {
                    return true;
                }

                // Reject if in historical cooldown
                if (isset($cooldowns[$type])) {
                    $lastStartDay = $cooldowns[$type];
                    if (($startDay - $lastStartDay) <= self::TYPE_COOLDOWN_DAYS) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /**
     * Record that a spike of a given type has started on a given day.
     * Updates the cooldown tracking in GameState.
     */
    public function recordSpikeStarted(GameState $gameState, string $type, int $startDay): void
    {
        $cooldowns = $gameState->spike_cooldowns ?? [];
        $cooldowns[$type] = $startDay;

        $gameState->update(['spike_cooldowns' => $cooldowns]);
    }
}
