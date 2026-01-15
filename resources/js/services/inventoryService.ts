import { 
  InventoryRecord, 
  Item, 
  Location, 
  InventoryPosition, 
  InventoryStatus, 
  ExpiryLot,
  SupplierItem
} from '../types';
import { SUPPLIER_ITEMS } from '../constants';

// Helper to determine status based on metrics
const calculateStatus = (
  onHand: number,
  reorderPoint: number,
  expiryLots: ExpiryLot[]
): InventoryStatus => {
  // 1. Critical Expiry Risk
  const criticalExpiryQty = expiryLots
    .filter(l => l.riskLevel === 'critical')
    .reduce((sum, l) => sum + l.quantity, 0);
    
  if (criticalExpiryQty > 0 && criticalExpiryQty >= onHand * 0.5) {
    return {
      code: 'EXPIRY_RISK',
      riskScore: 90,
      explanation: `${criticalExpiryQty} units expiring within 2 days`,
      badgeColor: 'rose'
    };
  }

  // 2. Stockout Risk (Immediate)
  if (onHand === 0) {
    return {
      code: 'STOCKOUT_RISK',
      riskScore: 100,
      explanation: 'Currently out of stock',
      badgeColor: 'rose'
    };
  }

  // 3. Below Reorder Point
  if (onHand <= reorderPoint) {
    const severity = 1 - (onHand / reorderPoint); // 0 to 1
    return {
      code: 'BELOW_ROP',
      riskScore: 50 + (severity * 30), // 50-80 range
      explanation: `Below reorder point (${reorderPoint})`,
      badgeColor: 'amber'
    };
  }

  // 4. Healthy
  return {
    code: 'OK',
    riskScore: 0,
    explanation: 'Levels optimal',
    badgeColor: 'emerald'
  };
};

// Transform raw app data into rich InventoryPositions
export const calculateInventoryPositions = (
  records: InventoryRecord[],
  items: Item[],
  locations: Location[]
): InventoryPosition[] => {
  return records.map(record => {
    const item = items.find(i => i.id === record.itemId);
    const location = locations.find(l => l.id === record.locationId);

    if (!item || !location) return null;

    // Simulate usage & supply data (Mocking logic since no backend)
    // Deterministic simulation based on item ID char codes
    const seed = item.id.charCodeAt(item.id.length - 1) + location.id.charCodeAt(location.id.length - 1);
    const dailyUsage = Math.floor((seed % 10) + 2); // Random 2-11 units/day
    
    // Find fastest supplier lead time
    const relevantSuppliers = SUPPLIER_ITEMS.filter(si => si.itemId === item.id);
    const leadTimeDays = relevantSuppliers.length > 0 
      ? Math.min(...relevantSuppliers.map(s => s.deliveryDays)) 
      : 3; // Default

    const safetyStock = Math.ceil(dailyUsage * 2); // 2 days safety
    const reorderPoint = (dailyUsage * leadTimeDays) + safetyStock;
    const daysCover = record.quantity / (dailyUsage || 1);

    // Mock "On Order" data (Simulate 30% chance of having an order incoming)
    const onOrder = (seed % 3 === 0) ? Math.ceil(reorderPoint * 1.5) : 0;

    // Calculate Expiry Lots (for perishables)
    let expiryLots: ExpiryLot[] = [];
    if (item.isPerishable && record.expiryDate) {
      const daysUntil = Math.ceil((new Date(record.expiryDate).getTime() - Date.now()) / (86400000));
      
      // Simulate multiple batches (FEFO) to show timeline distribution
      // Batch 1: Expiring soon (The record.expiryDate is the soonest one)
      const q1 = Math.ceil(record.quantity * 0.25);
      // Batch 2: Expiring mid-term
      const q2 = Math.ceil(record.quantity * 0.45);
      // Batch 3: Fresh
      const q3 = Math.max(0, record.quantity - q1 - q2);

      const lot1: ExpiryLot = {
        daysUntilExpiry: daysUntil,
        quantity: q1,
        riskLevel: daysUntil <= 2 ? 'critical' : daysUntil <= 5 ? 'warning' : 'safe'
      };

      const lot2: ExpiryLot = {
        daysUntilExpiry: daysUntil + 6,
        quantity: q2,
        riskLevel: (daysUntil + 6) <= 5 ? 'warning' : 'safe'
      };
      
      const lot3: ExpiryLot = {
        daysUntilExpiry: daysUntil + 14,
        quantity: q3,
        riskLevel: 'safe'
      };

      expiryLots = [lot1, lot2, lot3].filter(l => l.quantity > 0);
    }

    const status = calculateStatus(record.quantity, reorderPoint, expiryLots);

    return {
      id: `${location.id}-${item.id}`,
      skuId: item.id,
      item,
      locationId: location.id,
      locationName: location.name,
      onHand: record.quantity,
      onOrder,
      dailyUsage,
      leadTimeDays,
      reorderPoint,
      safetyStock,
      daysCover: parseFloat(daysCover.toFixed(1)),
      status,
      expiryLots
    };
  }).filter((pos): pos is InventoryPosition => pos !== null);
};