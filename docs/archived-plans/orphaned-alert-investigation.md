# Orphaned Alert Investigation: Missing Price Spike

**Date**: 2026-01-19  
**Status**: Resolved (data cleanup applied)

---

## Issue Summary

A notification for a "Chaos Event: price" appeared in the UI, but clicking it navigated to an empty War Room page. The spike event referenced by the alert did not exist in the database.

---

## Context

### Initial Symptoms
1. Notification bell showed: *"A chaos event occurred: price!"*
2. Clicking notification navigated to `/game/spike-history` (after route fix)
3. War Room displayed only 4 resolved events (demand, breakdown, blizzard, delay)
4. No price spike visible in the table

### Environment
- Alert ID: `019bd909-781f-703c-b3fc-1183744607e1`
- Expected Spike ID: `019bd909-7818-735d-97ef-a6efcdf1becf`
- User ID: 14

---

## Investigation

### Step 1: Verify Alert Exists
```php
Alert::where('is_read', false)->get();
// Result: 1 alert with type: 'spike_occurred', message: 'A chaos event occurred: price!'
```

### Step 2: Check Alert's Data Field
The alert's `data` column contained a **serialized SpikeEvent object**:
```json
{
  "id": "019bd909-7818-735d-97ef-a6efcdf1becf",
  "type": "price",
  "is_active": true,
  "user_id": 14
}
```

### Step 3: Query Spike by ID
```php
SpikeEvent::find('019bd909-7818-735d-97ef-a6efcdf1becf');
// Result: NULL — spike does not exist
```

### Step 4: List All Spikes
```php
SpikeEvent::pluck('type')->unique()->join(', ');
// Result: demand, breakdown, blizzard, delay (no 'price')
```

---

## Root Cause Analysis

### Event Flow Diagram
```
┌──────────────────┐     ┌─────────────────────┐     ┌─────────────────┐
│ TimeAdvanced     │────▶│ SimulationService   │────▶│ SpikeOccurred   │
│ Event            │     │ ::processEventTick  │     │ Event           │
└──────────────────┘     └─────────────────────┘     └─────────────────┘
                                                              │
                                   ┌──────────────────────────┴────────────────┐
                                   ▼                                           ▼
                         ┌─────────────────┐                         ┌─────────────────┐
                         │ GenerateAlert   │                         │ ApplySpikeEffect│
                         │ (creates alert) │                         │ (applies effect)│
                         └─────────────────┘                         └─────────────────┘
```

### Two Code Paths That Fire `SpikeOccurred`

| Location | Code | User ID |
|----------|------|---------|
| `SimulationService::processEventTick` (line 71) | Uses `$this->gameState->user_id` | ✅ Correct |
| `GenerateSpike::onTimeAdvanced` (line 26) | `$factory->generate($event->day)` | ❌ No userId passed |

### Why the Spike Disappeared
The spike referenced in the alert was likely:
1. Created by a previous code version or manual test
2. Deleted during a game reset (`resetGame()` deletes all spikes for the user)
3. Never properly persisted due to transaction rollback

The alert survived because `GenerateAlert` stores a **serialized copy** of the spike object in the `data` column, not a foreign key reference.

---

## Resolution

### Data Cleanup
```php
Alert::where('id', '019bd909-781f-703c-b3fc-1183744607e1')->delete();
// Deleted the orphaned alert
```

### Code Fixes Applied
1. **Route correction**: Changed `spike_occurred` navigation from `/game/war-room` to `/game/spike-history`
2. **User filtering**: Added `where('user_id', $userId)` to `spikeHistory()` controller

---

## Recommendations

### Short-term
- [x] Delete orphaned alerts referencing non-existent spikes
- [x] Ensure controller filters spikes by authenticated user

### Long-term (Optional)
- [ ] Add `spike_event_id` foreign key to alerts table with ON DELETE SET NULL
- [ ] Add safeguard in `GenerateAlert` to skip if spike has no valid ID
- [ ] Remove or fix legacy `GenerateSpike` listener that doesn't pass userId

---

## Files Referenced

| File | Relevance |
|------|-----------|
| `app/Listeners/GenerateAlert.php` | Creates alerts from SpikeOccurred events |
| `app/Services/SimulationService.php` | Main spike lifecycle management |
| `app/Listeners/GenerateSpike.php` | Legacy listener (missing userId) |
| `app/Http/Controllers/GameController.php` | spikeHistory endpoint |
| `resources/js/components/game/game-header.tsx` | Notification dropdown with navigation |
