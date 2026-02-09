import { SUPPLIER_ITEMS } from '../constants';
import {
    Item,
    Location,
    PolicyChangeLog,
    WasteEvent,
    WasteReason,
} from '../types';

// Helper to get unit price
const getUnitPrice = (itemId: string): number => {
    const si = SUPPLIER_ITEMS.find((i) => i.itemId === itemId);
    return si ? si.pricePerUnit : 10;
};

export const generateMockWasteData = (
    items: Item[],
    locations: Location[],
    startDate: Date,
    endDate: Date,
): WasteEvent[] => {
    const events: WasteEvent[] = [];
    const oneDay = 24 * 60 * 60 * 1000;
    const diffDays = Math.round(
        Math.abs((endDate.getTime() - startDate.getTime()) / oneDay),
    );

    // Focus on perishable items
    const perishables = items.filter((i) => i.isPerishable);
    const others = items.filter((i) => !i.isPerishable);

    for (let i = 0; i < diffDays; i++) {
        const currentDate = new Date(startDate.getTime() + i * oneDay);
        const dateStr = currentDate.toISOString().split('T')[0];

        // 40% chance of waste occurring on any given day system-wide
        if (Math.random() < 0.4) {
            const isPerishable = Math.random() < 0.8; // 80% of waste is perishable
            const itemPool = isPerishable ? perishables : others;

            if (itemPool.length === 0) continue;

            const item = itemPool[Math.floor(Math.random() * itemPool.length)];
            const loc = locations[Math.floor(Math.random() * locations.length)];
            const unitCost = getUnitPrice(item.id);

            // Weighted reasons
            const rand = Math.random();
            let reason: WasteReason = 'OTHER';
            if (isPerishable) {
                if (rand < 0.5) reason = 'EXPIRY';
                else if (rand < 0.8) reason = 'FORECAST_MISS';
                else reason = 'OVER_ORDER';
            } else {
                if (rand < 0.4)
                    reason = 'SUPPLIER_DELAY'; // Damaged in transit etc
                else reason = 'OTHER';
            }

            // Qty dependent on item cost/size
            const baseQty =
                item.category === 'Milk'
                    ? 5
                    : item.category === 'Pastry'
                      ? 12
                      : 2;
            const qty = Math.max(
                1,
                Math.floor(baseQty + Math.random() * baseQty),
            );

            events.push({
                id: `waste-${currentDate.getTime()}-${i}`,
                date: dateStr,
                skuId: item.id,
                locationId: loc.id,
                qty,
                unitCost,
                reason,
            });
        }
    }

    return events.sort(
        (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime(),
    );
};

export const generateMockPolicyChanges = (
    items: Item[],
    locations: Location[],
    startDate: Date,
    endDate: Date,
): PolicyChangeLog[] => {
    const logs: PolicyChangeLog[] = [];
    const oneDay = 24 * 60 * 60 * 1000;
    const diffDays = Math.round(
        Math.abs((endDate.getTime() - startDate.getTime()) / oneDay),
    );

    const users = ['Alex Roaster', 'Sarah Supply', 'System Auto-Tuner'];

    for (let i = 0; i < diffDays; i++) {
        const currentDate = new Date(startDate.getTime() + i * oneDay);

        // Changes are rarer than waste. 10% chance per day.
        if (Math.random() < 0.1) {
            const item = items[Math.floor(Math.random() * items.length)];
            const loc = locations[Math.floor(Math.random() * locations.length)];
            const user = users[Math.floor(Math.random() * users.length)];

            const rand = Math.random();
            let changeType: PolicyChangeLog['changeType'] = 'SAFETY_STOCK';
            let reason = 'Optimizing for waste reduction';
            let oldV: string | number = 10;
            let newV: string | number = 12;

            if (rand < 0.4) {
                changeType = 'REORDER_POINT';
                oldV = Math.floor(Math.random() * 50) + 20;
                newV = Math.floor(oldV * (Math.random() > 0.5 ? 1.1 : 0.9));
                reason = 'Adjusted based on recent forecast variance';
            } else if (rand < 0.7) {
                changeType = 'SAFETY_STOCK';
                oldV = Math.floor(Math.random() * 20) + 5;
                newV = Math.floor(oldV * 1.2);
                reason = 'Increased buffer for holiday season';
            } else if (rand < 0.9) {
                changeType = 'SUPPLIER_CHANGE';
                oldV = 'Standard';
                newV = 'Fast';
                reason = 'Switched delivery tier to reduce lead time';
            } else {
                changeType = 'TRANSFER_RULE';
                oldV = 'Manual';
                newV = 'Auto < 50%';
                reason = 'Automated transfer triggers for low stock';
            }

            logs.push({
                id: `pol-${currentDate.getTime()}-${i}`,
                date: currentDate.toISOString().split('T')[0],
                user,
                skuId: item.id,
                locationId: loc.id,
                changeType,
                oldValue: oldV,
                newValue: newV,
                reason,
                impactMetric:
                    Math.random() > 0.5
                        ? 'Projected Savings: $45/mo'
                        : undefined,
            });
        }
    }

    return logs.sort(
        (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime(),
    );
};
