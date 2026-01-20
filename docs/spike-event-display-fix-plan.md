# Spike Event Display Fix Plan

**Created**: 2026-01-19
**Completed**: 2026-01-19
**Status**: ✅ Implemented
**Purpose**: Fix missing name and description fields in War Room spike event display

---

## Problem Statement

The "War Room" view (`/game/spike-history`) displays spike events but shows empty/undefined values for the event name and description columns.

1. **Missing Database Columns**: The `spike_events` table lacks `name` and `description` columns
2. **Frontend/Backend Mismatch**: TypeScript `SpikeEventModel` expects fields that backend doesn't provide
3. **No Computed Fallbacks**: Model has no accessors to generate human-readable display values from raw data

The result is a broken UI where critical spike information is invisible to players.

---

## Design Decisions (Architecture Preferences)

| Decision | Choice |
| :--- | :--- |
| Storage Strategy | Database columns + computed fallbacks |
| Fallback Behavior | Model accessors generate values from type/magnitude |
| Eager Loading | Always load `location`, `product`, `affectedRoute` |
| Migration Safety | Nullable columns for backward compatibility |

---

## Solution Architecture

### Overview
```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  SpikeFactory   │────▶│   SpikeEvent     │────▶│  GameController │
│  (creates with  │     │   Model          │     │  (eager loads   │
│   name/desc)    │     │   (accessors)    │     │   relationships)│
└─────────────────┘     └──────────────────┘     └─────────────────┘
                               │                         │
                               ▼                         ▼
                        ┌──────────────────┐     ┌─────────────────┐
                        │  Database        │     │  Frontend       │
                        │  (name, desc     │     │  (renders       │
                        │   columns)       │     │   spike.name)   │
                        └──────────────────┘     └─────────────────┘
```

---

## Implementation Tasks

### Phase 1: Database Migration ✅

#### Task 1.1: Add name and description columns ⬜
**File**: `database/migrations/2026_01_20_010000_add_name_description_to_spike_events_table.php`

```php
Schema::table('spike_events', function (Blueprint $table) {
    $table->string('name')->nullable()->after('type');
    $table->text('description')->nullable()->after('name');
});
```

**Key Logic**:
* Nullable columns preserve existing data
* Placed after `type` for logical column ordering

---

### Phase 2: Model Computed Accessors ✅

#### Task 2.1: Add name accessor ✅
**File**: `app/Models/SpikeEvent.php`

```php
protected $appends = ['name', 'description'];

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
        default => ucfirst($this->type) . ' Event',
    };
}
```

#### Task 2.2: Add description accessor ✅
**File**: `app/Models/SpikeEvent.php`

```php
public function getDescriptionAttribute(): string
{
    if (!empty($this->attributes['description'])) {
        return $this->attributes['description'];
    }
    
    return $this->generateDescription();
}

protected function generateDescription(): string
{
    $parts = [];
    
    switch ($this->type) {
        case 'demand':
            $increase = round(($this->magnitude - 1) * 100);
            $parts[] = "Demand increased by {$increase}%";
            break;
        case 'delay':
            $days = (int) $this->magnitude;
            $parts[] = "Deliveries delayed by {$days} day(s)";
            break;
        case 'price':
            $increase = round(($this->magnitude - 1) * 100);
            $parts[] = "Prices increased by {$increase}%";
            break;
        case 'breakdown':
            $reduction = round($this->magnitude * 100);
            $parts[] = "Capacity reduced by {$reduction}%";
            break;
        case 'blizzard':
            $parts[] = "Severe weather affecting routes";
            break;
    }
    
    if ($this->location) {
        $parts[] = "at {$this->location->name}";
    }
    if ($this->product) {
        $parts[] = "affecting {$this->product->name}";
    }
    
    $parts[] = "Duration: {$this->duration} days";
    
    return implode('. ', $parts) . '.';
}
```

---

### Phase 3: Factory Updates ✅ (skipped - model accessors handle generation)

#### Task 3.1: Populate name/description at creation ✅ (model accessors)
**File**: `app/Services/SpikeEventFactory.php`

```php
return SpikeEvent::create([
    'user_id' => $userId,
    'type' => $type,
    'name' => $this->generateName($type),
    'description' => $this->generateDescription($type, $magnitude, ...),
    // ... existing fields
]);
```

---

### Phase 4: Controller Updates ✅

#### Task 4.1: Eager load relationships ✅
**File**: `app/Http/Controllers/GameController.php`

```php
public function spikeHistory(): Response
{
    $spikes = SpikeEvent::with(['location', 'product', 'affectedRoute'])
        ->orderBy('created_at', 'desc')
        ->get();

    return Inertia::render('game/spike-history', [
        'spikes' => $spikes,
        'statistics' => $this->getSpikeStatistics(),
    ]);
}
```

---

### Phase 5: TypeScript Updates ✅

#### Task 5.1: Extend SpikeEventModel interface ✅
**File**: `resources/js/types/index.d.ts`

```typescript
export interface SpikeEventModel {
    id: string;
    type: string;
    name: string;
    description: string;
    is_active: boolean;
    magnitude: number;
    duration: number;
    starts_at_day: number;
    ends_at_day: number;
    location?: LocationModel | null;
    product?: ProductModel | null;
    meta: Record<string, unknown> | null;
    created_at: string;
}
```

---

## File Summary

| File | Action | Status |
| :--- | :--- | :--- |
| `database/migrations/2026_01_20_...` | Create | ✅ |
| `app/Models/SpikeEvent.php` | Modify | ✅ |
| `app/Services/SpikeEventFactory.php` | — | Skipped |
| `app/Http/Controllers/GameController.php` | Modify | ✅ |
| `resources/js/types/index.d.ts` | Modify | ✅ |

---

## Execution Order

1. **Create migration** — Add nullable `name` and `description` columns
2. **Run migration** — `php artisan migrate`
3. **Update model** — Add `$appends`, `$fillable`, and accessors
4. **Update factory** — Generate name/description at creation time
5. **Update controller** — Add eager loading for relationships
6. **Update TypeScript** — Extend interface with new fields
7. **Verify UI** — Check War Room displays correctly

---

## Edge Cases to Handle

1. **Existing spikes without name/desc**: Model accessors generate fallback values ⬜
2. **Missing location/product relationships**: Description generation checks for null ⬜
3. **Unknown spike types**: Default case in match expression handles gracefully ⬜

---

## Rollback Plan

1. Run `php artisan migrate:rollback` to remove columns
2. Revert model changes (remove accessors and `$appends`)
3. Revert factory changes
4. Revert controller eager loading
5. Revert TypeScript interface

---

## Success Criteria

- [ ] Migration runs without errors
- [ ] All existing spikes display with generated names
- [ ] All existing spikes display with generated descriptions
- [ ] New spikes are created with persisted name/description
- [ ] War Room table shows all columns populated
- [ ] No N+1 query issues (eager loading works)

---

## Implementation Walkthrough

*To be completed after implementation.*
