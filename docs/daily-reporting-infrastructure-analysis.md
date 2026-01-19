# Daily Reporting Infrastructure Analysis

## Context

This document analyzes the current state of daily reporting and day transition event tracking in the Moonshine Coffee Management Sim. The analysis was prompted by the following questions:

1. **Dashboard Day Transition Reporting**: Is there currently logic for the Dashboard to show what transpired from the previous day and what events happened in the transition between days? For example, a report that shows what happened in Day 1 and what events transpired from Day 1 to Day 2.

2. **Backend Infrastructure Support**: Does current back-end infrastructure support functionalities for creating daily summary report, transition events log, and daily recap logs?

---

## Current State: Dashboard Day Transition Reporting

### What Currently Exists

The Dashboard displays the following real-time information:

- **Active Alerts**: Current alerts sorted by severity (critical/warning/info)
- **KPIs**: Inventory health, risk value, active alerts count, projected waste
- **Location Status**: Per-location inventory health and alert status
- **Active Quests**: Current quests with progress tracking
- **Current Spike**: If a spike is currently active

### Day Transition Logic

The `SimulationService::advanceTime()` method handles day transitions with three tick phases:

```php
public function advanceTime(): void
{
    \Illuminate\Support\Facades\DB::transaction(function () {
        $this->gameState->increment('day');
        $day = $this->gameState->day;

        $this->processEventTick($day);      // Spike lifecycle
        $this->processPhysicsTick($day);    // Deliveries/transfers
        $this->processAnalysisTick($day);    // Isolation alerts

        event(new TimeAdvanced($day, $this->gameState));
    });
}
```

**Event Tick**: Activates/deactivates spikes based on `starts_at_day` and `ends_at_day`
**Physics Tick**: Processes deliveries for orders (`Shipped` → `Delivered`) and transfers (`InTransit` → `Completed`)
**Analysis Tick**: Runs BFS pathfinding and generates isolation alerts

### What's Missing

**No Daily Summary Report Component**
- No dashboard section showing "What happened on Day N"
- No transition event log showing events that occurred between Day N and Day N+1

**No Daily Recap UI**
- The `SpikeHistory` page exists but only shows spike events, not comprehensive daily activities
- `PostMortemModal` exists for spike feedback but doesn't provide a full daily narrative

**No Historical Activity Tracking**
- Current state is displayed, but no historical context
- Cannot see "On Day 3, you placed 2 orders, completed 1 transfer, and resolved 2 alerts"

---

## Backend Infrastructure Assessment

### Event System (Strong Foundation) ✅

| Event | Purpose | Listeners |
|-------|---------|-----------|
| `TimeAdvanced` | Fired when day increments | `DecayPerishables`, `ApplyStorageCosts` |
| `OrderPlaced` | New order created | `DeductCash`, `GenerateAlert`, `UpdateMetrics` |
| `OrderDelivered` | Order arrives | `UpdateInventory`, `UpdateMetrics` |
| `TransferCompleted` | Transfer arrives | `GenerateAlert`, `UpdateInventory`, `UpdateMetrics` |
| `SpikeOccurred` | Spike starts | `GenerateAlert`, `ApplySpikeEffect` |
| `SpikeEnded` | Spike ends | `RollbackSpikeEffect` |

**Strengths**:
- Well-structured event architecture using Laravel's event system
- Existing DAG-style listener chains for complex workflows
- Events fired at all key game moments

**Gaps**:
- No listener that aggregates events by day
- No event listener specifically for creating daily reports

### Data Models (Partial Support) ⚠️

| Model | Relevant Fields | Support for Daily Reports |
|-------|----------------|-------------------------|
| `GameState` | `day`, `cash`, `xp` | ✅ Tracks current day |
| `Alert` | `data` (JSON), `spike_event_id`, `created_at` | ⚠️ Has `created_at` but no `day` column |
| `SpikeEvent` | `starts_at_day`, `ends_at_day`, `is_active`, `meta` | ✅ Has day tracking |
| `Order` | `delivery_day`, `created_at` | ⚠️ Has `delivery_day` but no `created_day` |
| `Transfer` | `delivery_day`, `created_at` | ⚠️ Has `delivery_day` but no `created_day` |
| `Inventory` | `quantity`, `last_restocked_at` | ❌ No snapshot mechanism |

