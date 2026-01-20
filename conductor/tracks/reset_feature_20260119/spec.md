# Specification: Game Reset Feature

## Problem Statement
Players and testers need a reliable way to restart their game state without manually deleting data. Currently, there is no reset endpoint, UI, or automated coverage for this, making onboarding testing slow and increasing the risk of stale data.

## Goal
Add a per-user reset workflow that restores the game to a fresh Day 1 state with baseline inventory and seeded pipeline activity.

## Design Decisions
- **Scope:** Current authenticated user only.
- **Inventory/Pipeline/Spikes:** Delete and re-seed via `InitializeNewGame`.
- **UX Safety:** Require confirmation dialog before reset.
- **Post-Reset:** Redirect to dashboard and full page reload.

## Solution Architecture
1. **Trigger:** User clicks "Reset Game" -> Confirmation Dialog.
2. **Action:** POST `/game/reset` -> `GameController::resetGame`.
3. **Logic:** DB Transaction:
    - Delete `orders`, `transfers`, `alerts`, `spikes`, `inventory`.
    - Reset `game_state` (Day 1, $1M Cash, 0 XP).
    - Call `InitializeNewGame`.
4. **Response:** Redirect to `/game/dashboard` with full reload.

## Success Criteria
- [ ] `/game/reset` resets the authenticated user's game state to Day 1 defaults.
- [ ] Baseline inventory and pipeline activity are reseeded after reset.
- [ ] Reset does not affect other users' data.
- [ ] Confirmation dialog prevents accidental resets.