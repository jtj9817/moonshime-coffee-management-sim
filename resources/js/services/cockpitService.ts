import { SUPPLIER_ITEMS, SUPPLIERS } from '../constants';
import { Alert, GameDashboardKPI, SuggestedAction, InventoryRecord, Item, Location, DraftOrder, InventoryPosition, ConsolidationSuggestion } from '../types';

// Mock data generator for the operational cockpit

export const generateAlerts = (
  inventory: InventoryRecord[],
  items: Item[],
  locations: Location[],
  currentLocId: string
): Alert[] => {
  const alerts: Alert[] = [];

  // Filter context
  const relevantInventory = currentLocId === 'all'
    ? inventory
    : inventory.filter(r => r.locationId === currentLocId);

  relevantInventory.forEach(record => {
    const item = items.find(i => i.id === record.itemId);
    const loc = locations.find(l => l.id === record.locationId);
    if (!item || !loc) return;

    // 1. Stockout Risk (Critical)
    // Threshold: < 20% of bulk threshold
    if (record.quantity < item.bulkThreshold * 0.2) {
      alerts.push({
        id: `alert-stock-${record.locationId}-${record.itemId}`,
        type: 'STOCKOUT',
        severity: record.quantity === 0 ? 'critical' : 'warning',
        locationId: loc.id,
        locationName: loc.name,
        itemId: item.id,
        itemName: item.name,
        message: `${item.name} is ${record.quantity === 0 ? 'out of stock' : 'critically low'}`,
        rationale: `Current level (${record.quantity} ${item.unit}) is below safety stock. Lead time is 1-3 days.`,
        action: {
          label: 'Restock Now',
          to: `/ordering?itemId=${item.id}&locId=${loc.id}`
        },
        timestamp: new Date().toISOString()
      });
    }

    // 2. Expiry Risk (Warning/Critical)
    if (record.expiryDate) {
      const daysToExpiry = Math.ceil((new Date(record.expiryDate).getTime() - Date.now()) / (1000 * 60 * 60 * 24));
      if (daysToExpiry <= 3 && daysToExpiry >= 0) {
        alerts.push({
          id: `alert-expiry-${record.locationId}-${record.itemId}`,
          type: 'EXPIRY',
          severity: daysToExpiry <= 1 ? 'critical' : 'warning',
          locationId: loc.id,
          locationName: loc.name,
          itemId: item.id,
          itemName: item.name,
          message: `${item.name} expires in ${daysToExpiry} day${daysToExpiry === 1 ? '' : 's'}`,
          rationale: `Batch of ${record.quantity} ${item.unit} will perish soon. Risk of $${(record.quantity * item.storageCostPerUnit * 5).toFixed(0)} waste.`,
          action: {
            label: 'View Batch',
            to: `/inventory?loc=${loc.id}&search=${item.name}`
          },
          timestamp: new Date().toISOString()
        });
      }
    }
  });

  // 3. Simulated Spike (Random injection for demo)
  // Only add if we have some data
  if (relevantInventory.length > 0 && Math.random() > 0.7) {
    const randomRec = relevantInventory[Math.floor(Math.random() * relevantInventory.length)];
    const item = items.find(i => i.id === randomRec.itemId);
    const loc = locations.find(l => l.id === randomRec.locationId);

    if (item && loc) {
      alerts.push({
        id: `alert-spike-${Math.random()}`,
        type: 'SPIKE',
        severity: 'warning',
        locationId: loc.id,
        locationName: loc.name,
        itemId: item.id,
        itemName: item.name,
        message: `Unusual demand spike for ${item.name}`,
        rationale: `Sales velocity increased 45% in the last 4 hours. Forecast adjusted.`,
        action: {
          label: 'Adjust Order',
          to: `/ordering?itemId=${item.id}&locId=${loc.id}`
        },
        timestamp: new Date().toISOString()
      });
    }
  }

  // Sort by severity (Critical first)
  const severityScore = { critical: 3, warning: 2, info: 1 };
  return alerts.sort((a, b) => severityScore[b.severity] - severityScore[a.severity]);
};

