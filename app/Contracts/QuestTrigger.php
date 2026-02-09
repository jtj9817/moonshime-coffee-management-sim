<?php

namespace App\Contracts;

use App\Models\User;

interface QuestTrigger
{
    /**
     * Calculate the current progress value for this trigger.
     *
     * @param  array<string, mixed>  $params  Trigger-specific parameters from quest config
     */
    public function currentValue(User $user, array $params = []): int;
}
