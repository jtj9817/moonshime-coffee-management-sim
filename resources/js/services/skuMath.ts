import { Supplier, SupplierItem, CostBreakdown, Item, PriceTier, BulkTierAnalysis, LandedCostBreakdown } from '../types';

// Z-Score approximation map for common service levels
export const getZScore = (serviceLevel: number): number => {
  if (serviceLevel >= 0.999) return 3.09;
  if (serviceLevel >= 0.99) return 2.33;
  if (serviceLevel >= 0.98) return 2.05;
  if (serviceLevel >= 0.95) return 1.645;
  if (serviceLevel >= 0.90) return 1.28;
  if (serviceLevel >= 0.85) return 1.04;
  if (serviceLevel >= 0.80) return 0.84;
  return 0.0;
};

export const calculateSafetyStock = (
  dailyUsageStdDev: number,
  avgLeadTime: number,
  leadTimeStdDev: number,
  avgDailyUsage: number,
  zScore: number
): number => {
  // Formula: Z * sqrt( (AvgLeadTime * StdDevDemand^2) + (AvgDemand^2 * StdDevLeadTime^2) )
  const demandVariance = Math.pow(dailyUsageStdDev, 2);
  const leadTimeVariance = Math.pow(leadTimeStdDev, 2);
  
  const combinedUncertainty = Math.sqrt(
    (avgLeadTime * demandVariance) + (Math.pow(avgDailyUsage, 2) * leadTimeVariance)
  );
  
  return Math.ceil(zScore * combinedUncertainty);
};

export const calculateROP = (
  avgDailyUsage: number,
  avgLeadTime: number,
  safetyStock: number
): number => {
  // Formula: (Demand * LeadTime) + SafetyStock
  return Math.ceil((avgDailyUsage * avgLeadTime) + safetyStock);
};

export const calculateDaysCover = (onHand: number, avgDailyUsage: number): number => {
  if (avgDailyUsage === 0) return 999;
  return parseFloat((onHand / avgDailyUsage).toFixed(1));
};

export const calcLandedCostPerUnit = (
  item: Item,
  supplier: Supplier,
  supplierItem: SupplierItem,
  orderQuantity: number = 100
): LandedCostBreakdown => {
  
  // 1. Determine Unit Price (Tiered)
  let unitPrice = supplierItem.pricePerUnit;
  if (supplierItem.priceTiers) {
    const applicableTier = supplierItem.priceTiers
        .sort((a,b) => b.minQty - a.minQty)
        .find(t => orderQuantity >= t.minQty);
    if (applicableTier) unitPrice = applicableTier.unitPrice;
  }

  // 2. Shipping Cost
  // Logic: Flat rate applies unless threshold met
  const totalOrderValue = unitPrice * orderQuantity;
  const isFreeShipping = totalOrderValue >= supplier.freeShippingThreshold;
  const shippingTotal = isFreeShipping ? 0 : supplier.flatShippingRate;
  const shippingPerUnit = shippingTotal / orderQuantity;

  // 3. Duties & Tariffs (Mock: 5% for 'Global' supplier name, else 0)
  const isInternational = supplier.name.toLowerCase().includes('global');
  const dutiesPerUnit = isInternational ? unitPrice * 0.05 : 0;

  // 4. Handling (Fixed mock)
  const handlingPerUnit = 0.05; 

  // 5. Holding Cost (Amortized for one turn)
  // Simplified: storageCostPerUnit from item properties
  const holdingCost = item.storageCostPerUnit;

  // 6. Risk Premium
  const stockoutCostPerUnit = 5.0; // Margin loss estimate
  const stockoutRiskCost = (1 - supplier.reliability) * stockoutCostPerUnit;

  const totalPerUnit = unitPrice + shippingPerUnit + dutiesPerUnit + handlingPerUnit + holdingCost + stockoutRiskCost;

  return {
    supplierId: supplier.id,
    supplierName: supplier.name,
    unitPrice,
    deliveryFeePerUnit: parseFloat(shippingPerUnit.toFixed(2)),
    dutiesPerUnit: parseFloat(dutiesPerUnit.toFixed(2)),
    handlingPerUnit,
    holdingCost,
    stockoutRiskCost: parseFloat(stockoutRiskCost.toFixed(2)),
    totalPerUnit: parseFloat(totalPerUnit.toFixed(2)),
    isBestValue: false,
    currency: 'USD'
  };
};