**Missing Tables**:
- `daily_reports` or `daily_summaries` - aggregated day data
- `activity_logs` or `event_logs` - all events by day
- `inventory_snapshots` - inventory levels per day

### Database Schema Analysis

#### Existing Tables

**Alerts Table**
```php
$table->uuid('id')->primary();
$table->string('type');
$table->string('severity')->default('info');
$table->foreignUuid('location_id')->nullable();
$table->foreignUuid('product_id')->nullable();
$table->text('message');
$table->json('data')->nullable();              // ✅ Flexible metadata
$table->foreignUuid('spike_event_id')->nullable(); // ✅ Links to spikes
$table->boolean('is_read')->default(false);
$table->boolean('is_resolved')->default(false); // ✅ Resolution tracking
$table->foreignId('user_id')->nullable();
$table->timestamps();
```

**Spike Events Table**
```php
$table->uuid('id')->primary();
$table->string('type');
$table->decimal('magnitude', 8, 2);
$table->integer('duration');
$table->foreignUuid('location_id')->nullable();
$table->foreignUuid('product_id')->nullable();
$table->integer('starts_at_day');    // ✅ Day tracking
$table->integer('ends_at_day');      // ✅ Day tracking
$table->boolean('is_active')->default(true);
$table->foreignUuid('parent_id')->nullable(); // ✅ DAG support
$table->foreignId('affected_route_id')->nullable();
$table->json('meta')->nullable();     // ✅ Flexible metadata
$table->foreignId('user_id')->nullable();
$table->timestamps();
```

**Orders Table**
```php
$table->uuid('id')->primary();
$table->foreignId('user_id')->nullable();
$table->foreignUuid('vendor_id');
$table->foreignUuid('location_id')->nullable();
$table->foreignId('route_id')->nullable();
$table->string('status');            // State machine managed
$table->integer('total_cost');
$table->timestamp('delivery_date')->nullable();
$table->integer('delivery_day')->nullable(); // ✅ Delivery day tracking
$table->timestamps();
```

**Transfers Table**
```php
$table->uuid('id')->primary();
$table->foreignId('user_id')->nullable();
$table->foreignUuid('source_location_id');
$table->foreignUuid('target_location_id');
$table->foreignUuid('product_id');
$table->integer('quantity');
$table->string('status');            // State machine managed
$table->integer('delivery_day')->nullable(); // ✅ Delivery day tracking
$table->timestamps();
```

**Inventory Table**
```php
$table->uuid('id')->primary();
$table->foreignId('user_id')->nullable();
$table->foreignUuid('location_id');
$table->foreignUuid('product_id');
$table->integer('quantity')->default(0);
$table->timestamp('last_restocked_at')->nullable();
$table->timestamps();
```

### Missing Infrastructure

#### No Daily Aggregation Tables

```php
// Missing: daily_reports
Schema::create('daily_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('day');
    $table->json('summary_data');  // Orders placed, transfers completed, spikes started/ended
    $table->json('metrics');      // Cash change, XP earned, inventory value
    $table->timestamps();
    $table->unique(['user_id', 'day']);
});

// Missing: inventory_snapshots
Schema::create('inventory_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('inventory_id')->constrained()->cascadeOnDelete();
    $table->integer('day');
    $table->integer('quantity');     // Snapshot of quantity at that day
    $table->timestamps();
    $table->unique(['user_id', 'inventory_id', 'day']);
});

// Missing: activity_logs
Schema::create('activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('day');
    $table->string('event_type');   // order_placed, spike_ended, transfer_completed, etc.
    $table->morphs('entity');      // Polymorphic: could be Order, SpikeEvent, Transfer
    $table->json('data')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'day']);
});
```

#### No Day-Scoped Indexes

