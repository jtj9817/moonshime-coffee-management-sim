# Notification Frontend Integration Fix

**Created**: 2026-01-19  
**Completed**: 2026-01-19  
**Status**: ✅ Complete  
**Purpose**: Connect the Layout notification bell UI to the real backend Alert system with deep-link navigation.

---

## Problem Statement

The application has a complete backend Alert system but the frontend notification UI is disconnected from it.

1. **Legacy Hook Usage**: `Layout.tsx` uses `useApp()` which references an obsolete/non-functional notification system
2. **Type Mismatch**: The UI expects `AppNotification` interface but backend provides `AlertModel`
3. **Non-functional Actions**: `markNotificationRead()` from `useApp()` doesn't connect to the real API
4. **No Navigation**: Clicking a notification only marks it read — doesn't take user to relevant context

These issues result in the notification bell showing "No signals received" even when alerts exist in the database.

---

## Design Decisions

| Decision | Choice |
| :--- | :--- |
| Context to Use | `useGame()` from `game-context.tsx` |
| Alert Type | `AlertModel` from `@/types/index` |
| Mark Read API | `POST /game/alerts/{id}/read` via Inertia router |
| **Click Behavior** | **Mark read + Navigate to relevant page** |
| Severity Colors | `critical` = rose, `warning` = amber, `info` = blue |

### Navigation Mapping

| Alert Type | Destination |
| :--- | :--- |
| `order_placed` | `/game/orders` |
| `transfer_completed` | `/game/transfers` |
| `spike_occurred` | `/game/war-room` |
| `isolation` | `/game/dashboard` (with location filter if available) |
| *default* | `/game/dashboard` |

---

## Solution Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Backend (Laravel)                        │
├─────────────────────────────────────────────────────────────────┤
│  HandleInertiaRequests.php                                       │
│  └─ game.alerts = Alert::where('is_read', false)->take(10)      │
│                                                                  │
│  GameController::markAlertRead($alert)                           │
│  └─ $alert->update(['is_read' => true])                         │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼ Inertia Shared Props
┌─────────────────────────────────────────────────────────────────┐
│                      Frontend (React)                            │
├─────────────────────────────────────────────────────────────────┤
│  game-context.tsx                                                │
│  └─ useGame() → { alerts: AlertModel[], markAlertRead(id) }    │
│                                                                  │
│  Layout.tsx                                                      │
│  └─ useOptionalGame() → alerts, markAlertRead                   │
│  └─ handleNotificationClick(alert):                             │
│       1. markAlertRead(alert.id)                                │
│       2. setIsNotifOpen(false)                                  │
│       3. router.visit(getAlertDestination(alert))               │
│                                                                  │
│  getAlertDestination(alert) utility                              │
│  └─ Maps alert.type → route path                                │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Tasks

### Phase 1: Update Layout Component ✅

#### Task 1.1: Replace useApp with useOptionalGame ✅
**File**: `resources/js/components/Layout.tsx`

```tsx
// BEFORE
import { useApp } from '../App';
const { notifications, markNotificationRead, ... } = useApp();

// AFTER
import { useOptionalGame } from '@/contexts/game-context';
import { router } from '@inertiajs/react';

const game = useOptionalGame();
const alerts = game?.alerts ?? [];
const markAlertRead = game?.markAlertRead ?? (() => {});
```

---

#### Task 1.2: Add Navigation Helper Function ✅
**File**: `resources/js/components/Layout.tsx`

```tsx
// Add inside Layout component
const getAlertDestination = (alert: AlertModel): string => {
  switch (alert.type) {
    case 'order_placed':
      return '/game/orders';
    case 'transfer_completed':
      return '/game/transfers';
    case 'spike_occurred':
      return '/game/war-room';
    case 'isolation':
      return alert.location_id 
        ? `/game/dashboard?location=${alert.location_id}` 
        : '/game/dashboard';
    default:
      return '/game/dashboard';
  }
};
```

---

#### Task 1.3: Add Click Handler ✅
**File**: `resources/js/components/Layout.tsx`

