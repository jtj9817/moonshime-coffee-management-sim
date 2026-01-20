import { SUPPLIERS, SUPPLIER_ITEMS } from '../constants';
import { SpikeSignal, EmergencyOption, Item, Location, InventoryRecord, SpikeHistoryEvent } from '../types';

// Simulated spike generator with Supply Chain awareness
export const detectSpikes = (
  locations: Location[],
  items: Item[],
  inventory: InventoryRecord[]
): SpikeSignal[] => {
  const spikes: SpikeSignal[] = [];

  // Randomly generate a spike event (30% chance per poll)
  const isSpiking = Math.random() > 0.7; 
  
  if (isSpiking) {
    const loc = locations[Math.floor(Math.random() * locations.length)];
    
    // Focus on high velocity items where spikes matter most
    const candidates = items.filter(i => 
      ['Milk', 'Beans', 'Cups'].includes(i.category)
    );
    
    if (candidates.length === 0) return [];
    
    const item = candidates[Math.floor(Math.random() * candidates.length)];
    
    // 1. Simulate Spike Metrics (Demand Side)
    const baseline = 5 + Math.floor(Math.random() * 10); 
    const multiplier = 1.5 + (Math.random() * 3.5); // 1.5x to 5x spike
    const current = Math.floor(baseline * multiplier);
    
    // 2. Inventory Context
    const record = inventory.find(r => r.locationId === loc.id && r.itemId === item.id);
    const stock = record ? record.quantity : 0;
    
    // 3. Runway Calculation (Time Until Empty)
    const burnRate = Math.max(1, current);
    const hoursLeft = stock / burnRate;
    
    // 4. Supply Chain Context (Lead Time & Reliability)
    const sItem = SUPPLIER_ITEMS.find(si => si.itemId === item.id);
    const supplier = sItem ? SUPPLIERS.find(s => s.id === sItem.supplierId) : null;
    
    const leadTimeDays = sItem?.deliveryDays || 1;
    const lateRate = supplier?.metrics?.lateRate || 0.0;
    
    // Variance Logic: 
    // If supplier is unreliable (high lateRate), we effectively treat the lead time as longer
    // to ensure we alert early enough to use an alternative source.
    // Factor: 1.0 (reliable) to ~1.5+ (unreliable)
    const varianceBuffer = 1 + (lateRate * 2.5); 
    
    const leadTimeHours = leadTimeDays * 24;
    // The "Point of No Return" considering vendor reliability
    const criticalThreshold = leadTimeHours * varianceBuffer;

    // Alert Conditions:
    // A. Immediate Operational Crisis: Running out in < 6 hours (Needs Courier)
    // B. Strategic Supply Gap: Running out faster than the unreliable vendor can likely restock (Needs Expedite/Transfer)
    const isImmediateCrisis = hoursLeft < 6;
    const isSupplyGap = hoursLeft < criticalThreshold && multiplier > 1.2;

    if (isImmediateCrisis || isSupplyGap) {
      const now = new Date();
      const shortageTime = new Date(now.getTime() + hoursLeft * 60 * 60 * 1000);
      
      spikes.push({
        id: `spike-${Date.now()}`,
        locationId: loc.id,
        skuId: item.id,
        metric: item.category === 'Milk' ? 'MILK_USAGE' : 'DRINKS_SOLD',
        baseline,
        current,
        multiplier: parseFloat(multiplier.toFixed(1)),
        projectedDaily: current * 12, // Projection for remainder of day
        shortageAt: shortageTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        timestamp: new Date().toISOString()
      });
    }
  }

  return spikes;
};

