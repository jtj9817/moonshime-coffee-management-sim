# Game State Persistence: Architectural Trade-offs & Brainstorming

## Context

This document captures architectural trade-offs and design considerations for implementing game state persistence in Moonshine Coffee Management Sim. The discussion focuses on supporting both anonymous (session-based) and registered (user-based) players, with a smooth migration path between the two.

Key constraints:
- Zero-friction onboarding for anonymous players
- Seamless migration to registered accounts without data loss
- Laravel 12 + Inertia.js + React 19 stack
- Existing architecture uses singleton services, event-driven patterns, and strategy patterns

---

## Trade-off 1: Single Table with Dual Key vs. Two Separate Tables

### Single Table with XOR Constraint

**Approach:**
- One `game_states` table with both `user_id` (nullable) and `session_id` (nullable) columns
- Database constraint enforces exactly one is set: `user_id XOR session_id`
- All related entities (orders, transfers, policies) reference `game_state_id`

**Pros:**
- Simpler data model with single source of truth
- Referential integrity maintained through foreign keys
- Anonymous-to-registered migration only updates one row (flip ownership)
- All game data automatically preserved during migration

**Cons:**
- Query complexity: every lookup requires `where('user_id', ...)->orWhere('session_id', ...)` pattern
- Indexing challenges on OR conditions
- Additional application-layer validation required to enforce constraint logic
- More complex joins when filtering by user

### Two Separate Tables

**Approach:**
- `user_game_states` table (references users)
- `anonymous_game_states` table (references sessions)
- Each table has its own relationships to orders, transfers, etc.
- Migration copies data between tables

**Pros:**
- Simpler queries: direct joins on specific table
- Efficient indexing: user_id on one table, session_id on other
- Clear semantic separation: registered vs. anonymous
- No database-level constraints to manage