Current queries would need to filter by `DATE(created_at)` which is inefficient:

```php
// Current approach (inefficient):
$alerts = Alert::where('user_id', $userId)
    ->whereDate('created_at', $targetDate)
    ->get();

// Better approach with day column:
$alerts = Alert::where('user_id', $userId)
    ->where('created_day', $targetDay)
    ->get(); // Uses index on created_day
```

---

## Implementation Roadmap

### Phase 1: Database Foundations

**Priority: High**

1. Add `created_day` to existing tables
2. Create `daily_reports` table
3. Create `inventory_snapshots` table
4. Create `activity_logs` table

### Phase 2: Backend Logic

**Priority: High**

1. Create `CreateDailyReport` listener
2. Create `CaptureInventorySnapshot` listener
3. Create `DailyReportService` for aggregation
4. Add controller methods for querying daily data

```php
// New listener in app/Listeners/CreateDailyReport.php
class CreateDailyReport
{
    public function handle(TimeAdvanced $event): void
    {
        $user = $event->gameState->user;
        $day = $event->day;

        $summary = [
            'orders_placed' => Order::where('user_id', $user->id)
                ->where('created_day', $day)->count(),
            'transfers_completed' => Transfer::where('user_id', $user->id)
                ->where('delivery_day', $day)->count(),
            'spikes_started' => SpikeEvent::where('user_id', $user->id)
                ->where('starts_at_day', $day)->count(),
            'spikes_ended' => SpikeEvent::where('user_id', $user->id)
                ->where('ends_at_day', $day)->count(),
            'alerts_generated' => Alert::where('user_id', $user->id)
                ->where('created_day', $day)->count(),
        ];

        DailyReport::create([
            'user_id' => $user->id,
            'day' => $day,
            'summary_data' => $summary,
            'metrics' => [
                'cash' => $event->gameState->cash,
                'xp' => $event->gameState->xp,
            ],
        ]);
    }
}
```

### Phase 3: Frontend Components

**Priority: Medium**

1. Create `DailyReportCard` component
2. Create `TransitionEventsLog` component
3. Add daily recap section to Dashboard
4. Create dedicated Daily History page

```tsx
// resources/js/components/DailyReportCard.tsx
interface DailyReportProps {
  day: number;
  ordersPlaced: number;
  transfersCompleted: number;
  spikesStarted: number;
  spikesEnded: number;
  cashChange: number;
  xpGained: number;
}
```

### Phase 4: Query Optimization

**Priority: Low**

1. Add composite indexes for day-based queries
2. Implement caching for daily report queries
3. Add pagination for long activity logs

---

## Support Matrix

| Feature | Current Support | Required Changes |
|----------|----------------|------------------|
| Event Tracking | **75%** | Add day columns, create activity logs |
| Data Storage | **40%** | Create snapshot and report tables |
| Aggregation Service | **0%** | Build daily report service |
| Historical Queries | **20%** | Add indexes, create query methods |
| Frontend Display | **0%** | Build dashboard components |

---

## Conclusion

The Moonshine Coffee Management Sim has an **excellent foundation** for daily reporting:

✅ **Event system is robust** - All key game actions fire well-structured events
✅ **Domain models are clean** - Models have good relationships and casts
✅ **State machine transitions work** - Orders and transfers have clear lifecycles

However, the **daily reporting functionality is missing**:

❌ No daily aggregation logic
❌ No inventory snapshot mechanism
❌ No day-scoped event tracking
❌ No frontend components for daily recaps

The architecture is **ready to support** daily reporting with moderate development effort. The existing event system means we can hook into `TimeAdvanced` and capture all day-transition events without major refactoring.

---

## Related Files

- `app/Services/SimulationService.php` - Day transition logic
- `app/Events/TimeAdvanced.php` - Day advanced event
- `app/Listeners/` - Existing event listeners
- `app/Models/` - Current data models
- `database/migrations/` - Database schema
- `resources/js/components/Dashboard.tsx` - Current dashboard
- `resources/js/pages/game/dashboard.tsx` - Inertia dashboard page
