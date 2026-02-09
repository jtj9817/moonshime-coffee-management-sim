<?php

namespace App\Services;

use App\Contracts\QuestTrigger;
use App\Models\GameState;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserQuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestService
{
    /**
     * Check all active quest triggers for a user and grant rewards on completion.
     *
     * @return array<string, mixed> Summary of completed quests in this check
     */
    public function checkTriggers(User $user): array
    {
        $quests = Quest::where('is_active', true)
            ->whereNotNull('trigger_class')
            ->get();

        if ($quests->isEmpty()) {
            return ['completed' => []];
        }

        $gameState = GameState::where('user_id', $user->id)->first();
        if (! $gameState) {
            return ['completed' => []];
        }

        $completedThisCheck = [];

        foreach ($quests as $quest) {
            $userQuest = UserQuest::firstOrCreate(
                ['user_id' => $user->id, 'quest_id' => $quest->id],
                ['current_value' => 0, 'is_completed' => false, 'created_day' => $gameState->day]
            );

            if ($userQuest->is_completed) {
                continue;
            }

            $trigger = $this->resolveTrigger($quest->trigger_class);
            if (! $trigger) {
                continue;
            }

            $currentValue = $trigger->currentValue($user, $quest->trigger_params ?? []);
            $wasCompleted = false;

            if ($currentValue >= $quest->target_value) {
                DB::transaction(function () use ($quest, $userQuest, $currentValue, $gameState, &$wasCompleted) {
                    $userQuest->update([
                        'current_value' => $currentValue,
                        'is_completed' => true,
                        'completed_at' => now(),
                    ]);

                    // Grant rewards
                    if ($quest->reward_cash_cents > 0) {
                        $gameState->increment('cash', $quest->reward_cash_cents);
                    }
                    if ($quest->reward_xp > 0) {
                        $gameState->increment('xp', $quest->reward_xp);
                    }

                    $wasCompleted = true;
                });

                if ($wasCompleted) {
                    $completedThisCheck[] = [
                        'quest_id' => $quest->id,
                        'title' => $quest->title,
                        'reward_cash_cents' => $quest->reward_cash_cents,
                        'reward_xp' => $quest->reward_xp,
                    ];
                }
            } else {
                if ($userQuest->current_value !== $currentValue) {
                    $userQuest->update(['current_value' => $currentValue]);
                }
            }
        }

        return ['completed' => $completedThisCheck];
    }

    /**
     * Get active quests with user progress for display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveQuestsForUser(User $user): array
    {
        $quests = Quest::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($quests->isEmpty()) {
            return [];
        }

        $gameState = $user->gameState ?: GameState::firstOrCreate(
            ['user_id' => $user->id],
            ['cash' => 1000000, 'xp' => 0, 'day' => 1]
        );

        $userQuests = UserQuest::where('user_id', $user->id)
            ->whereIn('quest_id', $quests->pluck('id'))
            ->get()
            ->keyBy('quest_id');

        $questPayloads = [];

        foreach ($quests as $quest) {
            $userQuest = $userQuests->get($quest->id);

            if (! $userQuest) {
                $userQuest = UserQuest::create([
                    'user_id' => $user->id,
                    'quest_id' => $quest->id,
                    'current_value' => 0,
                    'is_completed' => false,
                    'created_day' => $gameState->day,
                ]);
            }

            $currentValue = $this->calculateProgress($quest, $user);
            $updates = [];

            if ($userQuest->current_value !== $currentValue) {
                $updates['current_value'] = $currentValue;
            }

            if (! $userQuest->is_completed && $currentValue >= $quest->target_value) {
                $updates['is_completed'] = true;
                $updates['completed_at'] = now();
            }

            if (! empty($updates)) {
                $userQuest->update($updates);
            }

            $questPayloads[] = [
                'id' => $quest->id,
                'type' => $quest->type,
                'title' => $quest->title,
                'description' => $quest->description,
                'reward' => [
                    'xp' => $quest->reward_xp,
                    'cash' => $quest->reward_cash_cents > 0
                        ? round($quest->reward_cash_cents / 100, 2)
                        : 0,
                ],
                'targetValue' => $quest->target_value,
                'currentValue' => $currentValue,
                'isCompleted' => $userQuest->is_completed || ($currentValue >= $quest->target_value),
            ];
        }

        return $questPayloads;
    }

    /**
     * Resolve a trigger class string to an instance.
     */
    protected function resolveTrigger(string $triggerClass): ?QuestTrigger
    {
        if (! class_exists($triggerClass)) {
            Log::warning("Quest trigger class not found: {$triggerClass}");

            return null;
        }

        $instance = app($triggerClass);

        if (! $instance instanceof QuestTrigger) {
            Log::warning("Quest trigger class does not implement QuestTrigger: {$triggerClass}");

            return null;
        }

        return $instance;
    }

    protected function calculateProgress(Quest $quest, User $user): int
    {
        // Use trigger_class if available, fall back to legacy type-based calculation
        if ($quest->trigger_class) {
            $trigger = $this->resolveTrigger($quest->trigger_class);
            if ($trigger) {
                return $trigger->currentValue($user, $quest->trigger_params ?? []);
            }
        }

        return match ($quest->type) {
            'inventory' => $this->calculateInventoryMinProgress($user),
            default => 0,
        };
    }

    protected function calculateInventoryMinProgress(User $user): int
    {
        $totals = \App\Models\Inventory::where('user_id', $user->id)
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('product_id')
            ->pluck('total_quantity');

        if ($totals->isEmpty()) {
            return 0;
        }

        return (int) $totals->min();
    }
}
