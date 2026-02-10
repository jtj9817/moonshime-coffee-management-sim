<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduledOrderFactory> */
    use HasFactory, HasUuids;

    /**
     * Regex pattern for cron expressions in the format "@every Nd" (e.g., "@every 3d").
     */
    public const CRON_REGEX = '/^@every\s+(\d+)d$/';

    protected $fillable = [
        'user_id',
        'vendor_id',
        'source_location_id',
        'location_id',
        'items',
        'next_run_day',
        'interval_days',
        'cron_expression',
        'auto_submit',
        'is_active',
        'last_run_day',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'next_run_day' => 'integer',
            'interval_days' => 'integer',
            'auto_submit' => 'boolean',
            'is_active' => 'boolean',
            'last_run_day' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
