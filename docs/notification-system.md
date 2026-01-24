# Notification System

## Executive Summary

The Moonshine Coffee Management Sim implements a **custom Alert-based notification system** (not Laravel's built-in notifications) designed specifically for game events. The system provides real-time feedback to players about critical game events including orders, transfers, chaos events, and location isolation.

**Key Characteristics:**
- **Event-Driven**: Alerts are generated automatically by Laravel event listeners
- **Custom Implementation**: Uses `Alert` model instead of Laravel Notifications
- **HUD-Style UI**: Military/tactical aesthetic with "Comms Log" dropdown panel
- **Smart Navigation**: Clicking alerts navigates to relevant game pages
- **No Real-Time Updates**: Alerts load on page transitions (no WebSocket/Pusher)

---

## System Architecture

### Component Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Game Events Layer                         │
│  • OrderPlaced  • TransferCompleted  • SpikeOccurred            │
│  • TimeAdvanced (triggers isolation checks)                      │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Event Listeners Layer                         │
│  • GenerateAlert (handles Order/Transfer/Spike events)           │
│  • GenerateIsolationAlerts (location reachability checks)        │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Database Layer                              │
│  Alert Model → alerts table                                      │
│  • type, severity, message, user_id, is_read                     │
│  • location_id, product_id, spike_event_id (references)          │
│  • data (JSON metadata)                                          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                  Inertia Middleware Layer                        │
│  HandleInertiaRequests::share()                                  │
│  • Loads unread alerts on every request                          │
│  • Shares as props.game.alerts                                   │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    React Context Layer                           │
│  GameProvider (contexts/game-context.tsx)                        │
│  • Exposes alerts array via useGame() hook                       │
│  • Provides markAlertRead() function                             │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      UI Components Layer                         │
│  Layout.tsx (components/Layout.tsx:277-326)                      │
│  • Bell icon with animated unread badge                          │
│  • "Comms Log" dropdown panel                                    │
│  • Severity indicators (critical/warning/info)                   │
│  • Click-to-navigate functionality                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## Backend Implementation

### 1. Database Schema

**Migration**: `database/migrations/2026_01_16_055243_create_alerts_table.php`

```php
Schema::create('alerts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained();
    $table->foreignUuid('spike_event_id')->nullable()->constrained();
    $table->string('type');         // 'order_placed', 'spike_occurred', 'isolation', etc.
    $table->string('severity')->default('info');  // 'info', 'warning', 'critical'
    $table->foreignUuid('location_id')->nullable()->constrained();
    $table->foreignUuid('product_id')->nullable()->constrained();
    $table->text('message');        // User-facing message
    $table->json('data')->nullable(); // Additional metadata
    $table->boolean('is_read')->default(false);
    $table->integer('created_day'); // Game day when alert was created
    $table->timestamps();
});
```

**Indexes**:
```sql
CREATE INDEX idx_alerts_user_read ON alerts(user_id, is_read);
CREATE INDEX idx_alerts_severity ON alerts(severity) WHERE is_read = false;
```

### 2. Alert Model

**File**: `app/Models/Alert.php`

**Key Features**:
- Uses UUIDs for primary keys
- JSON casting for `data` field
- Boolean casting for `is_read`
- Relationships: `user()`, `location()`, `product()`, `spikeEvent()`

**Relationships**:
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function spikeEvent(): BelongsTo
{
    return $this->belongsTo(SpikeEvent::class);
}

public function location(): BelongsTo
{
    return $this->belongsTo(Location::class);
}

public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
```

**Documented In**: [`docs/backend/02-models-database.md:608-650`](./backend/02-models-database.md)

### 3. Alert Generation (Event Listeners)

#### GenerateAlert Listener

**File**: `app/Listeners/GenerateAlert.php`

**Registered In**: `app/Providers/GameServiceProvider.php:108-125`

```php
Event::listen(OrderPlaced::class, GenerateAlert::class);
Event::listen(TransferCompleted::class, GenerateAlert::class);
Event::listen(SpikeOccurred::class, GenerateAlert::class);
```

**Alert Types Generated**:

| Event | Alert Type | Severity | Message Template |
|-------|-----------|----------|------------------|
| `OrderPlaced` | `order_placed` | `info` | "Order #{id} placed for ${cost} cash." |
| `TransferCompleted` | `transfer_completed` | `info` | "Transfer #{id} completed successfully." |
| `SpikeOccurred` | `spike_occurred` | `warning`/`critical` | "A chaos event occurred: {type}!" |

**Implementation Example**:
```php
// app/Listeners/GenerateAlert.php

public function handle(OrderPlaced|TransferCompleted|SpikeOccurred $event): void
{
    match (true) {
        $event instanceof OrderPlaced => $this->handleOrderPlaced($event),
        $event instanceof TransferCompleted => $this->handleTransferCompleted($event),
        $event instanceof SpikeOccurred => $this->handleSpikeOccurred($event),
    };
}

private function handleOrderPlaced(OrderPlaced $event): void
{
    Alert::create([
        'user_id' => auth()->id(),
        'type' => 'order_placed',
        'severity' => 'info',
        'message' => "Order #{$event->order->id} placed for \${$event->order->total_cost} cash.",
        'data' => [
            'order_id' => $event->order->id,
            'total_cost' => $event->order->total_cost,
        ],
        'created_day' => GameState::where('user_id', auth()->id())->value('day'),
    ]);
}
```

#### GenerateIsolationAlerts Action

**File**: `app/Actions/GenerateIsolationAlerts.php`

**Triggered By**: `TimeAdvanced` event (via listener)

**Purpose**: Detects when locations become unreachable from the supply chain due to route blockages (typically from `BlizzardSpike` events).

**Algorithm**:
1. For each store location, run **Reverse BFS (Breadth-First Search)** from the store
2. Check if any warehouse or vendor is reachable via active routes
3. If unreachable, create a **critical severity** isolation alert
4. Link alert to the spike event that caused the isolation

**Implementation**:
```php
// app/Actions/GenerateIsolationAlerts.php:53-63

foreach ($isolatedStores as $store) {
    Alert::create([
        'user_id' => $userId,
        'type' => 'isolation',
        'severity' => 'critical',
        'location_id' => $store->id,
        'spike_event_id' => $spike->id,
        'message' => "Store '{$store->name}' is isolated from supply chain!",
        'data' => [
            'reason' => "Likely due to {$spike->type}",
            'blocked_routes' => $blockedRouteIds,
        ],
        'created_day' => $gameState->day,
    ]);
}
```

**Reference**: See [`docs/architecture/hybrid-event-topology.md:45-52`](./architecture/hybrid-event-topology.md) for BFS reachability algorithm details.

### 4. Controller Endpoints

**File**: `app/Http/Controllers/GameController.php`

#### Mark Alert as Read

```php
public function markAlertRead(Alert $alert): RedirectResponse
{
    $alert->update(['is_read' => true]);
    return back();
}
```

**Route**: `POST /game/alerts/{alert}/read` → `game.alerts.read`

**Documented In**: [`docs/backend/03-controllers-routes.md:166-176`](./backend/03-controllers-routes.md)

### 5. Inertia Middleware Integration

**File**: `app/Http/Middleware/HandleInertiaRequests.php:89-92`

**Purpose**: Share unread alerts with every Inertia response.

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // ... other shared data
        'alerts' => Alert::where('is_read', false)
            ->latest()
            ->take(10)
            ->get(),
    ]);
}
```

⚠️ **Potential Bug**: The query is missing `where('user_id', auth()->id())`, which could leak alerts from other users.

**Recommended Fix**:
```php
'alerts' => Alert::where('user_id', auth()->id())
    ->where('is_read', false)
    ->latest()
    ->take(10)
    ->get(),
```

---

## Frontend Implementation

### 1. React Context

**File**: `resources/js/contexts/game-context.tsx`

**Provides**:
- `alerts` array from Inertia props
- `markAlertRead(alertId)` function to mark alerts as read

**Implementation**:
```tsx
// resources/js/contexts/game-context.tsx:65-74

const markAlertRead = (alertId: string) => {
    router.post(
        `/game/alerts/${alertId}/read`,
        {},
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};
```

**Usage**:
```tsx
import { useGame } from '@/contexts/game-context';

function MyComponent() {
    const { alerts, markAlertRead } = useGame();

    return (
        <div>
            {alerts.map(alert => (
                <AlertItem
                    key={alert.id}
                    alert={alert}
                    onRead={() => markAlertRead(alert.id)}
                />
            ))}
        </div>
    );
}
```

### 2. Notification UI Component

**File**: `resources/js/components/Layout.tsx:277-326`

**Features**:
- 🔔 Bell icon button with Lucide React icon
- 🔴 Animated red dot badge for unread count
- 📋 Dropdown "Comms Log" panel with military HUD aesthetic
- 🎨 Severity-based color coding (critical=red, warning=amber, info=blue)
- 🖱️ Click-to-navigate functionality
- ⏰ Relative timestamps ("2d ago")

**Component Structure**:

```tsx
// Unread count calculation
const unreadCount = alerts.filter(a => !a.is_read).length;

// Bell button with badge
<button onClick={() => setIsNotifOpen(!isNotifOpen)}>
    <Bell size={20} />
    {unreadCount > 0 && (
        <span className="absolute top-1 right-1 w-2.5 h-2.5 bg-rose-500
              border-2 border-black rounded-full animate-bounce" />
    )}
</button>

// Dropdown panel
{isNotifOpen && (
    <div className="notification-dropdown">
        <h3>COMMS LOG</h3>
        <div className="divider" />

        {/* Unread alerts first */}
        {alerts.filter(a => !a.is_read).map(alert => (
            <AlertItem alert={alert} />
        ))}

        {/* Read alerts (dimmed) */}
        {alerts.filter(a => a.is_read).map(alert => (
            <AlertItem alert={alert} className="opacity-50" />
        ))}
    </div>
)}
```

**Severity Indicators**:
```tsx
const severityColor = {
    critical: 'bg-rose-500',
    warning: 'bg-amber-500',
    info: 'bg-sky-500',
}[alert.severity];

<div className={`w-2 h-2 rounded-full ${severityColor}`} />
```

### 3. Smart Navigation on Click

**File**: `resources/js/components/Layout.tsx:72-94`

**Logic**: When user clicks an alert, it:
1. Marks the alert as read (via `markAlertRead()`)
2. Navigates to the most relevant page based on alert type

**Navigation Mapping**:

| Alert Type | Destination Route | Purpose |
|------------|------------------|---------|
| `order_placed` | `/game/ordering` | View order details |
| `transfer_completed` | `/game/transfers` | View transfer history |
| `spike_occurred` | `/game/war-room` | View spike management |
| `isolation` | `/game/dashboard?location={id}` | View isolated location |
| *(default)* | `/game/dashboard` | General dashboard |

**Implementation**:
```tsx
const handleNotificationClick = (alert: AlertModel) => {
    markAlertRead(alert.id);

    const routeMap = {
        order_placed: '/game/ordering',
        transfer_completed: '/game/transfers',
        spike_occurred: '/game/war-room',
        isolation: `/game/dashboard?location=${alert.location_id}`,
    };

    const destination = routeMap[alert.type] || '/game/dashboard';
    router.visit(destination);
    setIsNotifOpen(false);
};
```

### 4. TypeScript Types

**File**: `resources/js/types/index.d.ts`

```typescript
export interface AlertModel {
    id: string;
    type: 'order_placed' | 'transfer_completed' | 'spike_occurred' | 'isolation';
    severity: 'info' | 'warning' | 'critical';
    message: string;
    location_id?: string;
    product_id?: string;
    spike_event_id?: string;
    data?: Record<string, any>;
    is_read: boolean;
    created_at: string;
    updated_at: string;
}
```

---

## Additional: Flash Message System

**Separate from Alerts**, the application includes a **temporary toast notification system** for success/error messages.

### Flash Toast Component

**File**: `resources/js/components/ui/flash-toast.tsx`

**Features**:
- Auto-dismisses after 5 seconds
- Color-coded by message type (success=green, error=red, warning=amber, info=blue)
- Icon for each message type (CheckCircle, XCircle, AlertTriangle, Info)
- Slide-in animation from top-right
- Manual dismiss button

**Usage from Laravel**:
```php
// Success message
return redirect()->route('game.index')
    ->with('success', 'Order placed successfully!');

// Error message
return back()
    ->with('error', 'Insufficient funds to place order.');

// Warning message
return back()
    ->with('warning', 'Some items are out of stock.');

// Info message
return back()
    ->with('info', 'Your order is being processed.');
```

**Inertia Integration**:
```tsx
// Flash messages are shared via HandleInertiaRequests middleware
'flash' => [
    'success' => fn () => $request->session()->get('success'),
    'error' => fn () => $request->session()->get('error'),
    'warning' => fn () => $request->session()->get('warning'),
    'info' => fn () => $request->session()->get('info'),
]
```

**Toast Display Logic**:
```tsx
import { usePage } from '@inertiajs/react';

function FlashToast() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (flash.success || flash.error || flash.warning || flash.info) {
            setVisible(true);
            const timer = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Render toast UI
}
```

---

## Data Flow Diagram

### Alert Generation and Display Flow

```
┌─────────────────────────────────────────────────────────────┐
│ Step 1: Game Event Occurs                                   │
│ • User places order → OrderPlaced event                     │
│ • Transfer completes → TransferCompleted event              │
│ • Time advances + spike active → SpikeOccurred event        │
│ • Time advances + location isolated → TimeAdvanced event    │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 2: Event Listener Triggered                            │
│ • GenerateAlert listener handles Order/Transfer/Spike       │
│ • GenerateIsolationAlerts action runs BFS reachability      │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 3: Alert Record Created                                │
│ Alert::create([                                             │
│   'user_id' => auth()->id(),                                │
│   'type' => 'order_placed',                                 │
│   'severity' => 'info',                                     │
│   'message' => 'Order #123 placed for $500 cash.',          │
│   'is_read' => false,                                       │
│   'data' => ['order_id' => 123, 'total_cost' => 500],       │
│ ])                                                           │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 4: Stored in Database                                  │
│ • alerts table updated                                      │
│ • Indexed by user_id + is_read for fast queries             │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 5: Next Page Request                                   │
│ • User navigates or Inertia reloads                         │
│ • HandleInertiaRequests middleware executes                 │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 6: Alerts Loaded and Shared                            │
│ • Middleware queries: Alert::where('is_read', false)        │
│                             ->latest()->take(10)->get()     │
│ • Shared as Inertia props: props.game.alerts                │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 7: React Context Consumes Props                        │
│ • GameProvider receives alerts from usePage().props         │
│ • useGame() hook exposes alerts to components               │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 8: UI Renders Notifications                            │
│ • Layout.tsx displays bell icon with unread badge           │
│ • Dropdown shows alerts sorted by read status               │
│ • Severity-based color indicators                           │
│ • Relative timestamps displayed                             │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 9: User Interaction                                    │
│ • User clicks alert                                         │
│ • handleNotificationClick() executes                        │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 10: Mark as Read + Navigate                            │
│ • POST /game/alerts/{id}/read                               │
│ • GameController::markAlertRead() updates is_read = true    │
│ • Inertia navigates to relevant page based on alert type    │
└─────────────────────────────────────────────────────────────┘
```

---

## Relationship to Game Architecture

### Integration with Hybrid Event-Topology System

The notification system is tightly coupled with the **Causal Graph (Event Layer)** described in [`docs/architecture/hybrid-event-topology.md`](./architecture/hybrid-event-topology.md).

**From the Hybrid Event-Topology documentation**:

> ### 3.1 Graph Theory Definition
> - **Type:** Directed Acyclic Graph (DAG)
> - **Nodes:** `SpikeEvents` (Root Causes), **`Alerts` (Symptoms)**, `Tasks` (Player Actions)
> - **Edges:** "Caused By" / "Requires" dependencies

**Alert as DAG Nodes**:
- **Root Node (Cause)**: `SpikeEvent` (e.g., Blizzard blocks routes)
- **Child Node (Symptom)**: `Alert` with `type='isolation'` linked to the spike
- **Leaf Node (Action)**: Player creates a `Transfer` to resolve the isolation

**Propagation Flow Example**:
1. **Root Node**: `SpikeEventFactory` generates a Blizzard spike
   - Effect: Sets `routes.is_active = false` for affected routes
2. **Child Node**: `GenerateIsolationAlerts` runs reachability check
   - Creation: Spawns `Alert` with `spike_event_id` pointing to Blizzard
   - Message: "Uptown Kiosk is isolated from supply chain!"
3. **Leaf Node**: Player sees alert, clicks it, navigates to War Room
   - Goal: Find alternate route via Dijkstra pathfinding
   - Resolution: Execute emergency transfer or activate backup route

**Key Insight**: Alerts serve as the **symptom layer** that translates root cause events (spikes) into actionable player notifications.

---

## Limitations and Future Enhancements

### Current Limitations

1. **No Real-Time Updates**
   - Alerts only appear after page reload/navigation
   - No WebSocket, Pusher, or Laravel Echo integration
   - Players may miss critical alerts until next action

2. **No User Preferences**
   - Cannot mute specific alert types
   - No email notifications
   - No "Do Not Disturb" mode
   - All users receive all alerts for their events

3. **Limited Alert History**
   - Only last 10 unread alerts shown in dropdown
   - No dedicated "All Alerts" page
   - Read alerts are visually de-emphasized but not archived

4. **Missing User Filtering in Middleware**
   - Potential bug: `HandleInertiaRequests` doesn't filter by `user_id`
   - Could leak alerts between users (though auth middleware should prevent)

5. **No Alert Aggregation**
   - Multiple similar alerts (e.g., 5 low stock alerts) shown individually
   - No grouping or summarization

### Potential Enhancements

#### Phase 1: User Experience
- [ ] Add dedicated "Notifications" page with full alert history
- [ ] Implement alert search and filtering (by type, severity, date)
- [ ] Add "Mark All as Read" button
- [ ] Show alert preview on hover

#### Phase 2: Real-Time Updates
- [ ] Integrate Laravel Echo + Pusher/Soketi
- [ ] Broadcast new alerts via WebSocket channels
- [ ] Show toast notification for critical alerts immediately
- [ ] Add sound effects for critical alerts (optional user setting)

#### Phase 3: User Preferences
- [ ] Alert preferences UI in Settings page
- [ ] Per-alert-type enable/disable toggles
- [ ] Email notification opt-in
- [ ] Severity threshold settings (e.g., only show warning+)

#### Phase 4: Advanced Features
- [ ] Alert aggregation ("You have 3 low stock alerts")
- [ ] Smart alert prioritization (ML-based)
- [ ] Custom alert rules (e.g., "Alert me when cash < $100")
- [ ] Alert snoozing functionality
- [ ] Export alert history to CSV

---

## Key Files Reference

### Backend

| Category | File Path | Lines | Description |
|----------|-----------|-------|-------------|
| **Migration** | `database/migrations/2026_01_16_055243_create_alerts_table.php` | - | Creates alerts table |
| **Model** | `app/Models/Alert.php` | - | Alert Eloquent model |
| **Listener** | `app/Listeners/GenerateAlert.php` | - | Handles Order/Transfer/Spike events |
| **Action** | `app/Actions/GenerateIsolationAlerts.php` | 53-63 | Generates isolation alerts via BFS |
| **Middleware** | `app/Http/Middleware/HandleInertiaRequests.php` | 89-92 | Shares alerts with Inertia |
| **Controller** | `app/Http/Controllers/GameController.php` | 380-385 | `markAlertRead()` endpoint |
| **Event Provider** | `app/Providers/GameServiceProvider.php` | 108-125 | Registers alert event listeners |

### Frontend

| Category | File Path | Lines | Description |
|----------|-----------|-------|-------------|
| **Context** | `resources/js/contexts/game-context.tsx` | 65-74 | `markAlertRead()` function |
| **UI Component** | `resources/js/components/Layout.tsx` | 277-326 | Notification bell and dropdown |
| **Navigation Logic** | `resources/js/components/Layout.tsx` | 72-94 | `handleNotificationClick()` |
| **Types** | `resources/js/types/index.d.ts` | - | `AlertModel` TypeScript interface |
| **Flash Toast** | `resources/js/components/ui/flash-toast.tsx` | - | Temporary toast notifications |

### Events

| Event Class | File Path | Triggers Alert |
|-------------|-----------|----------------|
| `OrderPlaced` | `app/Events/OrderPlaced.php` | ✅ Yes (info) |
| `TransferCompleted` | `app/Events/TransferCompleted.php` | ✅ Yes (info) |
| `SpikeOccurred` | `app/Events/SpikeOccurred.php` | ✅ Yes (warning/critical) |
| `TimeAdvanced` | `app/Events/TimeAdvanced.php` | ✅ Yes (via isolation check) |

---

## Testing Checklist

### Backend Tests

- [ ] Alert model creates records correctly
- [ ] Event listeners generate appropriate alerts
- [ ] `GenerateIsolationAlerts` detects isolated locations via BFS
- [ ] `markAlertRead()` endpoint updates `is_read` flag
- [ ] Middleware filters alerts by user (after bug fix)
- [ ] Alert relationships (user, location, product, spike) work correctly

### Frontend Tests

- [ ] Bell icon displays correct unread count
- [ ] Dropdown renders alerts with correct severity colors
- [ ] Clicking alert marks it as read
- [ ] Navigation routing works for all alert types
- [ ] Read alerts display with reduced opacity
- [ ] Timestamps format correctly (relative time)
- [ ] Flash toasts auto-dismiss after 5 seconds

### Integration Tests

- [ ] Placing order generates `order_placed` alert
- [ ] Completing transfer generates `transfer_completed` alert
- [ ] Spike occurrence generates `spike_occurred` alert
- [ ] Route blockage triggers isolation alert
- [ ] Alerts appear in UI after event occurs
- [ ] Multiple users see only their own alerts (after bug fix)

---

## Comparison with Existing Documentation

### ✅ Well-Documented Areas

1. **Alert Model & Schema** → [`docs/backend/02-models-database.md:608-650`](./backend/02-models-database.md)
   - Comprehensive column descriptions
   - Relationships documented
   - Casts and scopes included

2. **Event Architecture** → [`docs/backend/01-architecture.md:52-66`](./backend/01-architecture.md)
   - Event-driven design explained
   - Listener pattern documented
   - `GenerateAlert` mentioned in directory structure

3. **Causal Graph Theory** → [`docs/architecture/hybrid-event-topology.md:60-93`](./architecture/hybrid-event-topology.md)
   - Alerts positioned as "Symptom" nodes in DAG
   - Propagation flow described conceptually
   - Isolation alert logic mentioned

### ⚠️ Partially Documented Areas

1. **Controller Endpoints** → [`docs/backend/03-controllers-routes.md:166-176`](./backend/03-controllers-routes.md)
   - Routes mentioned but not detailed
   - Missing implementation examples

2. **Inertia Integration**
   - Middleware sharing mentioned in architecture docs
   - Not specific to alerts

### ❌ Previously Undocumented Areas

1. **React Notification UI**
   - Layout.tsx component not documented in `docs/frontend/`
   - Bell icon and dropdown UI not described
   - Navigation-on-click feature not mentioned

2. **Flash Toast System**
   - Completely missing from documentation
   - Separate from alert system but important for UX

3. **Complete Data Flow**
   - No end-to-end flow diagram in existing docs
   - Integration between layers not fully explained

4. **Known Bugs and Limitations**
   - Missing `user_id` filter in middleware not documented
   - No real-time updates limitation not stated
   - No user preferences limitation not mentioned

---

## Recommendations for Existing Documentation

### 1. Update `docs/frontend/README.md`

Add a section documenting the notification UI:

```markdown
### Notification System UI

The application displays alerts via a notification bell icon in the Layout component:

- **Location**: `resources/js/components/Layout.tsx:277-326`
- **Features**: Unread badge, dropdown panel, severity indicators, click-to-navigate
- **Styling**: Military HUD aesthetic ("Comms Log")
- **Context**: Uses `useGame()` hook from `GameProvider`

See [Notification System Documentation](../notification-system.md) for complete details.
```

### 2. Update `docs/backend/01-architecture.md`

Expand the Events & Listeners section to include alert generation details:

```markdown
### Alert Generation Workflow

Alerts are automatically generated by event listeners:

- `GenerateAlert` listener handles `OrderPlaced`, `TransferCompleted`, `SpikeOccurred`
- `GenerateIsolationAlerts` action runs BFS reachability checks on `TimeAdvanced`
- Alerts are stored in the `alerts` table with type, severity, and metadata
- Shared with frontend via `HandleInertiaRequests` middleware

See [Notification System Documentation](../notification-system.md) for complete flow.
```

### 3. Fix Known Bug in `HandleInertiaRequests.php`

Update the middleware to filter alerts by user:

```php
'alerts' => Alert::where('user_id', auth()->id())
    ->where('is_read', false)
    ->latest()
    ->take(10)
    ->get(),
```

### 4. Create Frontend Alert Component Documentation

Add `docs/frontend/08-notifications.md` with details on:
- Layout.tsx notification panel implementation
- Flash toast component usage
- TypeScript types for alerts
- Best practices for displaying alerts in custom components

---

## Conclusion

The Moonshine Coffee Management Sim implements a **comprehensive custom alert system** that transforms backend game events into actionable player notifications with intelligent UI navigation. While the system is well-architected and functionally complete, there are opportunities for enhancement including real-time updates, user preferences, and better alert management features.

The existing documentation provides strong coverage of the **database schema** and **conceptual architecture**, but this document fills critical gaps in **frontend implementation**, **complete data flow**, and **integration patterns**.

**Key Strengths**:
- ✅ Event-driven architecture ensures alerts are generated automatically
- ✅ Smart navigation guides players to relevant pages
- ✅ HUD-style UI provides excellent game immersion
- ✅ Tight integration with Causal Graph (Hybrid Event-Topology)

**Key Weaknesses**:
- ⚠️ No real-time notifications (page refresh required)
- ⚠️ Limited alert history (only 10 unread)
- ⚠️ Potential security issue (missing user filter)
- ⚠️ No user customization options

**Next Steps**:
1. Fix `user_id` filter bug in `HandleInertiaRequests.php`
2. Consider WebSocket integration for real-time alerts
3. Update frontend documentation to include notification UI
4. Add comprehensive alert management page
