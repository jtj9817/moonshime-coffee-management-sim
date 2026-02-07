<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpikeEvent extends Model
{
    /** @use HasFactory<\Database\Factories\SpikeEventFactory> */
    use HasFactory, HasUuids;

    protected $appends = ['name', 'description'];

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'description',
        'magnitude',
        'duration',
        'location_id',
        'product_id',
        'affected_route_id',
        'parent_id',
        'starts_at_day',
        'ends_at_day',
        'is_active',
        'is_guaranteed',
        'meta',
        'acknowledged_at',
        'mitigated_at',
        'resolved_at',
        'resolved_by',
        'resolution_cost',
        'action_log',
    ];

    protected $casts = [
        'magnitude' => 'decimal:2',
        'duration' => 'integer',
        'starts_at_day' => 'integer',
        'ends_at_day' => 'integer',
        'is_active' => 'boolean',
        'is_guaranteed' => 'boolean',
        'meta' => 'array',
        'user_id' => 'integer',
        'acknowledged_at' => 'datetime',
        'mitigated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'resolution_cost' => 'integer',
        'action_log' => 'array',
    ];

    /**
     * Get the spike event name.
     * Returns stored name or generates one from type.
     */
    public function getNameAttribute(): string
    {
        if (!empty($this->attributes['name'])) {
            return $this->attributes['name'];
        }

        return match ($this->type) {
            'demand' => 'Demand Surge',
            'delay' => 'Delivery Delay',
            'price' => 'Price Spike',
            'breakdown' => 'Equipment Breakdown',
            'blizzard' => 'Blizzard Warning',
            default => ucfirst($this->type ?? 'Unknown') . ' Event',
        };
    }

    /**
     * Get the spike event description.
     * Returns stored description or generates one from attributes.
     */
    public function getDescriptionAttribute(): string
    {
        if (!empty($this->attributes['description'])) {
            return $this->attributes['description'];
        }

        return $this->generateDescription();
    }

    /**
     * Generate a human-readable description from spike attributes.
     */
    protected function generateDescription(): string
    {
        $parts = [];

        switch ($this->type) {
            case 'demand':
                $increase = round(((float) $this->magnitude - 1) * 100);
                $parts[] = "Demand increased by {$increase}%";
                break;
            case 'delay':
                $days = (int) $this->magnitude;
                $parts[] = "Deliveries delayed by {$days} day(s)";
                break;
            case 'price':
                $increase = round(((float) $this->magnitude - 1) * 100);
                $parts[] = "Prices increased by {$increase}%";
                break;
            case 'breakdown':
                $reduction = round((float) $this->magnitude * 100);
                $parts[] = "Capacity reduced by {$reduction}%";
                break;
            case 'blizzard':
                $parts[] = "Severe weather affecting routes";
                break;
            default:
                $parts[] = "Unknown disruption";
        }

        // Add context about affected location/product
        if ($this->relationLoaded('location') && $this->location) {
            $parts[] = "at {$this->location->name}";
        }
        if ($this->relationLoaded('product') && $this->product) {
            $parts[] = "affecting {$this->product->name}";
        }

        if ($this->duration) {
            $parts[] = "Duration: {$this->duration} days";
        }

        return implode('. ', $parts) . '.';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function affectedRoute(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'affected_route_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SpikeEvent::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SpikeEvent::class, 'parent_id');
    }

    // ========== Query Scopes ==========

    /**
     * Scope to active spikes only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to spikes resolved by player (not time).
     */
    public function scopeResolvedByPlayer(Builder $query): Builder
    {
        return $query->where('resolved_by', 'player');
    }

    // ========== Helper Methods ==========

    /**
     * Check if this spike type can be resolved early.
     * Only breakdown and blizzard support early resolution.
     */
    public function isResolvable(): bool
    {
        return in_array($this->type, ['breakdown', 'blizzard']);
    }

    /**
     * Calculate the estimated cost to resolve this spike early.
     * Cost formula: base cost × magnitude factor
     */
    public function getResolutionCostEstimateAttribute(): int
    {
        if (!$this->isResolvable()) {
            return 0;
        }

        $baseCost = match ($this->type) {
            'breakdown' => 50000, // $500.00 in cents
            'blizzard' => 75000,  // $750.00 in cents
            default => 0,
        };

        // Scale by magnitude (higher magnitude = higher cost)
        return (int) round($baseCost * max(1, (float) $this->magnitude));
    }

    /**
     * Get playbook data for this spike.
     */
    public function getPlaybook(): array
    {
        $actions = match ($this->type) {
            'demand' => [
                ['label' => 'Order More Stock', 'href' => '/game/ordering'],
                ['label' => 'Transfer Inventory', 'href' => '/game/transfers'],
            ],
            'delay' => [
                ['label' => 'View Affected Orders', 'href' => '/game/ordering'],
                ['label' => 'Order from Alternate Vendor', 'href' => '/game/ordering'],
            ],
            'price' => [
                ['label' => 'Review Order Costs', 'href' => '/game/ordering'],
                ['label' => 'Find Alternative Products', 'href' => '/game/ordering'],
            ],
            'breakdown' => [
                ['label' => 'Transfer Stock Out', 'href' => '/game/transfers'],
            ],
            'blizzard' => [
                ['label' => 'Check Route Status', 'href' => '/game/transfers'],
            ],
            default => [],
        };

        return [
            'description' => $this->description,
            'actions' => $actions,
            'canResolveEarly' => $this->isResolvable(),
            'resolutionCost' => $this->resolution_cost_estimate,
        ];
    }
}