```tsx
const handleNotificationClick = (alert: AlertModel) => {
  markAlertRead(alert.id);
  setIsNotifOpen(false);
  router.visit(getAlertDestination(alert));
};
```

---

#### Task 1.4: Update Notification Count Logic ✅
**File**: `resources/js/components/Layout.tsx`

```tsx
// BEFORE
const unreadCount = notifications.filter(n => !n.read).length;

// AFTER
const unreadCount = alerts.filter(a => !a.is_read).length;
```

---

#### Task 1.5: Update Notification Dropdown Rendering ✅
**File**: `resources/js/components/Layout.tsx`

```tsx
// BEFORE
notifications.map(n => (
  <div key={n.id} onClick={() => markNotificationRead(n.id)} ...>
    <div className={`... ${n.type === 'alert' ? 'bg-rose-500' : ...}`}>
    <p>{n.message}</p>
    <p>{new Date(n.timestamp).toLocaleTimeString()}</p>
  </div>
))

// AFTER
alerts.map(alert => (
  <div 
    key={alert.id} 
    onClick={() => handleNotificationClick(alert)}
    className={`p-4 border-b border-stone-800 hover:bg-stone-800/50 cursor-pointer transition-colors ${alert.is_read ? 'opacity-50' : 'bg-stone-800/30'}`}
  >
    <div className="flex gap-3">
      <div className={`w-1.5 h-1.5 mt-1.5 rounded-full flex-shrink-0 ${
        alert.severity === 'critical' 
          ? 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.6)]' 
          : alert.severity === 'warning' 
            ? 'bg-amber-500' 
            : 'bg-blue-500'
      }`}></div>
      <div>
        <p className="text-xs font-bold text-stone-200 leading-snug">{alert.message}</p>
        <p className="text-[10px] text-stone-500 mt-1 font-mono">{new Date(alert.created_at).toLocaleTimeString()}</p>
      </div>
    </div>
  </div>
))
```

---

#### Task 1.6: Update Empty State Check ✅
**File**: `resources/js/components/Layout.tsx`

```tsx
// BEFORE
{notifications.length === 0 ? (

// AFTER
{alerts.length === 0 ? (
```

---

### Phase 2: Add Type Import ✅

#### Task 2.1: Import AlertModel Type ✅
**File**: `resources/js/components/Layout.tsx`

```tsx
import { AlertModel } from '@/types';
```

---

## File Summary

| File | Action | Status |
| :--- | :--- | :--- |
| `resources/js/components/Layout.tsx` | Modify | ✅ |

---

## Execution Order

1. **Add imports** — `useOptionalGame`, `router`, `AlertModel`
2. **Replace hook** — Remove `useApp`, add `useOptionalGame` destructuring
3. **Add helper functions** — `getAlertDestination`, `handleNotificationClick`
4. **Update count** — Fix `unreadCount` to use `is_read`
5. **Update dropdown** — Fix mapping to use `AlertModel` properties with click handler
6. **Test** — Verify clicking notifications navigates correctly

---

## Edge Cases to Handle

1. **Unauthenticated Users**: `useOptionalGame()` returns null gracefully ✅
2. **No Alerts**: Already handled with "No signals received" message ✅
3. **Unknown Alert Type**: Falls back to `/game/dashboard` ✅
4. **Missing location_id**: Isolation alerts without location go to dashboard without filter ✅

---

## Rollback Plan

1. Revert `Layout.tsx` to use `useApp()` from git history
2. Restore original notification mapping code

---

## Success Criteria

- [x] Notification bell shows count of unread alerts from database
- [x] Clicking notification dropdown shows real alert messages
- [x] Clicking an alert marks it as read AND navigates to relevant page
- [x] `order_placed` → Orders page
- [x] `transfer_completed` → Transfers page
- [x] `spike_occurred` → War Room
- [x] `isolation` → Dashboard
- [x] Severity colors render correctly (rose/amber/blue)
- [x] No console errors when game context is unavailable

---

## Implementation Walkthrough

Completed on 2026-01-19.

### The Problem

The notification bell in the header (`Layout.tsx`) was using a legacy `useApp()` hook that wasn't connected to the real backend. The backend has a complete `Alert` system, but the frontend was showing "No signals received" even when alerts existed.

