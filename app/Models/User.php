<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function gameState(): HasOne
    {
        return $this->hasOne(GameState::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'user_locations')->withTimestamps();
    }

    /**
     * Ensure owned locations include inventory locations plus all vendor locations.
     *
     * @return int Number of newly attached locations.
     */
    public function syncLocations(): int
    {
        $inventoryLocationIds = Inventory::query()
            ->where('user_id', $this->id)
            ->distinct()
            ->pluck('location_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $attachedCount = 0;

        if ($inventoryLocationIds !== []) {
            $changes = $this->locations()->syncWithoutDetaching($inventoryLocationIds);
            $attachedCount += count($changes['attached'] ?? []);
        }

        $vendorBatch = [];
        foreach (Location::query()->where('type', 'vendor')->select('id')->cursor() as $vendorLocation) {
            $vendorBatch[] = (string) $vendorLocation->id;

            if (count($vendorBatch) < 500) {
                continue;
            }

            $changes = $this->locations()->syncWithoutDetaching($vendorBatch);
            $attachedCount += count($changes['attached'] ?? []);
            $vendorBatch = [];
        }

        if ($vendorBatch !== []) {
            $changes = $this->locations()->syncWithoutDetaching($vendorBatch);
            $attachedCount += count($changes['attached'] ?? []);
        }

        return $attachedCount;
    }
}
