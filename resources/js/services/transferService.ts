import { SUPPLIER_ITEMS } from '../constants';
import { InventoryRecord, Item, Location, TransferSuggestion, SupplierItem } from '../types';

import { calculateInventoryPositions } from './inventoryService';

const TRANSFER_FIXED_COST = 15.00; // e.g. Courier fee
const TRANSFER_UNIT_COST = 0.05; // Handling per unit
const TRANSFER_LEAD_TIME_DAYS = 1; // Transfers are fast

export const generateTransferSuggestions = (
  inventory: InventoryRecord[],
  items: Item[],
  locations: Location[]
): TransferSuggestion[] => {
  const suggestions: TransferSuggestion[] = [];
  
  // Get positions with calculated ROP/SafetyStock
  const positions = calculateInventoryPositions(inventory, items, locations);

  // Pre-calculate total inventory per location to check storage constraints
  const locationStorageUsage: Record<string, number> = {};
  inventory.forEach(r => {
    locationStorageUsage[r.locationId] = (locationStorageUsage[r.locationId] || 0) + r.quantity;
  });

  // Identify Receivers (Needs Stock)
  // Logic: OnHand < ReorderPoint
  const receivers = positions.filter(p => p.onHand <= p.reorderPoint);

  receivers.forEach(receiver => {
    // Determine needed qty to get back to healthy level (e.g. ROP + 20%)
    let neededQty = Math.ceil(receiver.reorderPoint * 1.2) - receiver.onHand;
    if (neededQty <= 0) return;

    // Check Target Storage Capacity
    const targetLoc = locations.find(l => l.id === receiver.locationId);
    if (targetLoc) {
        const currentUsage = locationStorageUsage[receiver.locationId] || 0;
        const availableSpace = targetLoc.maxStorage - currentUsage;
        
        if (availableSpace <= 0) return; // Target is full
        
        if (neededQty > availableSpace) {
            neededQty = availableSpace; // Cap transfer quantity to fit available storage
        }
    }

    // Find best Supplier cost for comparison
    const supplierItems = SUPPLIER_ITEMS.filter(si => si.itemId === receiver.skuId);
    if (supplierItems.length === 0) return;
    
    // Simplification: Average supplier price + shipping amortized (roughly)
    const avgSupplierPrice = supplierItems.reduce((acc, curr) => acc + curr.pricePerUnit, 0) / supplierItems.length;
    const avgSupplierLeadTime = supplierItems.reduce((acc, curr) => acc + curr.deliveryDays, 0) / supplierItems.length;
    
    // Estimate Total Order Cost
    const supplierOrderCost = (avgSupplierPrice * neededQty) + 10; // +$10 flat shipping estimate

    // Find Donors (Has Excess)
    // Logic: 
    // 1. OnHand > SafetyStock + NeededQty (Ensure donor stays safe)
    // 2. OnHand >= BulkThreshold (Only transfer if location has reached bulk storage levels)
    const potentialDonors = positions.filter(p => 
      p.skuId === receiver.skuId && 
      p.locationId !== receiver.locationId &&
      p.onHand > (p.safetyStock + neededQty) &&
      p.onHand >= p.item.bulkThreshold
    );

    potentialDonors.forEach(donor => {
      // Calculate Transfer Cost Breakdown
      const fixedCost = TRANSFER_FIXED_COST;
      const handlingCost = neededQty * TRANSFER_UNIT_COST;
      const transferCost = fixedCost + handlingCost;
      
      const savings = supplierOrderCost - transferCost;
      const timeSaved = Math.max(0, avgSupplierLeadTime - TRANSFER_LEAD_TIME_DAYS);

      // Only suggest if it makes sense financially or critically (time saved)
      if (savings > 0 || (timeSaved >= 2 && receiver.onHand === 0)) {
         suggestions.push({
           id: `sug-${donor.locationId}-${receiver.locationId}-${receiver.skuId}`,
           skuId: receiver.skuId,
           sourceLocationId: donor.locationId,
           targetLocationId: receiver.locationId,
           qty: neededQty,
           transferCost,
           transferCostBreakdown: {
             fixed: fixedCost,
             handling: handlingCost
           },
           supplierCost: supplierOrderCost,
           savings: parseFloat(savings.toFixed(2)),
           timeSavedDays: parseFloat(timeSaved.toFixed(1)),
           reason: savings > 0 
             ? `Save $${savings.toFixed(0)} vs ordering`
             : `Prevent stockout ${timeSaved.toFixed(0)} days faster`,
           feasible: true
         });
      }
    });
  });

  return suggestions.sort((a, b) => b.savings - a.savings);
};