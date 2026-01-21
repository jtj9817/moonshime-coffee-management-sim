# Game Mechanics

## Overview

Moonshine Coffee Management Sim is a **turn-based supply chain management game** where players manage inventory, orders, and logistics across three coffee shop locations. The core gameplay loop revolves around making strategic decisions to maintain inventory levels while managing limited resources.

## Core Gameplay Loop

```
┌────────────────────────────────────────────────────┐
│                                                    │
│  1. Assess Situation                               │
│     - Review inventory levels                      │
│     - Check active alerts                          │
│     - Review pending orders/transfers              │
│                                                    │
├────────────────────────────────────────────────────┤
│                                                    │
│  2. Make Decisions                                 │
│     - Place vendor orders                          │
│     - Initiate internal transfers                  │
│     - Adjust inventory policies                    │
│     - Respond to spike events                      │
│                                                    │
├────────────────────────────────────────────────────┤
│                                                    │
│  3. Advance Time                                   │
│     - Click "Advance Day" button                   │
│     - System processes all pending actions         │
│     - Events trigger and resolve                   │
│                                                    │
└────────────────────────────────────────────────────┘
                        │
                        └─────► (Loop back to step 1)
```

## Game Initialization

### Starting State

**Resources**:
- Cash: $5,000
- Day: 0
- Reputation: 50/100

**Locations**:
```
1. Moonshine HQ (Roastery)
   - Type: Production facility
   - Storage: 5,000 units
   - Role: Central warehouse

2. Uptown Kiosk
   - Type: Retail kiosk
   - Storage: 500 units
   - Role: Small high-traffic location

3. Lakeside Cafe
   - Type: Full cafe
   - Storage: 1,200 units
   - Role: Medium-sized retail location
```

**Initial Inventory**:
Each location starts with some products:
- HQ: Well-stocked with beans, cups, tea, mocha sauce
- Kiosk: Low on beans, oat milk, pumpkin spice, food
- Cafe: Good on almond milk, low on cleaning supplies

### Setup Process

When a new game is initialized:

1. **Database Setup**:
   - Create locations (3 locations)
   - Create products (12 SKUs) - *See `config/game_data.php`*
   - Create vendors (4 suppliers)
   - Set up routes (6 bidirectional routes)
   - Seed initial inventory

2. **Game State Creation**:
   ```php
   GameState::create([
       'user_id' => $user->id,
       'cash' => 5000.00,
       'day' => 0,
       'reputation' => 50.00,
       'last_spike_day' => -1,
       'spike_count' => 0
   ]);
   ```

3. **Vendor-Product Relationships**:
   - Map products to vendors with pricing
   - Set minimum order quantities
   - Define delivery lead times
   - Configure bulk pricing tiers

## Time System

### Turn-Based Gameplay

The game operates on a **day-based system** where each turn represents one business day.

**Time Advancement**:
- Player initiates time progression by clicking "Advance Day"
- Cannot be undone
- All pending actions resolve immediately
- Events may trigger

**Day Counter**:
- Starts at Day 0
- Increments by 1 each turn
- Used for:
  - Order delivery scheduling
  - Transfer arrival timing
  - Spike event duration
  - Perishable expiry tracking

### Simulation Processing Order

When time advances, the following occurs in sequence:

1. **Process Deliveries**
   - Check all orders where `delivery_day <= current_day`
   - Update order status to "Delivered"
   - Add items to destination inventory
   - Process all transfers where `delivery_day <= current_day`
   - Update transfer status to "Completed"
   - Move items between locations

2. **Decay Perishables**
   - Reduce quantity of expired items
   - Calculate waste cost
   - Generate waste events
   - Update reputation

3. **Apply Storage Costs**
   - Calculate storage fees for inventory
   - Deduct from cash balance
   - Formula: `qty × storage_cost_per_unit`

4. **Generate Spike Events**
   - Random chance of spike occurrence
   - Respect cooldown periods
   - Apply spike constraints
   - Activate spike effects

5. **Update Metrics**
   - Recalculate KPIs
   - Generate new alerts
   - Update dashboard statistics

6. **Increment Day Counter**
   - `current_day += 1`
   - Save game state

## Resource Management

### Cash Management

**Starting Cash**: $5,000

**Cash Flow**:

**Inflows**:
- None currently (future: daily revenue simulation)

**Outflows**:
- Vendor orders (immediate deduction)
- Shipping costs (included in order total)
- Storage costs (daily, per unit)
- Transfer costs (immediate deduction)

**Bankruptcy**:
- If cash reaches $0, game displays warning
- Player cannot place orders
- Can only wait for existing deliveries or end game

**Cash Formula**:
```
New Cash = Current Cash - Order Cost - Storage Costs - Transfer Costs
```

### Storage Capacity