**Cons:**
- Duplicate schema to maintain (two migrations, two models)
- Migration complexity: copy rows + update all foreign keys in related tables
- Polymorphic relationships needed for orders/transfers to reference either table type
- Potential for inconsistent data between tables if schema diverges
- Foreign key management becomes complex (orders can't FK to both tables)

### Hybrid Approach: Composite Key

**Approach:**
- Both fields present, but nullable
- Unique constraint on `(user_id, session_id)` combination
- Application enforces business rules

**Pros:**
- Flexibility to store both (rare edge cases)
- Simplifies some query patterns

**Cons:**
- Same query complexity as XOR constraint
- Allows invalid data states without enforcement
- Ambiguous ownership semantics

---

## Trade-off 2: Session ID vs. Browser Fingerprint for Anonymous Play

### Session ID Only

**Approach:**
- Use Laravel's built-in session ID (`session()->getId()`)
- Anonymous game states tied to session
- Session expires after configured TTL (default 2 hours)

**Pros:**
- Built-in Laravel functionality, no external dependencies
- Privacy-compliant: no persistent device tracking
- Clean separation: session gone = game gone
- Simple implementation with existing session middleware
- Compliant with GDPR and privacy expectations

**Cons:**
- Data loss on browser close or session expiration
- Poor UX: players lose progress in short timeframes
- No cross-device continuity for anonymous users
- Users may rage-quit after losing progress

### Browser Fingerprinting

**Approach:**
- Use Canvas API, fonts, screen resolution, timezone to generate device ID
- Anonymous states tied to persistent fingerprint
- Survives browser sessions, cookies, and cache clearing

**Pros:**
- Persistent across sessions and browsers on same device
- Better UX: return to game days/weeks later
- Reduces data loss complaints

**Cons:**
- Privacy-invasive: GDPR and CCPA compliance risks
- Unreliable: fingerprint changes on browser updates, OS updates, hardware changes
- Device-sharing problem: multiple users on same device collide
- Implementation complexity: requires multiple data points and entropy
- Potential for fingerprint spoofing/abuse

### LocalStorage-First with Backend Fallback

**Approach:**
- Anonymous game state stored entirely in localStorage
- Backend only engaged on registration (migration)
- Server never sees anonymous data

**Pros:**
- Complete privacy: no server-side tracking
- Zero backend load for anonymous play
- Infinite retention until user clears browser data
- Simplest backend implementation

**Cons:**
- No server-side validation or anti-cheat
- Can't play across devices (mobile + desktop)
- No backup if localStorage quota exceeded
- Migration becomes complex (need to validate client-provided state)
- Lost if browser data cleared

### Hybrid: Session + 7-Day TTL with Backup

**Approach:**
- Use session ID as primary identifier
- Extend session TTL to 7 days for anonymous users
- Optional: localStorage backup for quick recovery within TTL
- Warning upfront: "Progress saved for 7 days"

**Pros:**
- Balances privacy with UX
- Clear expectations communicated to users
- Backup provides some resilience
- Leverages existing session infrastructure

**Cons:**
- Still expires (user might return after 8 days)
- Requires extended session configuration
- Backup adds complexity
- Not truly persistent

---

## Trade-off 3: Registration Security (2FA vs. Simple Email/Password)

### Full Security (Email + Password + 2FA)

**Approach:**
- Use Laravel Fortify with 2FA enabled by default
- Email verification required before account activation
- Two-factor authentication using authenticator app or SMS

**Pros:**
- Best practice security for account protection
- Prevents unauthorized access to game progress
- Already configured in existing codebase
- Professional security posture

**Cons:**
- High friction onboarding
- 2FA adds barrier to registration incentive
- Email verification loses users in the moment of decision
- Overkill for simulated game (virtual currency, no real money)
- Technical support overhead for 2FA recovery

### Simple Registration (Email + Password Only)

**Approach:**
- Standard email/password with basic validation
- No 2FA requirement
- No email verification before gameplay
- Optional password reset via email verification

**Pros:**
- Lowest friction: immediate access to register incentive
- Aligns with low-stakes nature of game
- Reduces abandonment during registration flow
- Simpler support requests
- Faster development iteration

**Cons:**
- Reduced account security
- Vulnerable to credential stuffing attacks
- No protection if email account compromised
- Perceived as "less professional"

### Middle Ground: Progressive Security

**Approach:**
- Simple registration initially
- 2FA optional, can enable in settings
- Email verification only for password reset
- Prompt for 2FA after X days or when cash threshold reached

**Pros:**
- Meets users where they are
- Security scales with engagement/asset value
- No barrier to entry
- Power users can opt-in to security

**Cons:**
- Complex logic for security requirements
- UX complexity: when to prompt?
- Some accounts never secure themselves
- Still vulnerable initially

### Email-less Registration

**Approach:**
- Generate username/password locally
- Store on device only
- Registration = create account with anonymous state

**Pros:**
- Zero friction: no email entry
- Privacy-preserving: no personal data
- Instant registration completion

**Cons:**
- No recovery path: lost device = lost account
- No cross-device play
- Can't merge accounts
- Potential for duplicate usernames

---

## Trade-off 4: Game Data References (GameState ID vs. User ID)

### Reference GameState

**Approach:**
- Orders, transfers, policies, quests reference `game_state_id`
- User ID exists only in `game_states` table
- Game data decoupled from identity

**Pros:**
- Zero-impact migration: anonymous-to-registered only updates `game_states.user_id`
- All game data automatically follows during migration
- Supports multiple game sessions per user (sandbox mode)
- Clean separation: identity vs. gameplay
- Enables features like "save slots" or "restart game"

**Cons:**
- Queries require join for user-level operations (e.g., "user's total orders")
- Leaderboards require grouping by `user_id` through join
- Can't directly query "all orders for user X"
- Cache complexity: user identity not readily available

### Reference User

**Approach:**
- Orders, transfers, policies reference `user_id`
- `user_id` nullable for anonymous, populated for registered
- Migration updates `user_id` in all related tables

**Pros:**
- Direct queries: `Order::where('user_id', $userId)` works
- Simpler user-level aggregations
- Standard pattern for user-owned resources
- Better query performance for user-scoped operations

**Cons:**
- Migration nightmare: must update 15+ tables atomically
- Transaction complexity: one failure breaks all references
- Can't easily support multiple game sessions
- User account merging becomes impossible
- Backfill logic required for anonymous data

### Hybrid: Both References

**Approach:**
- Tables reference both `user_id` (nullable) and `game_state_id`
- User ID populated during registration
- Backfilled via migration

**Pros:**
- Flexibility for both query patterns
- Migration can be staged
- Performance optimization possible

**Cons:**
- Redundant data (denormalization risk)
- Complex validation: which field to trust?
- Storage overhead
- Inconsistent states possible if sync fails

---

## Trade-off 5: Auto-Save Frequency

### Immediate Save (Every Action)

**Approach:**
- Trigger save after every mutation: order, transfer, time advance, quest complete
- Write to database on each action completion
- User feedback: "Saving..." indicator on each action

**Pros:**
- Maximum data retention: never loses progress
- Users expect this behavior from modern games
- Clear feedback loop
- Recovery from crashes: last action always saved
- Simple to reason about: action = save

**Cons:**
- Database write load: potentially thousands per minute
- Latency on actions: waiting for save completion
- Network dependency: offline play impossible
- Write amplification: single logical action might trigger multiple saves
- Database hotspots on `game_states.updated_at` index

### Debounced Save (Throttled)

**Approach:**
- Queue save operations, batch every 30 seconds
- Save immediately on critical actions (orders, transfers)
- Debounce non-critical changes (XP, cash)

**Pros:**
- Reduced database load: batched writes
- Better perceived performance: UI not blocked
- Opportunity for intelligent batching
- Lower cost at scale

**Cons:**
- Data loss on crashes: up to 30 seconds lost
- Complex logic: what's critical vs. non-critical?
- State synchronization issues: user action + pending save
- Harder to reason about current state

### Manual Save Only

**Approach:**
- User clicks "Save Game" button
- Optional auto-save before risky actions
- Warning on exit: "Unsaved changes"

**Pros:**
- Complete user control
- Zero database load until user chooses
- Classic RPG game feel

**Cons:**
- Users forget: rage-quit without saving
- Blame attribution: users fault game for data loss
- Poor UX for casual players
- Lost progress on browser crashes

### Hybrid: Critical + Idle Saves

**Approach:**
- Critical mutations (orders, transfers, registration) save immediately
- Progress changes (XP, cash, time) save debounced
- Idle heartbeat save every 5 minutes of inactivity
- Save on page unload (beforeunload event)

**Pros:**
- Best of both worlds: critical safety + reduced load
- Intelligent write distribution
- Protection against crash (heartbeat)
- Good performance: no blocking saves on minor changes

**Cons:**
- Complexity: categorizing actions
- Race conditions: multiple save sources
- beforeunload events unreliable (browser differences)

---

## Trade-off 6: Anonymous Session Expiration

### Hard Delete (Immediate Purge)

**Approach:**
- Session expires → `game_states` row deleted immediately
- No retention of anonymous data
- User returns → completely fresh game

**Pros:**
- Clean database: no orphaned records
- Privacy: no lingering anonymous data
- Simple implementation
- Storage cost control

**Cons:**
- Poor UX: even 1 hour away loses everything
- Users abandon after one bad experience
- No recovery from accidental logout

### 7-Day TTL Then Delete

**Approach:**
- Extend anonymous session to 7 days
- Soft-delete after TTL: mark for deletion
- Hard-delete after 30 days or via cron
- Warning to user on session expiry

**Pros:**
- Reasonable retention for casual players
- Time to return before data loss
- Clear expectations
- Database cleanup via automated job

**Cons:**
- Storage cost: 7 days of data retained
- Users still lose after 7 days (churn risk)
- Complexity: tracking TTL across sessions

### 30-Day Soft Delete

**Approach:**
- Soft-delete after session expires
- Move to `archived_game_states` table
- Keep for 90 days, then hard delete
- Offer "Restore Previous Game" to returning anonymous players

**Pros:**
- Best UX: recovery window up to 90 days
- Reduced churn: recover lost players
- Analytics: can study anonymous behavior patterns

**Cons:**
- Significant storage cost
- Complex restoration logic
- Privacy concerns: retaining anonymous data long-term
- Database bloat from archived records
- GDPR implications for anonymous data retention

### No Expiration (Indefinite)

**Approach:**
- Anonymous states never expire
- Session ID reused indefinitely
- Cleanup only on explicit user action

**Pros:**
- Maximum UX: game always there
- No privacy concerns (anonymous by nature)
- Simple: no TTL logic

**Cons:**
- Database grows unbounded
- Performance degradation over time
- Stale sessions (months/years old)
- No way to differentiate active vs. abandoned
- Security risk: session hijack persists indefinitely

---

## Trade-off 7: Conflict Resolution Strategy

### Last-Write-Wins (LWW)

**Approach:**
- Server accepts any update, overwrites existing state
- Uses `updated_at` timestamp for ordering
- No conflict detection
- Silent overwrite of older data

**Pros:**
- Standard gaming industry approach
- Simple implementation: no conflict logic
- Always accepts user action
- No user-facing complexity
- Database handles ordering naturally

**Cons:**
- Silent data loss: user doesn't know what was overwritten
- Multi-tab play: last tab overwrites all others
- Race conditions undetected
- User confusion: "Where did my order go?"

### First-Write-Wins

**Approach:**
- Reject updates if `updated_at` is older than server state
- Return error to client with current state
- Client must merge or retry

**Pros:**
- No silent data loss
- User made aware of conflicts
- Prevents accidental overwrites
- Good for offline sync scenarios

**Cons:**
- Complex UI: conflict resolution required
- User frustration: action fails due to other session
- Need state comparison/merge logic
- May prevent legitimate actions

### Version Numbering (Optimistic Locking)

**Approach:**
- Each state has incrementing version number
- Client sends version with update
- Server rejects if version mismatch
- Retry with versioned merge

**Pros:**
- Detects all conflicts
- Prevents race conditions
- Supports offline editing
- Clear failure mode

**Cons:**
- Requires client-side version tracking
- Merge logic complexity
- UX challenge: what to do on version mismatch?
- Potential for retry loops

### Conflict Prompt

**Approach:**
- Detect concurrent modifications
- Show user both versions
- User chooses which to keep or how to merge
- Similar to Google Docs conflict resolution

**Pros:**
- User control over conflict outcome
- No silent data loss
- Educational: users learn about concurrent play

**Cons:**
- UI complexity: comparing states is non-trivial
- User confusion: game-like experience broken by prompts
- Edge cases: how to merge game state?
- Adds cognitive load

---

## Trade-off 8: State Delivery (Inertia Props vs. LocalStorage vs. Hybrid)

### Server-Side Only (Inertia Props)

**Approach:**
- Load state from database on every page navigation
- Pass via Inertia middleware: `HandleInertiaRequests`
- Frontend is pure view layer
- No localStorage caching

**Pros:**
- Single source of truth: server
- No sync issues (server always wins)
- Simplifies frontend: no state management
- Works across devices (mobile + desktop)
- Server events (spikes, orders) immediately visible

**Cons:**
- Round-trip latency on every navigation
- Database load: queries on each page load
- No offline support
- Server dependency: can't play if API down

### Client-Side First (LocalStorage)

**Approach:**
- Initial load from server via Inertia
- Subsequent mutations stored in localStorage
- Background sync to server
- Server treated as backup/cloud save

**Pros:**
- Instant UI: no network latency
- Offline play: continue without connection
- Reduced server load: batched sync
- Progressive Web App (PWA) ready

**Cons:**
- Sync complexity: conflict resolution required
- State divergence: localStorage vs. server
- Cache invalidation: when to refetch?
- Multi-device issues: each device has different state
- Storage quota limits

### Hybrid: Inertia Props + Selective LocalStorage

**Approach:**
- Core state (cash, XP, day) from server via Inertia
- UI state (scroll position, filters, selections) in localStorage
- Server is authoritative
- Revalidate on critical actions

**Pros:**
- Best of both: accurate state + good UX
- Simplifies sync: only non-critical data local
- Reduces server load (UI state not persisted)
- Cross-device consistency for game state

**Cons:**
- Still requires classification: what's critical vs. UI?
- Some UX data lost on device switch
- Complexity in deciding what goes where

---

## Open Questions & Decision Points

### Device vs. Browser Scope
- **Question**: Should anonymous sessions be tied to browser (cookies) or device (fingerprint)?
- **Implication**: Browser allows same user on mobile + desktop as separate games. Device allows seamless continuation.
- **Trade-off**: Device is better UX but worse privacy and reliability.

### Recovery Mechanisms
- **Question**: What happens when anonymous user loses data?
- **Options**: Hard reset, "Register next time", offer one-time recovery window
- **Implication**: Sets user expectations and churn impact.

### Game Versioning
- **Question**: How to handle balance patches that invalidate old saves?
- **Approaches**: Migration scripts, version field on `game_states`, force reset for incompatible versions
- **Implication**: Affects long-term anonymous retention.

### Manual Saves / Save Slots
- **Question**: Should users have multiple save slots or autosave only?
- **Trade-off**: Save slots add complexity but allow experimentation. Autosave is simpler but no rollback.
- **Implication**: Impacts database schema and UI.

### Analytics vs. Privacy
- **Question**: How much anonymous data to retain for analytics?
- **Spectrum**: Delete immediately vs. anonymize and keep vs. full retention
- **Implication**: Legal compliance (GDPR) vs. product improvement.

### Offline Support
- **Question**: Should the game work without internet?
- **Options**: Fully offline, offline queue, online-only
- **Implication**: Major architectural difference if supporting offline play.

---

## Architectural Principles to Apply

### User Experience First
- Minimize friction for anonymous players
- Registration should feel like an upgrade, not a barrier
- Clear expectations about data retention

### Server as Source of Truth
- All game logic validated server-side
- Events and mutations trigger state changes
- Prevent client-side cheating

### Progressive Enhancement
- Start simple (anonymous session)
- Add features incrementally (registration, 2FA, cloud saves)
- Never block initial gameplay

### Graceful Degradation
- Offline scenarios handled gracefully
- Network errors don't lose progress
- Clear communication of system state

---

## Next Steps for Decision

Before implementation, resolve:

1. Anonymous retention policy (7 days? 30 days? indefinite?)
2. Registration security level (simple? 2FA? progressive?)
3. Save frequency (immediate? debounced? hybrid?)
4. Conflict resolution strategy (LWW? versioned?)
5. Offline support requirements (fully offline? online-only?)
6. Cross-device play for anonymous users (browser-scoped or device-scoped?)