// Deprecated wrapper for backwards compatibility, using new logic
export const calculateVendorTCO = (
  item: Item,
  supplier: Supplier,
  supplierItem: SupplierItem
): CostBreakdown => {
  return calcLandedCostPerUnit(item, supplier, supplierItem, supplierItem.minOrderQty);
};

export const evaluateBulkTierBreakeven = (
  currentTier: PriceTier,
  targetTier: PriceTier,
  item: Item,
  currentQty: number
): BulkTierAnalysis => {
  
  const unitSavings = currentTier.unitPrice - targetTier.unitPrice;
  const totalPurchaseSavings = unitSavings * targetTier.minQty; // Immediate cash savings on the goods
  
  // Cost of carrying extra inventory
  // Extra units = targetTier.minQty - currentQty (assuming we only need currentQty)
  const extraUnits = Math.max(0, targetTier.minQty - currentQty);
  
  // Holding cost for extra units over their consumption period
  // Time to consume extra = ExtraUnits / DailyUsage (Assumed 10 for calculation if unknown)
  // Simplified: Item.storageCostPerUnit * ExtraUnits * (TimeFactor)
  // Let's use Item.storageCostPerUnit as a monthly cost proxy
  const estimatedMonthsToConsumeExtra = extraUnits / 30; // Mock rate
  const incrementalHoldingCost = item.storageCostPerUnit * extraUnits * estimatedMonthsToConsumeExtra;
  
  const netBenefit = totalPurchaseSavings - incrementalHoldingCost;
  
  // Check storage constraint
  const exceedsStorage = targetTier.minQty > (item.bulkThreshold * 2); // Hard cap example
  
  let recommendation: 'UPGRADE_TIER' | 'STAY_CURRENT' | 'NOT_WORTH_IT' = 'NOT_WORTH_IT';
  
  if (exceedsStorage) {
      recommendation = 'NOT_WORTH_IT'; // Storage risk
  } else if (netBenefit > 50) { // Threshold for "worth it"
      recommendation = 'UPGRADE_TIER';
  } else {
      recommendation = 'STAY_CURRENT';
  }

  return {
    currentUnitCost: currentTier.unitPrice,
    targetUnitCost: targetTier.unitPrice,
    savingsAtTargetTier: totalPurchaseSavings,
    incrementalHoldingCost,
    netBenefit,
    breakevenQuantity: targetTier.minQty, // Simple for now
    recommendation
  };
};

export const generateMockForecast = (
  days: number,
  baseDemand: number,
  stdDev: number,
  seasonalityMultiplier: number = 1.0
) => {
  const data = [];
  const now = new Date();
  
  for (let i = 0; i < days; i++) {
    const date = new Date(now);
    date.setDate(date.getDate() + i);
    
    // Day of week factor (Higher on weekends)
    const dow = date.getDay();
    const isWeekend = dow === 0 || dow === 6;
    const dowFactor = isWeekend ? 1.3 : 1.0;

    // Random noise
    const noise = (Math.random() - 0.5) * stdDev;

    const predicted = Math.max(0, Math.floor((baseDemand * dowFactor * seasonalityMultiplier) + noise));
    
    data.push({
      date: date.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' }),
      baseDemand,
      seasonalMultiplier: seasonalityMultiplier,
      eventMultiplier: 1.0,
      predicted,
      upperBound: predicted + (stdDev * 1.5),
      lowerBound: Math.max(0, predicted - (stdDev * 1.5))
    });
  }
  return data;
};