Each location has a **maximum storage limit**:

| Location | Capacity |
|----------|----------|
| HQ (Roastery) | 5,000 units |
| Uptown Kiosk | 500 units |
| Lakeside Cafe | 1,200 units |

**Capacity Checking**:
```
Current Usage = Sum of all inventory quantities at location
Available Space = Max Storage - Current Usage

Order Validation:
  Can order? → (Current Usage + Order Quantity) <= Max Storage
```

**Over-Capacity Penalties**:
- Orders that exceed capacity are rejected
- Validation occurs before order placement
- Frontend warns when approaching capacity

### Reputation System

**Reputation Score**: 0-100 scale

**Starting**: 50

**Factors Affecting Reputation**:

**Decreases**:
- Stockouts (-5 per incident)
- Excessive waste (-1 per waste event)
- Expired inventory (-3 per expiry)
- Order cancellations (-2 per cancellation)

**Increases**:
- Successfully handling spikes (+3)
- Efficient operations (+1 per perfect day)
- Low waste operations (+2 for zero waste weeks)

**Future Enhancements**:
- Reputation affects vendor pricing
- High reputation unlocks premium vendors
- Low reputation increases lead times

## Inventory Management

### SKU Categories

The game includes **12 products** across categories:

1. **Beans** (1 SKU): Espresso Blend
2. **Milk** (2 SKUs): Oat Milk, Almond Milk
3. **Cups** (1 SKU): 12oz Paper Cups
4. **Syrup** (1 SKU): Vanilla Syrup
5. **Tea** (1 SKU): Earl Grey Tea
6. **Sugar** (1 SKU): Raw Sugar
7. **Cleaning** (1 SKU): Sanitizer Spray
8. **Seasonal** (1 SKU): Pumpkin Spice Sauce
9. **Food** (1 SKU): Bacon Gouda Sandwich
10. **Sauce** (1 SKU): Dark Mocha Sauce
11. **Pastry** (1 SKU): Butter Croissant

### Perishability

**Perishable Items**:
- Oat Milk (21 days)
- Almond Milk (30 days)
- Pumpkin Spice Sauce (14 days)
- Bacon Gouda Sandwich (90 days, frozen)
- Dark Mocha Sauce (30 days)
- Butter Croissant (3 days)

**Non-Perishable**:
- Beans, Cups, Syrup, Tea, Sugar, Cleaning supplies

**Expiry Mechanics**:
- Each inventory record tracks `expiry_date`
- On time advancement, expired items are removed
- Waste cost calculated and deducted from cash
- Reputation decreases

### Daily Usage Simulation

**Current State**: Daily usage is **simulated/estimated** based on:
- Location type and size
- Historical patterns
- Seasonal factors

**Usage Rates** (example):
```
Espresso Beans:
  - HQ: 0 (storage only)
  - Kiosk: 5 kg/day
  - Cafe: 15 kg/day

Oat Milk:
  - Kiosk: 3 L/day
  - Cafe: 8 L/day
```

**Future Enhancement**:
Implement actual consumption simulation where inventory decreases daily based on:
- Customer traffic
- Menu mix
- Seasonal demand
- Random variance

## Vendor System

### Vendor Profiles

**BeanCo Global**:
- Specialty: Beans, Cups, Tea, Sauce
- Reliability: 95%
- Speed: Standard (3 days)
- Shipping: Free over $500, else $25
- Pricing: Premium, volume discounts available

**RapidSupplies**:
- Specialty: Cups, Syrup, Cleaning, Food
- Reliability: 85%
- Speed: Fast (1 day)
- Shipping: Free over $200, else $15
- Pricing: Higher prices for speed

**Dairy Direct**:
- Specialty: Milk (Oat, Almond)
- Reliability: 98%
- Speed: Fast (1 day)
- Shipping: Free over $150, else $10
- Pricing: Competitive for dairy/alternatives

**ValueBulk**:
- Specialty: Beans, Cups, Syrup, Sugar, Seasonal
- Reliability: 70%
- Speed: Slow (7 days)
- Shipping: Free over $1,000, else $40
- Pricing: Cheapest, but risky

### Ordering Mechanics

**Order Placement**:
1. Player selects vendor
2. Adds items to order
3. System calculates:
   - Subtotal (items)
   - Shipping cost
   - Total cost
4. Validates:
   - Sufficient cash
   - Destination capacity
   - Minimum order quantities
5. Deducts cash immediately
6. Creates order with status "Pending"

**Order Processing**:
```
Day 0: Order placed → Status: "Pending"
Day 0 + lead time: Order ships → Status: "Shipped"
Day 0 + lead time + transit: Order arrives → Status: "Delivered"
```

