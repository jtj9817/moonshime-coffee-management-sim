# Specification: Critical Bug Fixes (Cash & User Scoping)

## Overview
This track addresses two critical bugs identified in the gameplay loop mechanics analysis:
1. **Starting Cash Bug**: New games are currently initialized with 10,000.00 ($100.00) instead of the intended 1,000,000 cents ($10,000.00).
2. **Multi-User Data Leakage**: Alerts, Reputation, and Spike events are not properly scoped to the authenticated user in the Inertia middleware, leading to data leakage between players.

## Functional Requirements
- **Correct Initialization**: Update the game state initialization logic to use 1,000,000 cents as the default starting cash.
- **User-Scoped Data**:
    - Ensure `Alert` queries in middleware only return alerts belonging to the authenticated user.
    - Ensure `SpikeEvent` queries in middleware only return active spikes belonging to the authenticated user.
    - Ensure the Reputation calculation (based on unread alerts) only counts alerts belonging to the authenticated user.

## Non-Functional Requirements
- **Data Isolation**: Strict separation of game state and event data between different users.
- **TDD Adherence**: Fixes must be verified with failing automated tests before implementation.

## Acceptance Criteria
- [ ] New users start with exactly 1,000,000 cents ($10,000.00) in their `game_states` record.
- [ ] Users can only see their own unread alerts in the dashboard.
- [ ] Users only see spikes that are active for their specific game.
- [ ] Reputation is calculated correctly based ONLY on the current user's unread alerts.
- [ ] Automated feature tests prove that User A cannot see User B's alerts or spikes.

## Out of Scope
- Migrating existing "broken" game states (this track only fixes the logic for new/future initializations).
- Implementing the "Reset Game" feature.
- Implementing "Game Over" or "Victory" conditions.