---

### Change 1: Updated Imports (Lines 1-13)

```tsx
// BEFORE
import { useApp } from '../App';

// AFTER
import { router } from '@inertiajs/react';
import { useOptionalGame } from '@/contexts/game-context';
import { AlertModel } from '@/types/index';
```

**Why**: 
- `router` — needed for programmatic navigation when clicking alerts
- `useOptionalGame` — the real game context that provides alerts from the backend
- `AlertModel` — TypeScript type for the alert structure

---

### Change 2: Context Hook Replacement (Lines 27-35)

```tsx
// BEFORE
const { notifications, markNotificationRead, ... } = useApp();

// AFTER  
const game = useOptionalGame();
const alerts = game?.alerts ?? [];
const markAlertRead = game?.markAlertRead ?? (() => {});
const gameState = game?.gameState ?? { cash: 0, xp: 0, day: 1, level: 1, reputation: 0, strikes: 0 };
```

**Why**: 
- `useOptionalGame()` returns `null` when not in a `GameProvider` (e.g., login pages), so we use null-safe fallbacks
- This prevents crashes on unauthenticated routes

---

### Change 3: Navigation Helper Function (Lines 58-73)

```tsx
const getAlertDestination = (alert: AlertModel): string => {
  switch (alert.type) {
    case 'order_placed':      return '/game/orders';
    case 'transfer_completed': return '/game/transfers';
    case 'spike_occurred':     return '/game/war-room';
    case 'isolation':          return alert.location_id 
                                 ? `/game/dashboard?location=${alert.location_id}` 
                                 : '/game/dashboard';
    default:                   return '/game/dashboard';
  }
};
```

**Why**: Maps each alert type to its relevant page so users get context immediately

---

### Change 4: Click Handler (Lines 75-80)

```tsx
const handleNotificationClick = (alert: AlertModel) => {
  markAlertRead(alert.id);       // 1. Mark as read in backend
  setIsNotifOpen(false);         // 2. Close dropdown
  router.visit(getAlertDestination(alert)); // 3. Navigate
};
```

**Why**: Single click does all three actions — no manual navigation needed

---

### Change 5: Updated Rendering (Lines 289-306)

```tsx
// BEFORE: Used AppNotification with n.type, n.read, n.timestamp
// AFTER:  Uses AlertModel with alert.severity, alert.is_read, alert.created_at

<div className={`... ${
  alert.severity === 'critical' ? 'bg-rose-500 shadow-[0_0_8px_...]' 
  : alert.severity === 'warning' ? 'bg-amber-500' 
  : 'bg-blue-500'
}`}>
```

**Why**: 
- Backend uses `is_read` (snake_case) not `read`
- Backend uses `severity` (`critical`/`warning`/`info`) not `type`
- Colors now match severity: 🔴 critical, 🟠 warning, 🔵 info

---

### Data Flow Summary

```
┌─ Backend ─────────────────────────────────────────────┐
│ Alert::create([type, severity, message, ...])         │
│        ↓                                              │
│ HandleInertiaRequests → game.alerts (shared props)    │
└───────────────────────────────────────────────────────┘
                         ↓
┌─ Frontend ────────────────────────────────────────────┐
│ useOptionalGame() → alerts                            │
│        ↓                                              │
│ Layout.tsx → Notification Bell                        │
│        ↓ (click)                                      │
│ handleNotificationClick()                             │
│   1. POST /game/alerts/{id}/read                      │
│   2. Close dropdown                                   │
│   3. router.visit('/game/orders')                     │
└───────────────────────────────────────────────────────┘
```

---

### Files Modified

| File | Changes |
| :--- | :--- |
| [Layout.tsx](file:///mnt/0B8533211952FCF2/moonshime-coffee-management-sim/resources/js/components/Layout.tsx) | Replaced `useApp` → `useOptionalGame`, added `getAlertDestination` + `handleNotificationClick`, updated notification rendering |

### Git Commit

```
756a49c feat(notifications): connect bell UI to backend alerts with deep-link navigation
```
