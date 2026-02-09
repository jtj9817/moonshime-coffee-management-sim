export enum ItemCategory {
    BEANS = 'Beans',
    MILK = 'Milk',
    CUPS = 'Cups',
    SYRUP = 'Syrup',
    PASTRY = 'Pastry',
    TEA = 'Tea',
    SUGAR = 'Sugar',
    CLEANING = 'Cleaning',
    FOOD = 'Food',
    SEASONAL = 'Seasonal',
    SAUCE = 'Sauce',
}

export interface Item {
    id: string;
    name: string;
    category: ItemCategory;
    unit: string;
    isPerishable: boolean;
    bulkThreshold: number; // Qty at which storage becomes an issue
    storageCostPerUnit: number;
    // image property removed in favor of category-based SVG icons
    estimatedShelfLife: number; // Days
}

export interface Location {
    id: string;
    name: string;
    address: string;
    maxStorage: number;
}

export interface InventoryRecord {
    locationId: string;
    itemId: string;
    quantity: number;
    lastRestocked: string;
    expiryDate?: string;
}

export interface SupplierMetrics {
    lateRate: number; // 0-1 percentage
    fillRate: number; // 0-1 percentage
    complaintRate: number; // 0-1 percentage
}

export interface Supplier {
    id: string;
    name: string;
    reliability: number; // 0-1 Overall Score
    deliverySpeed: 'Fast' | 'Standard' | 'Slow';
    freeShippingThreshold: number;
    flatShippingRate: number;
    // Enhanced Fields
    description?: string;
    categories: ItemCategory[];
    contactName?: string;
    contactEmail?: string;
    phone?: string;
    metrics?: SupplierMetrics;
    trustScore?: number; // 0-100 Game Metric
}

export interface PriceTier {
    minQty: number;
    maxQty?: number;
    unitPrice: number;
}

export interface SupplierItem {
    supplierId: string;
    itemId: string;
    pricePerUnit: number;
    minOrderQty: number;
    deliveryDays: number;
    priceTiers?: PriceTier[];
}

export interface Order {
    id: string;
    locationId: string;
    supplierId: string;
    items: { itemId: string; quantity: number }[];
    status: 'Pending' | 'Shipped' | 'Delivered';
    orderDate: string;
    estimatedDelivery: string;
    totalCost: number;
}

export interface AppNotification {
    id: string;
    type: 'alert' | 'info' | 'success';
    message: string;
    timestamp: string;
    read: boolean;
}

// --- Ordering Workspace Types ---

export interface DraftLineItem {
    id: string; // unique line id
    itemId: string;
    locationId: string;
    qty: number;
    unitPrice: number;
}

export interface DraftOrder {
    vendorId: string;
    items: DraftLineItem[];
}

export interface OrderWarning {
    kind:
        | 'EXPIRY'
        | 'TIER_BAD_DEAL'
        | 'STOCKOUT_RISK'
        | 'MIN_ORDER'
        | 'CONSOLIDATION_OPP';
    message: string;
    impact?: { cost?: number; waste?: number };
}

// --- Transfer Types ---

export type TransferStatus =
    | 'DRAFT'
    | 'PENDING_APPROVAL'
    | 'IN_TRANSIT'
    | 'COMPLETED'
    | 'CANCELLED';

export interface TransferLineItem {
    skuId: string;
    qty: number;
}

export interface Transfer {
    id: string;
    sourceLocationId: string;
    targetLocationId: string;
    items: TransferLineItem[];
    status: TransferStatus;
    createdDate: string;
    estimatedArrival: string;
    notes?: string;
}

export interface TransferSuggestion {
    id: string;
    skuId: string;
    sourceLocationId: string;
    targetLocationId: string;
    qty: number;
    transferCost: number;
    transferCostBreakdown: {
        fixed: number;
        handling: number;
    };
    supplierCost: number;
    savings: number;
    timeSavedDays: number;
    reason: string;
    feasible: boolean;
}

// --- GAME & SIMULATION TYPES ---

export interface GameTime {
    day: number;
    hour: number;
    minute: number;
}

export interface Quest {
    id: string;
    title: string;
    description: string;
    reward: {
        cash?: number;
        xp: number;
        reputation?: number;
    };
    isCompleted: boolean;
    type:
        | 'STOCK_LEVEL'
        | 'NO_ALERTS'
        | 'TRANSFER_EFFICIENCY'
        | 'VENDOR_NEGOTIATION';
    targetValue?: number;
    currentValue?: number;
    locationId?: string;
    itemId?: string;
}

export interface FloatingTextEvent {
    id: string;
    text: string;
    type: 'positive' | 'negative' | 'neutral' | 'xp';
    x: number;
    y: number;
}