export const getEmergencyOptions = (
  signal: SpikeSignal,
  item: Item
): EmergencyOption[] => {
  const options: EmergencyOption[] = [];

  // Get Supplier Info for dynamic option generation
  const sItem = SUPPLIER_ITEMS.find(si => si.itemId === item.id);
  const supplier = sItem ? SUPPLIERS.find(s => s.id === sItem.supplierId) : null;
  const standardLeadTimeHours = (sItem?.deliveryDays || 1) * 24;

  // 1. Instant Local Courier
  const courierCost = 45.00 + (Math.random() * 20);
  options.push({
    id: 'opt-courier',
    type: 'COURIER',
    providerName: 'Flash Courier Service',
    cost: parseFloat(courierCost.toFixed(2)),
    etaHours: 1.5,
    etaLabel: '90 Mins',
    recommended: true
  });

  // 2. Vendor Expedite
  // Expedited is usually ~40% of standard lead time, min 12 hours
  const expediteEta = Math.max(12, standardLeadTimeHours * 0.4); 
  const expediteCost = 25.00;
  
  // Assess Vendor Risk
  const isReliable = (supplier?.metrics?.lateRate || 0) < 0.05;
  const riskDesc = !isReliable 
    ? `High Risk: Vendor has ${(supplier?.metrics?.lateRate || 0) * 100}% late rate` 
    : undefined;

  options.push({
    id: 'opt-vendor',
    type: 'VENDOR_EXPEDITE',
    providerName: supplier ? `${supplier.name} Priority` : 'Vendor Priority',
    cost: expediteCost,
    etaHours: expediteEta,
    etaLabel: expediteEta < 24 ? `${expediteEta.toFixed(0)} Hours` : `${(expediteEta/24).toFixed(1)} Days`,
    riskDescription: riskDesc,
    recommended: false
  });

  // 3. Ignore / Stockout Cost Calculation
  const hoursStockout = 4; // Avg impact duration
  // revenue per unit usage * duration
  const lostRevenue = signal.current * 8.0 * hoursStockout; 

  options.push({
    id: 'opt-ignore',
    type: 'IGNORE',
    providerName: 'Do Nothing',
    cost: parseFloat(lostRevenue.toFixed(2)),
    etaHours: 0,
    etaLabel: 'N/A',
    riskDescription: 'Revenue loss & brand damage',
    recommended: false
  });

  return options;
};

// --- MOCK HISTORY GENERATOR ---

export const generateSpikeHistory = (
  items: Item[],
  locations: Location[],
  startDate: Date,
  endDate: Date
): SpikeHistoryEvent[] => {
  const history: SpikeHistoryEvent[] = [];
  const oneDay = 24 * 60 * 60 * 1000;
  const diffDays = Math.round(Math.abs((endDate.getTime() - startDate.getTime()) / oneDay));

  for (let i = 0; i < diffDays; i++) {
    const currentDate = new Date(startDate.getTime() + (i * oneDay));
    
    // 20% chance of a spike on any given day
    if (Math.random() < 0.2) {
      const item = items[Math.floor(Math.random() * items.length)];
      const loc = locations[Math.floor(Math.random() * locations.length)];
      const multiplier = 2 + Math.random() * 4;
      
      const causes: ('WEATHER' | 'LOCAL_EVENT' | 'PROMOTION' | 'SUPPLY_FAILURE' | 'UNKNOWN')[] = 
        ['WEATHER', 'LOCAL_EVENT', 'PROMOTION', 'SUPPLY_FAILURE', 'UNKNOWN'];
      const rootCause = causes[Math.floor(Math.random() * causes.length)];

      const isResolved = Math.random() > 0.1;

      // Generate Chart Data for the day (08:00 to 18:00)
      const chartData = [];
      const baseVal = 10 + Math.random() * 10;
      for (let hour = 8; hour <= 18; hour++) {
        const time = `${hour.toString().padStart(2, '0')}:00`;
        // Spike happens around 13:00
        const spikeFactor = (hour >= 12 && hour <= 15) ? multiplier : 1.0;
        const noise = (Math.random() - 0.5) * 5;
        const value = Math.max(0, (baseVal * spikeFactor) + noise);
        chartData.push({ time, value, baseline: baseVal });
      }

      history.push({
        id: `hist-${currentDate.getTime()}`,
        date: currentDate.toISOString().split('T')[0],
        timeDetected: '12:45',
        locationId: loc.id,
        itemId: item.id,
        peakMultiplier: parseFloat(multiplier.toFixed(1)),
        durationHours: 3.5,
        totalImpactCost: parseFloat((Math.random() * 500).toFixed(2)),
        status: isResolved ? 'RESOLVED' : 'IGNORED',
        rootCause,
        resolutionLog: isResolved ? [{
          timestamp: '13:05',
          action: 'Transfer Initiated',
          user: 'System Auto-Agent',
          costIncurred: 15.00,
          note: 'Detected threshold breach. Automatically routed stock from HQ.'
        }] : [],
        chartData
      });
    }
  }

  return history.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
};