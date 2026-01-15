import { Item, PerishableOrderLimit } from '../types';

export const calcMaxPerishableOrder = (
  item: Item,
  dailyDemandRate: number,
  currentOnHand: number,
  leadTimeDays: number
): PerishableOrderLimit => {
  
  if (!item.isPerishable) {
    return {
      maxOrderQty: 9999,
      rationale: "Item is non-perishable.",
      riskAssessment: { wasteRisk: 0, stockoutRisk: 0 }
    };
  }

  // 1. Calculate Usable Shelf Life
  // We lose leadTimeDays while it ships
  const usableShelfLife = Math.max(0, item.estimatedShelfLife - leadTimeDays);
  
  if (usableShelfLife <= 0) {
      return {
          maxOrderQty: 0,
          rationale: `Lead time (${leadTimeDays}d) exceeds shelf life (${item.estimatedShelfLife}d).`,
          riskAssessment: { wasteRisk: 1, stockoutRisk: 1 }
      };
  }

  // 2. Calculate Sell-Through Capacity
  // How much can we sell before it rots?
  const maxSellable = dailyDemandRate * usableShelfLife;

  // 3. Subtract Current Stock (First In First Out)
  // Assuming current stock is fresher or handled separately, but conservative estimate:
  // If we order maxSellable, and we have current stock, we might waste the new stuff if demand drops.
  // Let's cap the NEW order at maxSellable.
  
  // 4. Safety Buffer (Reduce max by 20% to account for demand variance)
  const safeMaxOrder = Math.floor(maxSellable * 0.8);

  const wasteRisk = safeMaxOrder > (dailyDemandRate * usableShelfLife * 0.5) ? 0.5 : 0.1;

  return {
    maxOrderQty: safeMaxOrder,
    rationale: `Max ${safeMaxOrder} units to sell within ${usableShelfLife} usable days (@${dailyDemandRate}/day).`,
    riskAssessment: {
        wasteRisk,
        stockoutRisk: safeMaxOrder < dailyDemandRate ? 0.8 : 0.1
    }
  };
};
