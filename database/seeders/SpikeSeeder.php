<?php

namespace Database\Seeders;

use App\Models\GameState;
use App\Services\SpikeConstraintChecker;
use App\Services\SpikeEventFactory;
use Illuminate\Database\Seeder;

/**
 * Seeds initial spikes for new games.
 * Generates 3-5 spikes distributed across Days 2-7 with type cooldown.
 */
class SpikeSeeder extends Seeder
{
    /**
     * Run the database seeds (for local/dev seeding).
     */
    public function run(): void
    {
        // Local/dev seeding only: if no GameState exists, skip.
        $gameState = GameState::query()->first();
        if (! $gameState) {
            return;
        }

        $this->seedInitialSpikes($gameState);
    }

    /**
     * Seed initial spikes for a game state.
     * Called both by seeder and by InitializeNewGame action.
     */
    public function seedInitialSpikes(GameState $gameState): void
    {
        $spikeCount = rand(3, 5);
        $availableDays = [2, 3, 4, 5, 6, 7];
        $selectedDays = collect($availableDays)
            ->random(min($spikeCount, count($availableDays)))
            ->sort()
            ->values();

        $lastUsedDayByType = []; // type => last start day
        $factory = app(SpikeEventFactory::class);
        $constraintChecker = app(SpikeConstraintChecker::class);

        foreach ($selectedDays as $day) {
            $allowedTypes = collect(SpikeConstraintChecker::ALL_SPIKE_TYPES)
                ->reject(function (string $type) use ($lastUsedDayByType, $day) {
                    return isset($lastUsedDayByType[$type])
                        && ($day - $lastUsedDayByType[$type]) <= SpikeConstraintChecker::TYPE_COOLDOWN_DAYS;
                })
                ->values()
                ->all();

            // If everything is blocked, relax cooldown for seeding (still keep cap enforcement)
            if (empty($allowedTypes)) {
                $allowedTypes = SpikeConstraintChecker::ALL_SPIKE_TYPES;
            }

            $duration = $this->getDurationThatFits($constraintChecker, $gameState, $day);
            if ($duration === null) {
                continue;
            }

            $spike = $factory->generateWithConstraints(
                userId: $gameState->user_id,
                allowedTypes: $allowedTypes,
                startDay: $day,
                duration: $duration,
                isGuaranteed: true
            );

            if ($spike) {
                $lastUsedDayByType[$spike->type] = $day;
            }
        }
    }

    private function getDurationThatFits(
        SpikeConstraintChecker $constraintChecker,
        GameState $gameState,
        int $startDay
    ): ?int {
        // Prefer shorter durations to keep cap room for remaining seeded spikes.
        foreach ([2, 3, 4, 5] as $duration) {
            if ($constraintChecker->canScheduleSpike($gameState, $startDay, $duration)) {
                return $duration;
            }
        }

        return null;
    }
}
