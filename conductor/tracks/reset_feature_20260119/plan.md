# Implementation Plan - Reset Feature

## Phase 1: Backend Reset Endpoint
- [ ] Task: Add `resetGame` route to `routes/web.php` (POST /game/reset).
- [ ] Task: Implement `GameController::resetGame` logic with DB transaction and `InitializeNewGame` call.
- [ ] Task: Conductor - User Manual Verification 'Backend Reset Endpoint' (Protocol in workflow.md)

## Phase 2: Frontend Reset UX
- [ ] Task: Create `ConfirmDialog` component (`resources/js/components/ui/confirm-dialog.tsx`).
- [ ] Task: Create `ResetGameButton` component with API integration (`resources/js/components/game/reset-game-button.tsx`).
- [ ] Task: Integrate `ResetGameButton` into the Dashboard page (`resources/js/Pages/game/dashboard.tsx`).
- [ ] Task: Conductor - User Manual Verification 'Frontend Reset UX' (Protocol in workflow.md)

## Phase 3: Tests
- [ ] Task: Create Feature Test `tests/Feature/ResetGameTest.php` to verify state clearing and reseeding.
- [ ] Task: Conductor - User Manual Verification 'Tests' (Protocol in workflow.md)