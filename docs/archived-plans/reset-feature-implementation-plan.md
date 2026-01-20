# Reset Feature Implementation Plan

**Created**: 2026-01-19
**Completed**: 2026-01-19
**Status**: Completed
**Purpose**: Add a per-user reset workflow that restores the game to a fresh Day 1 state with baseline inventory and seeded pipeline activity.

---

## Problem Statement
Players and testers need a reliable way to restart their game state without manually deleting data. The updated gameplay loop now seeds baseline inventory, in-transit pipeline activity, and initial spikes for new games. There is currently no reset endpoint or UI to trigger this behavior.

1. **No reset endpoint**: There is no `/game/reset` route or controller action to clear per-user state.
2. **No reset UI**: The dashboard lacks a reset button and confirmation flow.
3. **No reset tests**: There is no automated coverage to ensure reset behavior is safe and user-scoped.

These gaps make it slow to test onboarding and early-game behavior and increase the risk of stale or corrupted game state during development.

---

## Design Decisions (Gameplay/Engineering Preferences)

| Decision | Choice |
| :--- | :--- |
| Reset scope | Current authenticated user only |
| Inventory reset | Delete user inventory and re-seed via `InitializeNewGame` |
| Pipeline reset | Delete orders/transfers and re-seed via `InitializeNewGame` |
| Spikes reset | Delete spikes and re-seed initial spikes |
| UX safety | Require confirmation dialog before reset |
| Post-reset UX | Redirect to dashboard and full page reload |

---

## Solution Architecture

### Overview
```
[User clicks Reset]
        |
        v
[Confirm dialog]
        |
        v
POST /game/reset
        |
        v
GameController::resetGame
        |
        v
DB::transaction
  - delete orders/transfers/alerts/spikes/inventory
  - reset game_state day/cash/xp
  - InitializeNewGame(handle user)
        |
        v
redirect to /game/dashboard
        |
        v
Full reload to refresh Inertia props
```

---

## Implementation Tasks

### Phase 1: Backend Reset Endpoint [Completed]

#### Task 1.1: Add reset route [Completed]
**File**: `routes/web.php`

```php
Route::middleware(['auth', 'verified'])->prefix('game')->name('game.')->group(function () {
    // ...
    Route::post('/reset', [GameController::class, 'resetGame'])->name('reset');
});
```

**Key Logic/Responsibilities**:
* Expose a POST endpoint for reset.
* Restrict to authenticated, verified users.

#### Task 1.2: Implement reset logic [Completed]
**File**: `app/Http/Controllers/GameController.php`

```php
public function resetGame(InitializeNewGame $initializer): \Illuminate\Http\RedirectResponse
{
    DB::transaction(function () use ($initializer) {
        $user = auth()->user();

        // Clear all game data
        Order::where('user_id', $user->id)->delete();
        Transfer::where('user_id', $user->id)->delete();
        Alert::where('user_id', $user->id)->delete();
        SpikeEvent::where('user_id', $user->id)->delete();
        Inventory::where('user_id', $user->id)->delete();
        
        // Reset GameState
        $gameState = GameState::where('user_id', $user->id)->first();
        if ($gameState) {
            $gameState->update([
                'day' => 1,
                'cash' => 1000000,
                'xp' => 0,
            ]);
        }

        // Re-seed
        $initializer->handle($user);
    });

    return to_route('game.dashboard')->with('success', 'Game has been reset to Day 1.');
}
```

**Key Logic/Responsibilities**:
* Clear only per-user data tables.
* Reset `game_states` to Day 1 defaults.
* Re-seed baseline inventory, pipeline, and spikes.

---

### Phase 2: Frontend Reset UX [Completed]

#### Task 2.1: Add reset button component [Completed]
**File**: `resources/js/components/game/reset-game-button.tsx`

