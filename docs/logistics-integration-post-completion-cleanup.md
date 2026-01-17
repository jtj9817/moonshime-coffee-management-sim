# Stabilization & Verification Plan (Pre-Track Documentation)

## 1. Overview
This document identifies the "missing items" and technical debt accumulated during the initial implementation of the Hybrid Event-Topology and UI Integration tracks. While the core functionality is present in the codebase, certain granular sub-tasks remained unchecked in archives, and minor architectural redundancies were introduced. This plan serves as a precursor for a "Stabilization & Cleanup" Conductor track.

## 2. Identified Gaps & Technical Debt

### 2.1 Hybrid Event-Topology (Archived Track: `hybrid_event_topology_20260116`)
**Status:** Functionally complete but documentation/verification is desynchronized.
- **Unchecked Granular Tasks:** Entirety of Phase 1 (Physical Graph) and Phase 2 (Causal Graph) in the archived `plan.md` remain marked as `[ ]` despite implementation.
- **Verification Gaps:** 
    - Relationship integrity between `Route` and `Location` (Source/Target).
    - Factory reliability for "Graph-Targeting" Spike Events.
    - Persistence of DAG fields (`parent_id`, `type`) in `SpikeEvent` and their impact on simulation cycles.

### 2.2 UI Integration (Archived Track: `ui_integration_20260116`)
**Status:** Functionally complete with minor UX and architectural deviations.
- **KPI Redundancy:** `GameController::calculateKPIs` currently passes "Logistics Health" twice—once within the `kpis` array and once as a top-level prop. The frontend (`dashboard.tsx`) contains manual filtering logic to deduplicate this.
- **Restock Form UX:** The original spec required disabling the primary route option when blocked. The current implementation keeps the option enabled to show a "Route Blocked" status message and disables the **Submit** button instead. 
- **Alternative Suggestion Logic:** Verify that the "Switch to Alternative" state correctly updates the server-side cost calculation upon final submission (currently relies on client-side state).

### 2.3 System-Wide Verification
- **Manual Verification Logs:** Lack of formal logs in `tests/manual/` confirming the "Full Disruption Lifecycle" passed manual inspection during the last archive.
- **Test Coverage Alignment:** Ensure `LogisticsIntegrationLifecycleTest.php` covers the specific edge cases defined in the Causal Graph spec (e.g., recursive alert propagation).

## 3. Proposed Stabilization Track (Next Steps)

### Phase 1: Architectural Cleanup
- [ ] Refactor `GameController::calculateKPIs` to remove redundant "Logistics Health" from the generic KPI array.
- [ ] Align `transfers.tsx` UX with the literal specification (disabling blocked primary options) or update the Specification to reflect the new "Informational Blocking" standard.
- [ ] Standardize the pathfinding API response to include a `is_premium` flag for alternative routes.

### Phase 2: Verification & Documentation Sync
- [ ] Execute a full audit of `Route` and `SpikeEvent` migrations against the `hybrid-event-topology.md` architecture doc.
- [ ] Synchronize the archived `hybrid_event_topology_20260116/plan.md` by verifying and marking completed tasks.
- [ ] Create a comprehensive manual verification script `tests/manual/verify_stabilization_v1.php` using the `laravel-manual-testing` skill.

### Phase 3: Integration Stress Testing
- [ ] Write a Stress Test for the DAG propagation: Trigger a Root Spike -> Verify 10+ Symptom Alerts -> Verify Mass Reachability failure -> Resolve Spike -> Verify System Recovery.
- [ ] Verify Dijkstra performance on a larger seeded graph (Hub-and-Spoke with 20+ nodes).

## 4. Acceptance Criteria
- All archived plans correctly reflect the state of the codebase.
- No redundant props passed to Inertia dashboard.
- REST API responses for logistics are strictly typed and consistent with DTOs.
- Manual verification script successfully runs a 10-day simulation cycle with 5+ overlapping spikes.
