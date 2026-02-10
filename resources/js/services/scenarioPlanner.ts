export type ScenarioRiskLevel = 'low' | 'medium' | 'stockout';

export interface ForecastProjectionRow {
    day_offset: number;
    predicted_demand: number;
    predicted_stock: number;
    risk_level: ScenarioRiskLevel;
    incoming_deliveries: number;
}

export interface ForecastProjectionInput {
    currentStock: number;
    baselineDailyDemand: number;
    horizonDays?: number;
    incomingDeliveriesByOffset?: Record<number, number>;
    demandMultipliersByOffset?: Record<number, number>;
}

export interface ScenarioPlanInput {
    currentStock: number;
    dailyDemand: number;
    leadTimeDays: number;
    reorderPoint: number;
    targetCoverageDays: number;
    incomingDeliveriesByOffset?: Record<number, number>;
    demandMultipliersByOffset?: Record<number, number>;
}

export interface ScenarioPlanResult {
    timeToStockoutDays: number | null;
    recommendedOrderQuantity: number;
    shouldReorderNow: boolean;
    reorderByDay: number | null;
    projection: ForecastProjectionRow[];
}

const normalizeInt = (value: number): number => Math.max(0, Math.round(value));

export const calculateRiskLevel = (
    predictedStock: number,
    predictedDemand: number,
): ScenarioRiskLevel => {
    if (predictedStock <= 0) {
        return 'stockout';
    }

    if (predictedStock < predictedDemand * 2) {
        return 'medium';
    }

    return 'low';
};

export const buildForecastProjection = ({
    currentStock,
    baselineDailyDemand,
    horizonDays = 7,
    incomingDeliveriesByOffset = {},
    demandMultipliersByOffset = {},
}: ForecastProjectionInput): ForecastProjectionRow[] => {
    const normalizedHorizon = Math.max(1, Math.round(horizonDays));
    const normalizedStock = normalizeInt(currentStock);
    const normalizedDemand = normalizeInt(baselineDailyDemand);

    const projection: ForecastProjectionRow[] = [];
    let runningStock = normalizedStock;

    for (let dayOffset = 1; dayOffset <= normalizedHorizon; dayOffset++) {
        const multiplier = demandMultipliersByOffset[dayOffset] ?? 1;
        const predictedDemand = normalizeInt(normalizedDemand * multiplier);
        const incoming = normalizeInt(
            incomingDeliveriesByOffset[dayOffset] ?? 0,
        );

        runningStock = Math.max(0, runningStock + incoming - predictedDemand);

        projection.push({
            day_offset: dayOffset,
            predicted_demand: predictedDemand,
            predicted_stock: runningStock,
            risk_level: calculateRiskLevel(runningStock, predictedDemand),
            incoming_deliveries: incoming,
        });
    }

    return projection;
};

export const calculateScenarioPlan = ({
    currentStock,
    dailyDemand,
    leadTimeDays,
    reorderPoint,
    targetCoverageDays,
    incomingDeliveriesByOffset = {},
    demandMultipliersByOffset = {},
}: ScenarioPlanInput): ScenarioPlanResult => {
    const normalizedLead = Math.max(1, Math.round(leadTimeDays));
    const normalizedCoverage = Math.max(1, Math.round(targetCoverageDays));
    const horizonDays = normalizedLead + normalizedCoverage;

    const projection = buildForecastProjection({
        currentStock,
        baselineDailyDemand: dailyDemand,
        horizonDays,
        incomingDeliveriesByOffset,
        demandMultipliersByOffset,
    });

    const stockoutRow =
        projection.find((row) => row.predicted_stock <= 0) ?? null;
    const timeToStockoutDays = stockoutRow?.day_offset ?? null;

    const totalDemand = projection.reduce(
        (sum, row) => sum + row.predicted_demand,
        0,
    );
    const totalIncoming = projection.reduce(
        (sum, row) => sum + row.incoming_deliveries,
        0,
    );
    const availableStock = normalizeInt(currentStock) + totalIncoming;

    const recommendedOrderQuantity = Math.max(0, totalDemand - availableStock);
    const shouldReorderNow =
        normalizeInt(currentStock) <= normalizeInt(reorderPoint) ||
        (timeToStockoutDays !== null && timeToStockoutDays <= normalizedLead);

    return {
        timeToStockoutDays,
        recommendedOrderQuantity,
        shouldReorderNow,
        reorderByDay:
            timeToStockoutDays === null
                ? null
                : Math.max(0, timeToStockoutDays - normalizedLead),
        projection,
    };
};
