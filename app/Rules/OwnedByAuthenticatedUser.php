<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class OwnedByAuthenticatedUser implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  string|null  $customMessage  Optional custom error message
     */
    public function __construct(
        protected ?string $customMessage = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if (! $user) {
            $fail('No authenticated user found.');

            return;
        }

        $ownedLocationIds = $user->locations()
            ->pluck('locations.id')
            ->all();

        if (! in_array((string) $value, $ownedLocationIds, true)) {
            $fail($this->customMessage ?? "The selected {$attribute} is not available for your game state.");
        }
    }
}
