# Gameplay Documentation Overview

This directory contains comprehensive documentation about the Moonshine Coffee Management Sim gameplay loop, mechanics, and improvement proposals.

---

## 🔴 START HERE: Critical Bugs

**If you're about to work on this project, read this first:**

### [`CRITICAL-BUGS.md`](./CRITICAL-BUGS.md)
**Status:** 🔴 BLOCKING - Must fix immediately
**Fix Time:** ~15 minutes

Contains critical bugs that make the game unplayable:
1. **Starting cash bug**: Players start with $100 instead of $10,000
2. **User scoping bugs**: Multi-user data leakage in alerts/reputation/strikes

**Action Required:** Fix these bugs before any feature development.

---

## 📚 Core Documentation

### [`gameplay-loop-mechanics-analysis.md`](./gameplay-loop-mechanics-analysis.md)
**Date:** 2026-01-19 (Updated: 2026-01-24)
**Status:** ✅ Complete (with bug annotations)

**Original comprehensive analysis covering:**
- Initial game state and seeding
- Day progression mechanics (4-phase tick system)
- Event/Physics/Consumption/Analysis phases
- Game resolution and end conditions
- Player decision tracking architecture
- State persistence and sharing via Inertia
- Reset feature implementation guide

**Updated with:**
- Critical bug annotations
- References to fix documentation
- Corrected cash initialization values

**Best For:**
- Understanding how the game loop currently works
- Technical reference for simulation phases
- Database schema and model relationships

---

### [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md)
**Date:** 2026-01-24
**Status:** ✅ Complete

**Deep-dive analysis including:**

#### Current State Analysis
- Day 1 initial state with actual values
- 4-phase tick system (Event → Physics → Consumption → Analysis)
- Detailed consumption mechanics with demand simulation
- Cash handling architecture (cent-based system)

#### Discrepancies and Bugs
- Critical bugs (starting cash, user scoping)
- Documentation vs. implementation differences
- Missing features and incomplete systems

#### Engagement Issues
- Day 1 passivity problem
- Invisible demand system
- Lack of meaningful consequences
- Abstract spike system
- Missing progression
- Non-actionable analytics
- No multi-day planning

#### Improvement Proposals (9 Priority Levels)
- **Priority 0:** Critical bug fixes (15 min)
- **Priority 1:** Quick wins - demand forecasting, stockout consequences (1-2 weeks)
- **Priority 2:** Core engagement - tutorial quests, spike resolution (2-4 weeks)
- **Priority 3:** Depth & strategy - AI recommendations, what-if scenarios (4-8 weeks)
- **Priority 4-9:** Advanced features - prestige system, challenges, endgame scenarios

#### Implementation Roadmap
- Phased approach with effort estimates
- Specific files to create/modify
- Testing strategies
- Success metrics

**Best For:**
- Understanding engagement problems
- Planning feature development
- Prioritizing improvements
- Implementation guidance

---

## 📖 Document Relationships

```
CRITICAL-BUGS.md
├─ References: gameplay-loop-analysis-and-improvements.md
└─ Action: Fix bugs before reading other docs

gameplay-loop-mechanics-analysis.md
├─ Original technical analysis (2026-01-19)
├─ Updated with bug annotations (2026-01-24)
└─ References: CRITICAL-BUGS.md, gameplay-loop-analysis-and-improvements.md

gameplay-loop-analysis-and-improvements.md
├─ Builds on: gameplay-loop-mechanics-analysis.md
├─ Adds: Bug analysis, engagement issues, improvement proposals
└─ References: CRITICAL-BUGS.md
```

---

## 🎯 Quick Navigation

### I want to...

**Fix critical bugs** → [`CRITICAL-BUGS.md`](./CRITICAL-BUGS.md)
- 15-minute fixes for game-breaking issues

**Understand current gameplay** → [`gameplay-loop-mechanics-analysis.md`](./gameplay-loop-mechanics-analysis.md)
- Technical deep-dive into simulation engine

**Understand engagement problems** → [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md) (Section: Engagement Issues)
- Why players aren't motivated to play

**Plan improvements** → [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md) (Section: Improvement Proposals)
- Prioritized feature development roadmap

**Understand cash handling** → [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md) (Section: Cash Handling Architecture)
- Cent-based monetary system explained

**Understand consumption mechanics** → [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md) (Section: Phase 3: Consumption Tick)
- How inventory depletes daily

**Understand spike system** → [`gameplay-loop-mechanics-analysis.md`](./gameplay-loop-mechanics-analysis.md) (Section: Phase 1: Event Tick)
- How chaos events are generated and applied

---

## 🔧 Development Workflow

### For New Contributors

1. **Read** [`CRITICAL-BUGS.md`](./CRITICAL-BUGS.md) - Understand current blockers
2. **Fix** critical bugs (15 minutes) - Get game to playable state
3. **Read** [`gameplay-loop-mechanics-analysis.md`](./gameplay-loop-mechanics-analysis.md) - Understand technical architecture
4. **Read** [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md) - Understand improvement roadmap
5. **Pick** a Priority 1 or 2 task from improvement proposals
6. **Implement** with testing and documentation

### For Bug Fixes

1. Check if bug is already documented in [`CRITICAL-BUGS.md`](./CRITICAL-BUGS.md)
2. If not, add to [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md) Critical Bugs section
3. Implement fix with tests
4. Update relevant documentation

### For Feature Development

1. Review improvement proposals in [`gameplay-loop-analysis-and-improvements.md`](./gameplay-loop-analysis-and-improvements.md)
2. Choose feature from appropriate priority level
3. Follow implementation guide for specific files to modify
4. Add tests and update documentation
5. Mark as complete in roadmap section

---

## 📊 Current Status Summary

### Game State
- ❌ **Unplayable** due to starting cash bug ($100 vs $10,000)
- ❌ **Multi-user broken** due to scoping bugs
- ⚠️ **Missing engagement features** (quests, progression, stakes)
- ✅ **Core simulation working** (tick system, spikes, logistics)

### Documentation State
- ✅ **Complete technical analysis** of current implementation
- ✅ **Complete bug analysis** with fixes identified
- ✅ **Complete improvement roadmap** with 9 priority levels
- ✅ **Effort estimates** for all proposed features

### Next Steps
1. **Immediate:** Fix critical bugs (15 min)
2. **Short-term:** Implement Priority 1 features (1-2 weeks)
3. **Medium-term:** Add core engagement features (2-4 weeks)
4. **Long-term:** Build strategic depth and replayability (4-12 weeks)

---

## 📝 Version History

| Date | Document | Version | Changes |
|------|----------|---------|---------|
| 2026-01-19 | `gameplay-loop-mechanics-analysis.md` | 1.0 | Initial comprehensive analysis |
| 2026-01-24 | `gameplay-loop-mechanics-analysis.md` | 1.1 | Added critical bug annotations |
| 2026-01-24 | `gameplay-loop-analysis-and-improvements.md` | 1.0 | Created with engagement analysis and proposals |
| 2026-01-24 | `CRITICAL-BUGS.md` | 1.0 | Created for immediate bug fixes |
| 2026-01-24 | `README-GAMEPLAY.md` | 1.0 | Created navigation guide |

---

## 🔗 Related Documentation

- [`../CLAUDE.md`](../CLAUDE.md) - Project overview and AI coding guidelines
- [`./technical-design-document.md`](./technical-design-document.md) - System architecture
- [`./notification-system.md`](./notification-system.md) - Alert system documentation
- [`./analytics-page-audit.md`](./analytics-page-audit.md) - Analytics implementation details

---

**Maintained By:** Development Team
**Last Updated:** 2026-01-24