```tsx
export default function ResetGameButton() {
    const [showConfirm, setShowConfirm] = useState(false);

    const handleReset = () => {
        router.post('/game/reset', {}, {
            onSuccess: () => window.location.reload(),
        });
    };

    return (
        <>
            <Button onClick={() => setShowConfirm(true)} variant="destructive">
                Reset Game
            </Button>
            <ConfirmDialog
                open={showConfirm}
                onClose={() => setShowConfirm(false)}
                onConfirm={handleReset}
                title="Reset Game Progress?"
                message="This will permanently delete all progress and restart at Day 1."
            />
        </>
    );
}
```

**Key Logic/Responsibilities**:
* Require user confirmation.
* Trigger POST request and full reload on success.

#### Task 2.2: Provide confirmation dialog [Completed]
**File**: `resources/js/components/ui/confirm-dialog.tsx`

```tsx
export function ConfirmDialog({ open, onClose, onConfirm, title, message }: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <p className="text-sm text-gray-600">{message}</p>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Cancel</Button>
                    <Button variant="destructive" onClick={onConfirm}>Reset Game</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

**Key Logic/Responsibilities**:
* Present a consistent destructive action dialog.
* Keep the component reusable for other confirmations.

#### Task 2.3: Integrate reset button on dashboard [Completed]
**File**: `resources/js/Pages/game/dashboard.tsx`

```tsx
<div className="mt-8 border-t pt-4">
    <h3 className="text-lg font-semibold mb-2">Game Settings</h3>
    <ResetGameButton />
</div>
```

**Key Logic/Responsibilities**:
* Expose reset in a low-risk settings section.
* Keep visibility limited to authenticated users.

---

### Phase 3: Tests [Completed]

#### Task 3.1: Add feature tests [Completed]
**File**: `tests/Feature/ResetGameTest.php`

```php
public function test_reset_clears_and_reseeds_user_state(): void
{
    $user = User::factory()->create();

    Order::factory()->count(2)->create(['user_id' => $user->id]);
    Transfer::factory()->count(1)->create(['user_id' => $user->id]);
    Alert::factory()->count(1)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/game/reset');

    $response->assertRedirect('/game/dashboard');
    $this->assertEquals(1, GameState::where('user_id', $user->id)->value('day'));
    $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
}
```

**Key Logic/Responsibilities**:
* Ensure reset is user-scoped.
* Verify Day 1 defaults are restored.
* Confirm per-user data is cleared and reseeded.

---

## File Summary

| File | Action | Status |
| :--- | :--- | :--- |
| `routes/web.php` | Modify | Completed |
| `app/Http/Controllers/GameController.php` | Modify | Completed |
| `app/Actions/InitializeNewGame.php` | Reuse | Completed |
| `resources/js/components/game/reset-game-button.tsx` | Create | Completed |
| `resources/js/components/ui/confirm-dialog.tsx` | Create | Completed |
| `resources/js/Pages/game/dashboard.tsx` | Modify | Completed |
| `tests/Feature/ResetGameTest.php` | Create | Completed |

---

## Execution Order
1. **Add backend route and controller logic** — ensure reset works before UI.
2. **Wire frontend button + confirmation dialog** — add safe UX to trigger reset.
3. **Add feature tests** — validate scope and ensure regressions are caught.

---

## Edge Cases to Handle
1. **Missing world data**: If locations/products are not seeded, `InitializeNewGame` should no-op without failing. [Completed]
2. **Unauthorized access**: Guests should be redirected to login. [Completed]
3. **Concurrent resets**: Use a transaction to keep data consistent. [Completed]

---

## Rollback Plan
1. Remove `/game/reset` route and controller method.
2. Remove reset UI components and dashboard integration.
3. Delete the feature test file.

---

## Success Criteria
- [x] `/game/reset` resets the authenticated user's game state to Day 1 defaults.
- [x] Baseline inventory and pipeline activity are reseeded after reset.
- [x] Reset does not affect other users' data.
- [x] Confirmation dialog prevents accidental resets.

---