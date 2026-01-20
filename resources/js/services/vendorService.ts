import { SUPPLIERS, SUPPLIER_ITEMS } from '../constants';
import { Supplier, SupplierItem, VendorScoreBreakdown } from '../types';

export interface ScoredVendor {
  vendor: Supplier;
  supplierItem: SupplierItem;
  score: number;
  breakdown: VendorScoreBreakdown;
}

export const chooseBestVendorGivenUrgency = (
  itemId: string,
  neededQuantity: number,
  urgencyLevel: 'critical' | 'high' | 'standard' | 'low'
): { selected: ScoredVendor | null; alternatives: ScoredVendor[] } => {
  
  const potentialSuppliers = SUPPLIER_ITEMS
    .filter(si => si.itemId === itemId)
    .map(si => {
      const vendor = SUPPLIERS.find(s => s.id === si.supplierId);
      return { supplierItem: si, vendor };
    })
    .filter(res => res.vendor !== undefined) as { supplierItem: SupplierItem, vendor: Supplier }[];

  if (potentialSuppliers.length === 0) return { selected: null, alternatives: [] };

  const scored: ScoredVendor[] = potentialSuppliers.map(({ vendor, supplierItem }) => {
     let priceScore = 0;
     let speedScore = 0;
     let reliabilityScore = 0;

     // Normalize Price (Lower is better)
     // Find global min/max for context? Simplified: 100 / price
     priceScore = (100 / supplierItem.pricePerUnit) * 10; 

     // Normalize Speed (Lower days is better)
     speedScore = (10 / Math.max(1, supplierItem.deliveryDays)) * 10;

     // Normalize Reliability
     reliabilityScore = vendor.reliability * 100;

     // Weights based on Urgency
     let wPrice = 1, wSpeed = 1, wReliability = 1;

     switch (urgencyLevel) {
         case 'critical':
             wPrice = 0.5; wSpeed = 4.0; wReliability = 3.0;
             break;
         case 'high':
             wPrice = 1.0; wSpeed = 2.5; wReliability = 2.0;
             break;
         case 'standard':
             wPrice = 2.0; wSpeed = 1.0; wReliability = 1.0;
             break;
         case 'low':
             wPrice = 4.0; wSpeed = 0.5; wReliability = 0.5;
             break;
     }

     const totalScore = (priceScore * wPrice) + (speedScore * wSpeed) + (reliabilityScore * wReliability);

     return {
         vendor,
         supplierItem,
         score: totalScore,
         breakdown: { priceScore, speedScore, reliabilityScore, totalScore }
     };
  });

  scored.sort((a, b) => b.score - a.score);

  return {
      selected: scored[0],
      alternatives: scored.slice(1)
  };
};
