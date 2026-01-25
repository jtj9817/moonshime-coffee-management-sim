# Implementation Plan - Critical Bug Fixes (Cash & User Scoping)

## Phase 1: Fix Starting Cash Initialization [checkpoint: 0d7d0da]
- [x] Task: Create a reproduction test case for incorrect starting cash.
    - [x] Create `tests/Feature/GameInitializationTest.php`.
    - [x] Write a test that initializes a new game and asserts cash is 1,000,000 cents.
    - [x] Run test to confirm failure (Red).
- [x] Task: Fix the initialization logic.
    - [x] Update `app/Actions/InitializeNewGame.php` to use correct value `1000000`.
    - [x] Update `app/Http/Middleware/HandleInertiaRequests.php` fallback creation logic (if applicable).
    - [x] Run test to confirm pass (Green).
- [x] Task: Conductor - User Manual Verification 'Phase 1: Fix Starting Cash Initialization' (Protocol in workflow.md)

## Phase 2: Fix Multi-User Data Leakage [checkpoint: 7d5dea7]
- [x] Task: Create reproduction test cases for data leakage.
    - [x] Create `tests/Feature/MultiUserIsolationTest.php`.
    - [x] Write a test with two users (A and B).
    - [x] Seed alerts and spikes for User B.
    - [x] Assert that User A's Inertia response (shared props) does *not* contain User B's alerts or spikes.
    - [x] Assert that User A's reputation is not affected by User B's unread alerts.
    - [x] Run tests to confirm failure (Red).
- [x] Task: Implement User Scoping in Middleware.
    - [x] Modify `app/Http/Middleware/HandleInertiaRequests.php`.
    - [x] Update `getGameData` method to strictly filter `Alert::where('user_id', $user->id)` and `SpikeEvent::where('user_id', $user->id)`.
    - [x] Update reputation calculation to use user-scoped alert count.
    - [x] Run tests to confirm pass (Green).
- [x] Task: Conductor - User Manual Verification 'Phase 2: Fix Multi-User Data Leakage' (Protocol in workflow.md)