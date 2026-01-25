# CRITICAL BUGS - FIX IMMEDIATELY

**Date:** 2026-01-24
**Status:** 🔴 BLOCKING - Game is currently unplayable
**Fix Time:** ~15 minutes
**Priority:** P0 - MUST FIX BEFORE ANY OTHER WORK

---

## Bug #1: Starting Cash is $100 instead of $10,000 ⚠️⚠️⚠️

### Impact
Players start with **100x less money** than intended, making the game unplayable.
- Orders cost ~$100-500
- Players can only afford 1 order before bankruptcy
- Inventory restocking becomes impossible

### Root Cause
Code initializes with decimal `10000.00` instead of integer `1000000`.

**Cash is stored as CENTS in database:**
- Correct: `1000000` cents = $10,000.00
- Current: `10000` cents = $100.00

### Files to Fix

#### File 1: `app/Actions/InitializeNewGame.php`
**Line 41:**
```php
// BEFORE (WRONG):
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 10000.00, 'xp' => 0, 'day' => 1]
);

// AFTER (CORRECT):
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 1000000, 'xp' => 0, 'day' => 1]  // 1M cents = $10,000
);
```

#### File 2: `app/Http/Middleware/HandleInertiaRequests.php`
**Line 107:**
```php
// BEFORE (WRONG):
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 10000.00, 'xp' => 0, 'day' => 1]
);

// AFTER (CORRECT):
$gameState = GameState::firstOrCreate(
    ['user_id' => $user->id],
    ['cash' => 1000000, 'xp' => 0, 'day' => 1]  // 1M cents = $10,000
);
```

### Testing
```bash
# 1. Reset a test user's game state
php artisan tinker
>>> $user = User::first();
>>> $user->gameState()->delete();
>>> app(\App\Actions\InitializeNewGame::class)->handle($user);
>>> $user->gameState->cash;  // Should output: 1000000 (NOT 10000)

# 2. Verify in browser
# Visit /game/dashboard
# Check that cash shows $10,000.00 (NOT $100.00)
```

---

## Bug #2: User Scoping Issues (Multi-User Data Leakage) 🔓

### Impact
In multi-user environments:
- Players see each other's alerts
- Reputation calculated from ALL players' alerts (not just yours)
- Strikes calculated from ALL players' critical alerts

### Root Cause
Missing `->where('user_id', $user->id)` filters in middleware queries.

### File to Fix: `app/Http/Middleware/HandleInertiaRequests.php`

#### Fix #1: Alerts List (Line 90)
```php
// BEFORE (WRONG):
'alerts' => Alert::where('is_read', false)
    ->latest()
    ->take(10)
    ->get(),

// AFTER (CORRECT):
'alerts' => Alert::where('user_id', $user->id)
    ->where('is_read', false)
    ->latest()
    ->take(10)
    ->get(),
```

#### Fix #2: Reputation Calculation (Line 138)
```php
// BEFORE (WRONG):
$alertCount = Alert::where('is_read', false)->count();

// AFTER (CORRECT):
$alertCount = Alert::where('user_id', $user->id)
    ->where('is_read', false)
    ->count();
```

#### Fix #3: Strikes Calculation (Line 150)
```php
// BEFORE (WRONG):
return Alert::where('is_read', false)
    ->where('severity', 'critical')
    ->count();

// AFTER (CORRECT):
return Alert::where('user_id', $user->id)
    ->where('is_read', false)
    ->where('severity', 'critical')
    ->count();
```

### Testing
```bash
# 1. Create two test users
php artisan tinker
>>> $user1 = User::factory()->create(['email' => 'test1@example.com']);
>>> $user2 = User::factory()->create(['email' => 'test2@example.com']);

# 2. Create alert for user1
>>> Alert::create(['user_id' => $user1->id, 'type' => 'stockout', 'severity' => 'critical', 'message' => 'User 1 Alert']);

# 3. Login as user2 and verify they DON'T see user1's alert
# Visit /game/dashboard as user2
# Should NOT see "User 1 Alert"
```

---

## Quick Fix Checklist

- [ ] **Bug #1**: Change `10000.00` → `1000000` in `InitializeNewGame.php:41`
- [ ] **Bug #1**: Change `10000.00` → `1000000` in `HandleInertiaRequests.php:107`
- [ ] **Bug #2**: Add `user_id` filter to alerts list in `HandleInertiaRequests.php:90`
- [ ] **Bug #2**: Add `user_id` filter to reputation calc in `HandleInertiaRequests.php:138`
- [ ] **Bug #2**: Add `user_id` filter to strikes calc in `HandleInertiaRequests.php:150`
- [ ] **Test**: Verify starting cash is $10,000 for new games
- [ ] **Test**: Verify multi-user isolation (alerts, reputation, strikes)
- [ ] **Deploy**: Commit with message "fix(game): correct starting cash and user scoping"

---

## Why These Are Critical

### Without Bug #1 Fix:
- ❌ New players cannot complete tutorial
- ❌ First order costs more than starting cash
- ❌ Game appears broken, users will churn immediately
- ❌ All gameplay balancing is invalidated

### Without Bug #2 Fix:
- ❌ Multiplayer data corruption
- ❌ Privacy issue - players see each other's game state
- ❌ Reputation/strikes incorrect for all users
- ❌ Analytics and metrics are unreliable

---

## Post-Fix Verification

After fixing, verify these scenarios work correctly:

### Scenario 1: New Game
1. Create new user account
2. Visit dashboard
3. ✅ Cash should show **$10,000.00**
4. ✅ Can place order for ~$200-400
5. ✅ Cash decrements correctly

### Scenario 2: Multi-User Isolation
1. User A generates stockout alert
2. User B logs in
3. ✅ User B should NOT see User A's alert
4. ✅ User B's reputation unaffected by User A's alerts

### Scenario 3: Day Advancement
1. Start new game with $10,000
2. Place order for $300
3. Advance day
4. ✅ Cash = $10,000 - $300 = $9,700
5. ✅ No negative cash

---

## Related Documentation
- `docs/gameplay-loop-analysis-and-improvements.md` - Full analysis and improvement proposals
- `database/migrations/2026_01_16_055234_create_game_states_table.php` - Schema showing correct default (1000000)
- `app/Http/Middleware/HandleInertiaRequests.php` - Middleware with bugs
- `app/Actions/InitializeNewGame.php` - Initialization with cash bug