**Multi-Hop Orders**:
For destinations not directly served by vendors:
- System finds optimal route
- Creates multiple shipment legs
- Each leg has its own transit time
- Total delivery = sum of all leg durations

## Transfer System

### Internal Transfers

Players can move inventory between their own locations.

**Transfer Mechanics**:
1. Select source and destination locations
2. Choose product and quantity
3. System calculates:
   - Transfer cost (route-based)
   - Transit time
   - Arrival day
4. Validates:
   - Source has sufficient inventory
   - Destination has capacity
5. Deducts cost immediately
6. Reduces source inventory
7. Increases destination inventory on arrival day

**Transfer vs. Order Decision**:
- **Transfer**: Fast, predictable, good for rebalancing
- **Order**: Brings in new inventory, vendor-dependent

**Use Cases**:
- Emergency restocking from HQ
- Redistributing excess inventory
- Consolidating before an order
- Preparing for predicted demand

## Spike Events

### Event Types

**1. Demand Spike**
- **Effect**: Increases daily usage at a location
- **Duration**: 2-5 days
- **Multiplier**: 1.5x - 3x
- **Impact**: Accelerates stockouts

**2. Vendor Delay**
- **Effect**: Extends delivery times for a vendor
- **Duration**: 3-7 days
- **Delay**: +2 to +4 days
- **Impact**: Disrupts supply timing

**3. Price Spike**
- **Effect**: Increases product costs from a vendor
- **Duration**: 3-5 days
- **Multiplier**: 1.3x - 2x
- **Impact**: Reduces profit margins

**4. Equipment Breakdown**
- **Effect**: Reduces storage capacity at a location
- **Duration**: 2-4 days
- **Reduction**: 20% - 40%
- **Impact**: Forces inventory reduction

**5. Blizzard/Weather**
- **Effect**: Delays all shipments on affected routes
- **Duration**: 1-3 days
- **Delay**: +1 to +2 days per leg
- **Impact**: Widespread delivery delays

### Event Generation

**Trigger Conditions**:
- Random chance each day
- Cooldown period between events (minimum 3 days)
- Maximum 1 active spike per category
- Respects dependency constraints (blizzard blocks other spikes)

**Player Response**:
- React by placing emergency orders
- Use expedited transfers
- Cancel affected orders
- Wait out the event

**Event Resolution**:
- Events auto-resolve after duration
- Effects are rolled back
- Alerts notify player of end

## Win/Lose Conditions

### Success Metrics

The game is **open-ended** with no explicit win condition, but success is measured by:

1. **Financial Health**
   - Positive cash balance
   - Growing reserves
   - Efficient spending

2. **Operational Excellence**
   - Minimal stockouts
   - Low waste percentage
   - Optimal inventory levels

3. **Reputation**
   - High reputation score (70+)
   - Customer satisfaction
   - Vendor relationships

4. **Resilience**
   - Successfully navigating spike events
   - Quick recovery from disruptions

### Failure States

**Bankruptcy**:
- Cash reaches $0
- Cannot place orders
- Game over message displayed

**Critical Stockouts**:
- Running out of essential items
- Reputation drop below 20
- Warning messages displayed

**Excessive Waste**:
- Consistent over-ordering
- High perishable waste
- Reputation penalty

### Scoring (Future)

Potential scoring system:
```
Score = (Cash × 1) + (Reputation × 100) - (Total Waste × 10) + (Days Survived × 5)
```

## Tutorial & Onboarding

### First-Time Player Experience

**Initial State**:
- Pre-populated with some inventory
- Some locations low on stock (triggers alerts)
- Starting cash sufficient for several orders

**Guided Steps**:
1. **Dashboard Tour**: Explain KPIs and alerts
2. **Inventory Review**: Show inventory table and status indicators
3. **Place First Order**: Walk through order dialog
4. **Advance Time**: Explain simulation and time advancement
5. **Review Results**: Show how deliveries update inventory

**Learning Objectives**:
- Understand resource constraints
- Learn ordering process
- Grasp time-based mechanics
- Recognize alerts and risks

## Future Enhancements

### Revenue Simulation
- Daily customer sales
- Revenue based on inventory availability
- Profit calculation

### Dynamic Demand
- Seasonal patterns
- Day-of-week variations
- Special events (holidays, promotions)

### Staff Management
- Hire/fire employees
- Wages as operational cost
- Efficiency bonuses

### Location Expansion
- Open new locations
- Upgrade existing locations
- Increase storage capacity

### Advanced Vendors
- Negotiate contracts
- Volume discounts
- Exclusive partnerships

### Multiplayer (Future)
- Compete with other players
- Leaderboards
- Shared supply chain challenges

## Related Documentation

- [Inventory System](./02-inventory-system.md)
- [Supply Chain](./03-supply-chain.md)
- [Spike Events](./05-spike-events.md)