export interface GameState {
    cash: number;
    reputation: number; // 0-100
    xp: number;
    level: number;
    strikes: number; // max 3
    time: GameTime;
    isPaused: boolean;
}

// --- STRATEGY & POLICY TYPES ---

export interface PolicyProfile {
    globalServiceLevel: number; // 0.80 to 0.999
    safetyStockBufferPct: number; // 0.0 to 0.5 (extra buffer)
    holdingCostRate: number; // annual % for calculation
    autoTransferThreshold: number; // 0-1, triggered when stock drops below this % of ROP
}

export interface PolicyImpactAnalysis {
    projectedHoldingCost: number;
    projectedStockoutRisk: number; // 0-100
    capitalRequired: number;
    deltaHoldingCost: number;
    deltaCapital: number;
}

export interface AppContextType {
    locations: Location[];
    items: Item[];
    inventory: InventoryRecord[];
    currentLocationId: string; // 'all' or specific ID
    setCurrentLocationId: (id: string) => void;
    placeOrder: (
        locId: string,
        item: Item,
        qty: number,
        supplier: Supplier,
    ) => void;

    // Cart
    drafts: DraftOrder[];
    addToDraft: (
        vendorId: string,
        locationId: string,
        item: Item,
        qty: number,
        price: number,
    ) => void;
    removeFromDraft: (vendorId: string, lineId: string) => void;
    submitDraft: (vendorId: string) => void;

    notifications: AppNotification[];
    markNotificationRead: (id: string) => void;

    // Transfers
    transfers: Transfer[];
    createTransfer: (
        sourceId: string,
        targetId: string,
        items: TransferLineItem[],
    ) => void;
    updateTransferStatus: (id: string, status: TransferStatus) => void;

    // Game Context
    gameState: GameState;
    quests: Quest[];
    completeQuest: (id: string) => void;
    negotiateWithVendor: (
        supplierId: string,
    ) => Promise<{ success: boolean; discount?: number }>;
    triggerFeedback: (
        text: string,
        type: 'positive' | 'negative' | 'neutral' | 'xp',
    ) => void;

    // Policy Context
    policies: PolicyProfile;
    updatePolicies: (p: Partial<PolicyProfile>) => void;
}

// --- Dashboard / Cockpit Types ---

export type AlertSeverity = 'info' | 'warning' | 'critical';
export type AlertType =
    | 'STOCKOUT'
    | 'EXPIRY'
    | 'SPIKE'
    | 'VENDOR_DELAY'
    | 'WASTE';

export interface Alert {
    id: string;
    type: AlertType;
    severity: AlertSeverity;
    locationId: string;
    locationName: string;
    itemId?: string;
    itemName?: string;
    message: string;
    rationale: string;
    action?: {
        label: string;
        to: string; // URL path
    };
    timestamp: string;
}

export type SuggestedActionKind =
    | 'PLACE_ORDER'
    | 'TRANSFER'
    | 'ADJUST_REORDER_POINT'
    | 'CONSOLIDATE_CART';

export interface SuggestedAction {
    id: string;
    kind: SuggestedActionKind;
    title: string;
    description: string;
    impact: {
        savings?: number;
        wasteAvoided?: number;
        revenueSaved?: number;
    };
    action: {
        label: string;
        to: string;
    };
}

export interface GameDashboardKPI {
    label: string;
    value: string | number;
    trend?: number; // percentage
    trendDirection: 'up' | 'down' | 'neutral';
    status: 'good' | 'warning' | 'bad';
    iconName: 'Activity' | 'DollarSign' | 'Trash2' | 'AlertOctagon';
}

// --- Inventory Management Types ---

export type InventoryStatusCode =
    | 'OK'
    | 'BELOW_ROP'
    | 'EXPIRY_RISK'
    | 'STOCKOUT_RISK'
    | 'SPIKE_RISK';

export interface InventoryStatus {
    code: InventoryStatusCode;
    riskScore: number; // 0-100
    explanation: string;
    badgeColor: 'emerald' | 'amber' | 'rose' | 'blue';
}

export interface ExpiryLot {
    daysUntilExpiry: number;
    quantity: number;
    riskLevel: 'safe' | 'warning' | 'critical';
}

export interface InventoryPosition {
    id: string; // composite key loc-item
    skuId: string;
    item: Item;
    locationId: string;
    locationName: string;
    onHand: number;
    onOrder: number;
    dailyUsage: number;
    leadTimeDays: number;
    reorderPoint: number;
    safetyStock: number;
    daysCover: number;
    status: InventoryStatus;
    expiryLots: ExpiryLot[]; // For perishables
}

