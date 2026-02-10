import { describe, expect, it } from 'vitest';

import {
    buildForecastProjection,
    calculateScenarioPlan,
} from '@/services/scenarioPlanner';

describe('scenario planner calculations', () => {
    it('computes time-to-stockout using shared forecast projection math', () => {
        const projection = buildForecastProjection({
            currentStock: 25,
            baselineDailyDemand: 5,
            horizonDays: 10,
            incomingDeliveriesByOffset: {
                3: 10,
            },
        });

        expect(projection[0]?.predicted_stock).toBe(20);
        expect(projection[2]?.incoming_deliveries).toBe(10);

        const plan = calculateScenarioPlan({
            currentStock: 25,
            dailyDemand: 5,
            leadTimeDays: 2,
            reorderPoint: 8,
            targetCoverageDays: 7,
            incomingDeliveriesByOffset: {
                3: 10,
            },
        });

        expect(plan.timeToStockoutDays).toBe(7);
    });

    it('recommends reorder quantity to survive lead time plus coverage horizon', () => {
        const plan = calculateScenarioPlan({
            currentStock: 40,
            dailyDemand: 8,
            leadTimeDays: 3,
            reorderPoint: 12,
            targetCoverageDays: 10,
            incomingDeliveriesByOffset: {
                2: 4,
            },
        });

        expect(plan.recommendedOrderQuantity).toBe(60);
        expect(plan.shouldReorderNow).toBe(false);

        const urgentPlan = calculateScenarioPlan({
            currentStock: 8,
            dailyDemand: 8,
            leadTimeDays: 3,
            reorderPoint: 12,
            targetCoverageDays: 10,
        });

        expect(urgentPlan.shouldReorderNow).toBe(true);
    });
});
