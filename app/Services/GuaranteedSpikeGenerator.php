<?php

namespace App\Services;

use App\Models\GameState;
use App\Models\SpikeEvent;

/**
 * Generates a guaranteed spike for the current day.
 * Respects constraints (cap, cooldown) with fallback logic.
 */
class GuaranteedSpikeGenerator
{
    public function __construct(
        protected SpikeConstraintChecker $constraintChecker,
        protected SpikeEventFactory $factory
    ) {}

    /**
     * Generate a guaranteed spike for the current day.
     * Returns null only if constraints prevent generation.
     */
    public function generate(GameState $gameState, int $currentDay): ?SpikeEvent
    {
        // Tutorial grace period - no guaranteed spikes on Day 1
        if ($currentDay <= 1) {
            return null;
        }

        // 1. Check if we can generate (under cap)
        // NOTE: cap must be enforced against the spike's *full window* to avoid future overlap
        $duration = rand(2, 5);
        if (!$this->constraintChecker->canScheduleSpike($gameState, $currentDay, $duration)) {
            return null; // At capacity
        }

        // 2. Get allowed types (respecting cooldown)
        $allowedTypes = $this->constraintChecker->getAllowedTypes($gameState, $currentDay);

        if (empty($allowedTypes)) {
            // Guarantee > cooldown: relax cooldown as a last resort
            $allowedTypes = SpikeConstraintChecker::ALL_SPIKE_TYPES;
        }

        // 3. Generate spike with allowed type, starting TODAY
        $spike = $this->factory->generateWithConstraints(
            userId: $gameState->user_id,
            allowedTypes: $allowedTypes,
            startDay: $currentDay,
            duration: $duration,
            isGuaranteed: true
        );

        return $spike;
    }
}