// --- SKU Detail / Truth Page Types ---

export interface ReorderPolicy {
    serviceLevel: number; // e.g. 0.95
    zScore: number; // e.g. 1.645
    reviewPeriodDays: number;
    transferThresholdPct: number;
}

export interface CostBreakdown {
    supplierId: string;
    supplierName: string;
    unitPrice: number;
    deliveryFeePerUnit: number;
    holdingCost: number;
    stockoutRiskCost: number;
    totalPerUnit: number;
    isBestValue: boolean;
}

export interface LandedCostBreakdown extends CostBreakdown {
    dutiesPerUnit: number;
    handlingPerUnit: number;
    currency: string;
}

export interface ForecastData {
    date: string;
    baseDemand: number;
    seasonalMultiplier: number;
    eventMultiplier: number;
    predicted: number;
    upperBound: number;
    lowerBound: number;
}

export interface VendorScoreBreakdown {
    priceScore: number;
    speedScore: number;
    reliabilityScore: number;
    totalScore: number;
}

export interface BulkTierAnalysis {
    currentUnitCost: number;
    targetUnitCost: number;
    savingsAtTargetTier: number;
    incrementalHoldingCost: number;
    netBenefit: number;
    breakevenQuantity: number;
    recommendation: 'UPGRADE_TIER' | 'STAY_CURRENT' | 'NOT_WORTH_IT';
}

export interface ConsolidationSuggestion {
    itemId: string;
    itemName: string;
    suggestedQty: number;
    reason: string;
    priority: 'high' | 'medium' | 'low';
    savingsPotential: number;
}

export interface PerishableOrderLimit {
    maxOrderQty: number;
    rationale: string;
    riskAssessment: {
        wasteRisk: number;
        stockoutRisk: number;
    };
}

// --- Spike Monitor & History Types ---

export interface SpikeSignal {
    id: string;
    locationId: string;
    skuId?: string;
    metric: 'DRINKS_SOLD' | 'MILK_USAGE' | 'BEANS_USAGE';
    baseline: number;
    current: number;
    multiplier: number;
    projectedDaily: number;
    shortageAt?: string;
    timestamp: string;
}

export interface EmergencyOption {
    id: string;
    type: 'COURIER' | 'VENDOR_EXPEDITE' | 'TRANSFER' | 'IGNORE';
    providerName: string;
    cost: number;
    etaHours: number;
    etaLabel: string;
    riskDescription?: string;
    recommended: boolean;
}

export interface SpikeResolutionLog {
    timestamp: string;
    action: string;
    user: string;
    costIncurred: number;
    note: string;
}

export interface SpikeHistoryEvent {
    id: string;
    date: string; // YYYY-MM-DD
    timeDetected: string; // HH:MM
    locationId: string;
    itemId: string;
    peakMultiplier: number;
    durationHours: number;
    totalImpactCost: number;
    status: 'RESOLVED' | 'IGNORED' | 'AUTO-MITIGATED';
    rootCause:
        | 'WEATHER'
        | 'LOCAL_EVENT'
        | 'PROMOTION'
        | 'SUPPLY_FAILURE'
        | 'UNKNOWN';
    resolutionLog: SpikeResolutionLog[];
    // For charts
    chartData: { time: string; value: number; baseline: number }[];
}

export interface SpikeFeedback {
    isFalsePositive: boolean;
    classification:
        | 'REAL_DEMAND'
        | 'DATA_ERROR'
        | 'ONE_OFF_EVENT'
        | 'OPERATIONAL_WASTE';
    rootCause?:
        | 'WEATHER'
        | 'LOCAL_EVENT'
        | 'PROMOTION'
        | 'SUPPLY_FAILURE'
        | 'UNKNOWN';
    notes: string;
}

// --- Waste & Policy Reporting Types ---

export type WasteReason =
    | 'OVER_ORDER'
    | 'FORECAST_MISS'
    | 'SUPPLIER_DELAY'
    | 'EXPIRY'
    | 'OTHER';

export interface WasteEvent {
    id: string;
    skuId: string;
    locationId: string;
    qty: number;
    unitCost: number;
    reason: WasteReason;
    date: string; // YYYY-MM-DD
}

export interface PolicyChangeLog {
    id: string;
    date: string; // YYYY-MM-DD
    user: string;
    skuId?: string;
    locationId?: string;
    changeType:
        | 'REORDER_POINT'
        | 'SAFETY_STOCK'
        | 'SUPPLIER_CHANGE'
        | 'TRANSFER_RULE';
    oldValue: string | number;
    newValue: string | number;
    reason: string;
    impactMetric?: string;
}
