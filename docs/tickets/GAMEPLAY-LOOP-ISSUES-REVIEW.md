# GAME-LOOP-REVIEW: Gameplay Loop Issues Verification

## Summary
Verify which issues from `docs/gameplay-loop-analysis-and-improvements.md` remain valid in the current codebase. This ticket records current findings and required corrections.

## Context
- Source doc: `docs/gameplay-loop-analysis-and-improvements.md`
- Cash is stored in **cents** (integer) and must remain so.
- Multi-user isolation is a **requirement**.

## Findings
### Still Valid / Partially Fixed
- **Starting cash mismatch (partially fixed).**
  - `InitializeNewGame` and shared game state now initialize with `1000000.00`, but other creation/reset paths still use `10000.00`, causing inconsistent starting cash.
  - Files:
    - `app/Providers/GameServiceProvider.php:51` and `app/Providers/GameServiceProvider.php:55`
    - `app/Http/Controllers/GameController.php:407`
    - Correct paths: `app/Actions/InitializeNewGame.php:41`, `app/Http/Middleware/HandleInertiaRequests.php:106`
- **Dashboard alerts still global (multi-user leakage).**
  - `GameController::dashboard()` queries unread alerts without `user_id` filter.
  - File: `app/Http/Controllers/GameController.php:38`
- **Stockouts remain invisible to the player.**
  - Demand simulation logs stockouts but does not emit alerts/events or record lost sales.
  - File: `app/Services/DemandSimulationService.php:69`
- **Spike mitigation lacks gameplay effect.**
  - Mitigation only logs an action; no simulation changes occur unless early resolution is used (breakdown/blizzard only).
  - File: `app/Services/SpikeResolutionService.php:75`
- **Quests are still static placeholders.**
  - `getActiveQuests()` returns a hardcoded quest array.
  - File: `app/Http/Controllers/GameController.php:445`
- **Analytics remain non-actionable.**
  - `revenue7Day` is hardcoded to `0` and no recommendation system is wired into analytics.
  - File: `app/Http/Controllers/GameController.php:206`

### Verified Fixed
- **Reputation/strikes and shared alerts in Inertia middleware are user-scoped.**
  - File: `app/Http/Middleware/HandleInertiaRequests.php:133`, `app/Http/Middleware/HandleInertiaRequests.php:151`
- **InitializeNewGame starts with cent-based cash.**
  - File: `app/Actions/InitializeNewGame.php:41`

## Requirements (Technical)
- Ensure **all** GameState creation and reset paths use cent-based initialization (`1000000` for $10,000.00).
- Enforce **user scoping** for all alert queries and any dashboard-derived aggregates.
- Emit a **stockout signal** (alert/event + persisted record) when demand exceeds inventory.
- If mitigation is offered in the UI, it must **affect simulation state** or be removed.
- Replace hardcoded quests with **data-driven** sources (DB/service).
- Replace analytics placeholders with **computed metrics** and/or persisted facts.

## Acceptance Criteria
- No code path initializes or resets cash with `10000.00`; all use integer cents aligned with the schema default.
- Alerts visible on dashboard are scoped to `auth()->id()`.
- A stockout produces a persisted record and a user-visible alert/event.
- Mitigation actions produce measurable changes in spike effects or inventory/logistics state.
- Quests are not hardcoded in controller code.
- Analytics values are derived from persisted data rather than placeholders.

## References
- `docs/gameplay-loop-analysis-and-improvements.md`
