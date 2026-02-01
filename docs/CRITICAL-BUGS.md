# CRITICAL BUGS - FIX IMMEDIATELY

**Last Updated:** 2026-01-30
**Status:** ✅ ALL BUGS FIXED
**Original Date:** 2026-01-24
**Original Priority:** P0 - MUST FIX BEFORE ANY OTHER WORK

**RESOLUTION SUMMARY:**
Both critical bugs identified on 2026-01-24 have been fixed and verified:
- Bug #1 (Starting Cash): Fixed - verified in codebase
- Bug #2 (User Scoping): Fixed - verified in codebase

---

## Bug #1: Starting Cash is $100 instead of $10,000 ✅ FIXED

**Fix Date:** Before 2026-01-27
**Verification Date:** 2026-01-30

### Impact (Historical)
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

### Verification (2026-01-30)

**Status:** ✅ FIXED

**Evidence:**
- `app/Actions/InitializeNewGame.php:41` - Uses `1000000.00` (correct)
- `app/Http/Middleware/HandleInertiaRequests.php:108` - Uses `1000000.00` (correct)

**Verification Commands:**
```bash
# 1. Verify InitializeNewGame.php
grep -n "cash.*1000000" app/Actions/InitializeNewGame.php
# Output: 41:            ['cash' => 1000000.00, 'xp' => 0, 'day' => 1]

# 2. Verify HandleInertiaRequests.php
grep -n "cash.*1000000" app/Http/Middleware/HandleInertiaRequests.php
# Output: 108:            ['cash' => 1000000.00, 'xp' => 0, 'day' => 1]
```

### Testing (Historical)
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

## Bug #2: User Scoping Issues (Multi-User Data Leakage) ✅ FIXED

**Fix Date:** Before 2026-01-27
**Verification Date:** 2026-01-30

### Impact (Historical)
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

### Verification (2026-01-30)

**Status:** ✅ FIXED

**Evidence:**
All three locations in `app/Http/Middleware/HandleInertiaRequests.php` now include `user_id` scoping:

1. **Alerts List (Line 90):** ✅ FIXED
   ```php
   'alerts' => Alert::where('user_id', $user->id)
       ->where('is_read', false)
       ->latest()
       ->take(10)
       ->get(),
   ```

2. **Reputation Calculation (Line 139):** ✅ FIXED
   ```php
   $alertCount = Alert::where('user_id', $user->id)
       ->where('is_read', false)
       ->count();
   ```

3. **Strikes Calculation (Line 153):** ✅ FIXED
   ```php
   return Alert::where('user_id', $user->id)
       ->where('is_read', false)
       ->where('severity', 'critical')
       ->count();
   ```

**Verification Commands:**
```bash
# Verify all user_id scoping in HandleInertiaRequests
grep -n "Alert::where('user_id', \$user->id)" app/Http/Middleware/HandleInertiaRequests.php
# Output should show lines 90, 139, 153
```

### Testing (Historical)
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

## Quick Fix Checklist (COMPLETED)

- [x] **Bug #1**: Change `10000.00` → `1000000` in `InitializeNewGame.php:41` ✅
- [x] **Bug #1**: Change `10000.00` → `1000000` in `HandleInertiaRequests.php:108` ✅
- [x] **Bug #2**: Add `user_id` filter to alerts list in `HandleInertiaRequests.php:90` ✅
- [x] **Bug #2**: Add `user_id` filter to reputation calc in `HandleInertiaRequests.php:139` ✅
- [x] **Bug #2**: Add `user_id` filter to strikes calc in `HandleInertiaRequests.php:153` ✅
- [x] **Test**: Verify starting cash is $10,000 for new games ✅
- [x] **Test**: Verify multi-user isolation (alerts, reputation, strikes) ✅
- [x] **Deploy**: Fixes deployed and verified ✅

**Verification Date:** 2026-01-30

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
