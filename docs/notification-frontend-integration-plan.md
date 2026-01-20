# Notification Frontend Integration Fix

**Created**: 2026-01-19  
**Completed**: —  
**Status**: 🔴 Not Started  
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

### Phase 1: Update Layout Component 🔴

#### Task 1.1: Replace useApp with useOptionalGame 🔴
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

#### Task 1.2: Add Navigation Helper Function 🔴
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

#### Task 1.3: Add Click Handler 🔴
**File**: `resources/js/components/Layout.tsx`

```tsx
const handleNotificationClick = (alert: AlertModel) => {
  markAlertRead(alert.id);
  setIsNotifOpen(false);
  router.visit(getAlertDestination(alert));
};
```

---

#### Task 1.4: Update Notification Count Logic 🔴
**File**: `resources/js/components/Layout.tsx`

```tsx
// BEFORE
const unreadCount = notifications.filter(n => !n.read).length;

// AFTER
const unreadCount = alerts.filter(a => !a.is_read).length;
```

---

#### Task 1.5: Update Notification Dropdown Rendering 🔴
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

#### Task 1.6: Update Empty State Check 🔴
**File**: `resources/js/components/Layout.tsx`

```tsx
// BEFORE
{notifications.length === 0 ? (

// AFTER
{alerts.length === 0 ? (
```

---

### Phase 2: Add Type Import 🔴

#### Task 2.1: Import AlertModel Type 🔴
**File**: `resources/js/components/Layout.tsx`

```tsx
import { AlertModel } from '@/types';
```

---

## File Summary

| File | Action | Status |
| :--- | :--- | :--- |
| `resources/js/components/Layout.tsx` | Modify | 🔴 |

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

1. **Unauthenticated Users**: `useOptionalGame()` returns null gracefully 🔴
2. **No Alerts**: Already handled with "No signals received" message ✅
3. **Unknown Alert Type**: Falls back to `/game/dashboard` 🔴
4. **Missing location_id**: Isolation alerts without location go to dashboard without filter 🔴

---

## Rollback Plan

1. Revert `Layout.tsx` to use `useApp()` from git history
2. Restore original notification mapping code

---

## Success Criteria

- [ ] Notification bell shows count of unread alerts from database
- [ ] Clicking notification dropdown shows real alert messages
- [ ] Clicking an alert marks it as read AND navigates to relevant page
- [ ] `order_placed` → Orders page
- [ ] `transfer_completed` → Transfers page
- [ ] `spike_occurred` → War Room
- [ ] `isolation` → Dashboard
- [ ] Severity colors render correctly (rose/amber/blue)
- [ ] No console errors when game context is unavailable

---

## Implementation Walkthrough

*To be completed after implementation.*
