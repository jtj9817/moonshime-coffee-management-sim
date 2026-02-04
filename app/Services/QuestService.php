<?php

namespace App\Services;

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserQuest;
use Illuminate\Support\Facades\DB;

class QuestService
{
    /**
     * Get active quests with user progress.
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

            if (!$userQuest) {
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

            if (!$userQuest->is_completed && $currentValue >= $quest->target_value) {
                $updates['is_completed'] = true;
                $updates['completed_at'] = now();
            }

            if (!empty($updates)) {
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

    protected function calculateProgress(Quest $quest, User $user): int
    {
        return match ($quest->type) {
            'inventory' => $this->calculateInventoryMinProgress($user),
            default => 0,
        };
    }

    protected function calculateInventoryMinProgress(User $user): int
    {
        $totals = Inventory::where('user_id', $user->id)
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('product_id')
            ->pluck('total_quantity');

        if ($totals->isEmpty()) {
            return 0;
        }

        return (int) $totals->min();
    }
}