export const generateSuggestedActions = (alerts: Alert[]): SuggestedAction[] => {
  const actions: SuggestedAction[] = [];

  // Suggest consolidation if multiple stockouts in same location
  const locationCounts: Record<string, number> = {};
  alerts.filter(a => a.type === 'STOCKOUT').forEach(a => {
    locationCounts[a.locationId] = (locationCounts[a.locationId] || 0) + 1;
  });

  Object.entries(locationCounts).forEach(([locId, count]) => {
    if (count >= 2) {
      actions.push({
        id: `act-con-${locId}`,
        kind: 'CONSOLIDATE_CART',
        title: 'Consolidate Vendor Orders',
        description: `You have ${count} low stock items at this location. Order together to save on shipping.`,
        impact: { savings: 45.00 },
        action: { label: 'Start Consolidated Order', to: `/ordering?locId=${locId}` }
      });
    }
  });

  // Suggest Transfer for critical stockouts if another location has plenty
  // (Simplified simulation)
  const criticalStockout = alerts.find(a => a.type === 'STOCKOUT' && a.severity === 'critical');
  if (criticalStockout) {
    actions.push({
      id: `act-transfer-${criticalStockout.id}`,
      kind: 'TRANSFER',
      title: `Emergency Transfer: ${criticalStockout.itemName}`,
      description: `HQ Roastery has excess stock. Transfer 20 units to ${criticalStockout.locationName} to avoid stockout today.`,
      impact: { revenueSaved: 320 },
      action: { label: 'Initiate Transfer', to: `/inventory?loc=${criticalStockout.locationId}` }
    });
  }

  return actions;
};

export const generateKPIs = (alerts: Alert[], inventory: InventoryRecord[]): GameDashboardKPI[] => {
  const criticalCount = alerts.filter(a => a.severity === 'critical').length;
  const expiryCount = alerts.filter(a => a.type === 'EXPIRY').length;

  // Calculate a rough health score (100 - penalties)
  const healthScore = Math.max(0, 100 - (criticalCount * 15) - (expiryCount * 5));

  return [
    {
      label: 'Inventory Health',
      value: `${healthScore}%`,
      status: healthScore > 80 ? 'good' : healthScore > 60 ? 'warning' : 'bad',
      trend: 2.5,
      trendDirection: 'down',
      iconName: 'Activity'
    },
    {
      label: 'Risk Value',
      value: `$${(criticalCount * 450 + expiryCount * 120).toLocaleString()}`,
      status: criticalCount > 0 ? 'warning' : 'good',
      trend: 12,
      trendDirection: 'up',
      iconName: 'DollarSign'
    },
    {
      label: 'Active Alerts',
      value: alerts.length,
      status: alerts.length > 5 ? 'bad' : alerts.length > 2 ? 'warning' : 'good',
      iconName: 'AlertOctagon',
      trendDirection: 'neutral'
    },
    {
      label: 'Projected Waste',
      value: `${expiryCount} Items`,
      status: expiryCount > 2 ? 'bad' : 'good',
      trend: 0,
      trendDirection: 'neutral',
      iconName: 'Trash2'
    }
  ];
}

export const suggestConsolidationAdds = (
  draft: DraftOrder,
  items: Item[],
  inventoryPositions: InventoryPosition[]
): { suggestions: ConsolidationSuggestion[], gapToFreeShipping: number } => {

  const supplier = SUPPLIERS.find(s => s.id === draft.vendorId);
  if (!supplier) return { suggestions: [], gapToFreeShipping: 0 };

  const currentSubtotal = draft.items.reduce((sum, item) => sum + (item.qty * item.unitPrice), 0);
  const gap = Math.max(0, supplier.freeShippingThreshold - currentSubtotal);

  if (gap <= 0) return { suggestions: [], gapToFreeShipping: 0 };

  const suggestions: ConsolidationSuggestion[] = [];

  // Identify items from this supplier that are NOT in the draft but are low stock or high velocity
  const availableItems = SUPPLIER_ITEMS.filter(si => si.supplierId === supplier.id);

  availableItems.forEach(si => {
    // Skip if already in draft
    if (draft.items.some(di => di.itemId === si.itemId)) return;

    const item = items.find(i => i.id === si.itemId);
    if (!item) return;

    // Find position (checking first location in draft for context, or generic)
    const locationId = draft.items[0]?.locationId;
    const pos = inventoryPositions.find(p => p.skuId === si.itemId && p.locationId === locationId);

    if (!pos) return;

    // Logic: 
    // 1. High Priority: Below ROP
    // 2. Medium Priority: Healthy but can top up to reach free shipping

    if (pos.onHand <= pos.reorderPoint) {
      suggestions.push({
        itemId: item.id,
        itemName: item.name,
        suggestedQty: si.minOrderQty, // Simple logic
        reason: 'Below Reorder Point',
        priority: 'high',
        savingsPotential: supplier.flatShippingRate
      });
    } else if (pos.daysCover < 14 && !item.isPerishable) {
      suggestions.push({
        itemId: item.id,
        itemName: item.name,
        suggestedQty: si.minOrderQty,
        reason: 'Top-up opportunity (Low Cover)',
        priority: 'medium',
        savingsPotential: 0
      });
    }
  });

  return {
    suggestions: suggestions.sort((a, b) => (a.priority === 'high' ? -1 : 1)).slice(0, 3),
    gapToFreeShipping: gap
  };
};
