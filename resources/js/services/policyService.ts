
import { SUPPLIER_ITEMS } from '../constants';
import { PolicyProfile, PolicyImpactAnalysis, InventoryRecord, Item, Location } from '../types';

import { calculateInventoryPositions } from './inventoryService';
import { getZScore, calculateSafetyStock } from './skuMath';

export const calculatePolicyImpact = (
  currentPolicy: PolicyProfile,
  newPolicy: PolicyProfile,
  inventory: InventoryRecord[],
  items: Item[],
  locations: Location[]
): PolicyImpactAnalysis => {
  
  // 1. Calculate positions based on CURRENT data (mocked usage stats inside inventoryService)
  const positions = calculateInventoryPositions(inventory, items, locations);
  
  let currentCapital = 0;
  let newCapital = 0;
  let currentHolding = 0;
  let newHolding = 0;

  positions.forEach(pos => {
    // Determine Cost Per Unit (Approximate using first supplier found or storage cost)
    // In a real app, this would use weighted average cost.
    const sItem = SUPPLIER_ITEMS.find(si => si.itemId === pos.skuId);
    const unitCost = sItem ? sItem.pricePerUnit : 10;
    
    // --- Current Policy Calculation ---
    const zScoreCurrent = getZScore(currentPolicy.globalServiceLevel);
    // StdDev assumptions for simulation (Mocked as 20% of daily usage)
    const demandStdDev = pos.dailyUsage * 0.2;
    const leadTimeStdDev = 1; // 1 day variation

    const ssCurrent = calculateSafetyStock(
      demandStdDev, pos.leadTimeDays, leadTimeStdDev, pos.dailyUsage, zScoreCurrent
    ) * (1 + currentPolicy.safetyStockBufferPct);

    // --- New Policy Calculation ---
    const zScoreNew = getZScore(newPolicy.globalServiceLevel);
    const ssNew = calculateSafetyStock(
      demandStdDev, pos.leadTimeDays, leadTimeStdDev, pos.dailyUsage, zScoreNew
    ) * (1 + newPolicy.safetyStockBufferPct);

    // Calculate Capital Required for Safety Stock
    currentCapital += ssCurrent * unitCost;
    newCapital += ssNew * unitCost;

    // Calculate Annual Holding Cost (Cost * Rate)
    currentHolding += (ssCurrent * unitCost) * currentPolicy.holdingCostRate;
    newHolding += (ssNew * unitCost) * newPolicy.holdingCostRate;
  });

  const deltaCapital = newCapital - currentCapital;
  const deltaHoldingCost = newHolding - currentHolding;
  
  // Projected Stockout Risk Score (Inverse of Service Level)
  // 0 is no risk, 100 is guaranteed stockout.
  // 0.99 SL = 1% risk score (approx)
  const projectedStockoutRisk = (1 - newPolicy.globalServiceLevel) * 100;

  return {
    projectedHoldingCost: newHolding,
    projectedStockoutRisk,
    capitalRequired: newCapital,
    deltaHoldingCost,
    deltaCapital
  };
};
