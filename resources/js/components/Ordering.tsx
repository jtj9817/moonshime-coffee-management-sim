import {
    AlertTriangle,
    ChevronDown,
    ChevronUp,
    Clock,
    DollarSign,
    Lightbulb,
    Package,
    Plus,
    Search,
    Send,
    ShoppingBag,
    Trash2,
    TrendingUp,
    Truck,
    Zap,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import { useApp } from '../App';
import { SUPPLIERS, SUPPLIER_ITEMS } from '../constants';
import { formatCurrency } from '../lib/formatCurrency';
import { suggestConsolidationAdds } from '../services/cockpitService';
import { calculateInventoryPositions } from '../services/inventoryService';
import { calcMaxPerishableOrder } from '../services/orderCalculations';
import { evaluateBulkTierBreakeven } from '../services/skuMath';
import { chooseBestVendorGivenUrgency } from '../services/vendorService';
import {
    ConsolidationSuggestion,
    DraftOrder,
    OrderWarning,
    Supplier,
    SupplierItem,
} from '../types';

import ProductIcon from './ProductIcon';

const Ordering: React.FC = () => {
    const {
        locations,
        currentLocationId,
        setCurrentLocationId,
        drafts,
        addToDraft,
        removeFromDraft,
        submitDraft,
        inventory,
        items,
        gameState,
    } = useApp();
    const [searchParams] = useSearchParams();

    // Local State
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [expandedItemId, setExpandedItemId] = useState<string | null>(null);
    const [addQty, setAddQty] = useState<number>(0);
    const [targetLocationId, setTargetLocationId] = useState(
        currentLocationId === 'all' ? locations[0].id : currentLocationId,
    );

    // Animation State for Stamp
    const [showStamp, setShowStamp] = useState(false);

    // Derive Inventory Stats for context in catalog
    const inventoryPositions = useMemo(
        () => calculateInventoryPositions(inventory, items, locations),
        [inventory, items, locations],
    );

    // Handle URL params - initialize from search params on mount
    const locParam = searchParams.get('locId');
    const itemParam = searchParams.get('itemId');

    // Sync target location from URL params or global location
    const effectiveTargetLocationId = useMemo(() => {
        if (locParam && locParam !== 'all') return locParam;
        if (currentLocationId !== 'all') return currentLocationId;
        return targetLocationId;
    }, [locParam, currentLocationId, targetLocationId]);

    // Keep targetLocationId in sync
    if (effectiveTargetLocationId !== targetLocationId) {
        setTargetLocationId(effectiveTargetLocationId);
    }

    // Sync global header when URL param specifies a location
    if (locParam && locParam !== 'all' && currentLocationId !== locParam) {
        setCurrentLocationId(locParam);
    }

    // Expand item from URL param
    if (itemParam && expandedItemId !== itemParam) {
        setExpandedItemId(itemParam);
    }

    // --- Logic Helpers ---

    const getFilteredItems = () => {
        return items.filter((item) => {
            const matchesSearch = item.name
                .toLowerCase()
                .includes(searchTerm.toLowerCase());
            const matchesCategory =
                selectedCategory === 'all' ||
                item.category === selectedCategory;
            return matchesSearch && matchesCategory;
        });
    };

    const getVendorOptions = (itemId: string) => {
        return SUPPLIER_ITEMS.filter((si) => si.itemId === itemId)
            .map((si) => {
                const supplier = SUPPLIERS.find((s) => s.id === si.supplierId);
                return { ...si, supplier };
            })
            .sort((a, b) => a.pricePerUnit - b.pricePerUnit); // Cheapest first
    };

    const getRecommendedVendor = (
        itemId: string,
        urgencyLevel: 'critical' | 'high' | 'standard' | 'low',
        neededQty: number,
    ) => {
        const result = chooseBestVendorGivenUrgency(
            itemId,
            neededQty,
            urgencyLevel,
        );
        return result.selected;
    };

    const handleAdd = (si: SupplierItem, supplier: Supplier) => {
        if (addQty < si.minOrderQty) return;
        addToDraft(
            supplier.id,
            targetLocationId,
            items.find((i) => i.id === si.itemId)!,
            addQty,
            si.pricePerUnit,
        );
        setAddQty(0);
        setExpandedItemId(null);
    };

    const handleSubmitWithFX = (vendorId: string) => {
        submitDraft(vendorId);
        setShowStamp(true);
        setTimeout(() => setShowStamp(false), 2000);
    };

    // --- Draft Analysis Logic ---

    const analyzeDraft = (
        draft: DraftOrder,
    ): {
        subtotal: number;
        shippingCost: number;
        total: number;
        warnings: OrderWarning[];
        progressToFreeShipping: number;
        consolidationSuggestions: ConsolidationSuggestion[];
    } => {
        const supplier = SUPPLIERS.find((s) => s.id === draft.vendorId);
        if (!supplier)
            return {
                subtotal: 0,
                shippingCost: 0,
                total: 0,
                warnings: [],
                progressToFreeShipping: 0,
                consolidationSuggestions: [],
            };

        let subtotal = 0;
        const warnings: OrderWarning[] = [];

        draft.items.forEach((line) => {
            subtotal += line.qty * line.unitPrice;
            const item = items.find((i) => i.id === line.itemId);
            const sItem = SUPPLIER_ITEMS.find(
                (si) =>
                    si.supplierId === draft.vendorId &&
                    si.itemId === line.itemId,
            );
            const pos = inventoryPositions.find(
                (p) => p.skuId === item?.id && p.locationId === line.locationId,
            );

            // Warning 1: Smart Perishability Limit
            if (item?.isPerishable && pos) {
                const limit = calcMaxPerishableOrder(
                    item,
                    pos.dailyUsage,
                    pos.onHand,
                    sItem?.deliveryDays || 1,
                );
                if (line.qty > limit.maxOrderQty) {
                    warnings.push({
                        kind: 'EXPIRY',
                        message: `High Waste Risk: ${limit.rationale}`,
                        impact: {
                            waste:
                                (line.qty - limit.maxOrderQty) * line.unitPrice,
                        },
                    });
                }
            }

            // Warning 2: Min Order per Item
            if (sItem && line.qty < sItem.minOrderQty) {
                warnings.push({
                    kind: 'MIN_ORDER',
                    message: `${item?.name}: Quantity ${line.qty} is below MOQ of ${sItem.minOrderQty}.`,
                });
            }

            // Warning 3: Smart Tier Pricing Analysis
            if (sItem?.priceTiers && item) {
                const sortedTiers = [...sItem.priceTiers].sort(
                    (a, b) => a.minQty - b.minQty,
                );
                const currentTier = sortedTiers.find(
                    (t) => line.qty >= t.minQty,
                ) || { minQty: 0, unitPrice: sItem.pricePerUnit };
                const nextTier = sortedTiers.find((t) => t.minQty > line.qty);

                if (nextTier) {
                    const analysis = evaluateBulkTierBreakeven(
                        currentTier,
                        nextTier,
                        item,
                        line.qty,
                    );
                    if (analysis.recommendation === 'UPGRADE_TIER') {
                        const addQty = nextTier.minQty - line.qty;
                        warnings.push({
                            kind: 'TIER_BAD_DEAL',
                            message: `Buy ${addQty} more to save $${formatCurrency(analysis.netBenefit)} overall (Breakeven Analysis).`,
                            impact: { cost: analysis.savingsAtTargetTier },
                        });
                    }
                }
            }

            // Warning 4: Stockout Risk during Lead Time
            if (pos && sItem) {
                const daysToArrival = sItem.deliveryDays;
                // If stock covers less than arrival time + 1 day buffer
                if (pos.daysCover < daysToArrival + 1) {
                    warnings.push({
                        kind: 'STOCKOUT_RISK',
                        message: `${item?.name} stock (${pos.onHand}) may deplete before ${daysToArrival}-day delivery arrives.`,
                    });
                }
            }
        });

        const meetsFreeShipping = subtotal >= supplier.freeShippingThreshold;
        const shippingCost = meetsFreeShipping ? 0 : supplier.flatShippingRate;
        const progressToFreeShipping = Math.min(
            100,
            (subtotal / supplier.freeShippingThreshold) * 100,
        );

        // Warning 5: Consolidation Suggestions (New Logic)
        let consolidationSuggestions: ConsolidationSuggestion[] = [];
        if (!meetsFreeShipping && progressToFreeShipping > 50) {
            const result = suggestConsolidationAdds(
                draft,
                items,
                inventoryPositions,
            );
            consolidationSuggestions = result.suggestions;

            if (result.suggestions.length === 0) {
                // Fallback generic message if no smart suggestions found
                warnings.push({
                    kind: 'CONSOLIDATION_OPP',
                    message: `Add $${formatCurrency(supplier.freeShippingThreshold - subtotal)} more to save $${formatCurrency(shippingCost)} shipping.`,
                    impact: { cost: shippingCost },
                });
            }
        }

        // Sort warnings by severity/importance
        const priority: Record<string, number> = {
            STOCKOUT_RISK: 0,
            EXPIRY: 1,
            MIN_ORDER: 2,
            TIER_BAD_DEAL: 3,
            CONSOLIDATION_OPP: 4,
        };
        warnings.sort((a, b) => priority[a.kind] - priority[b.kind]);

        return {
            subtotal,
            shippingCost,
            total: subtotal + shippingCost,
            warnings,
            progressToFreeShipping,
            consolidationSuggestions,
        };
    };

    const categories = Array.from(new Set(items.map((i) => i.category)));

    return (
        <div className="relative flex h-[calc(100vh-140px)] animate-in flex-col gap-6 duration-500 fade-in xl:flex-row">
            {/* --- STAMP ANIMATION OVERLAY --- */}
            {showStamp && (
                <div className="pointer-events-none absolute inset-0 z-50 flex items-center justify-center">
                    <div className="animate-stamp rotate-[-12deg] rounded-xl border-8 border-emerald-600 bg-white/10 p-8 text-8xl font-black text-emerald-600 uppercase opacity-0 shadow-2xl backdrop-blur-sm">
                        APPROVED
                    </div>
                </div>
            )}
            <style>{`
        @keyframes stamp {
            0% { opacity: 0; transform: scale(2) rotate(-12deg); }
            10% { opacity: 1; transform: scale(1) rotate(-12deg); }
            80% { opacity: 1; transform: scale(1) rotate(-12deg); }
            100% { opacity: 0; transform: scale(1.5) rotate(-12deg); }
        }
        .animate-stamp { animation: stamp 1.5s ease-out forwards; }
      `}</style>

            {/* --- LEFT: Catalog & Sourcing --- */}
            <div className="flex min-w-0 flex-col gap-4 xl:w-7/12">
                <div className="flex flex-shrink-0 flex-col items-start justify-between gap-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-xl font-bold text-stone-900">
                            Sourcing Catalog
                        </h2>
                        <p className="text-sm text-stone-500">
                            Available Budget:{' '}
                            <span className="font-bold text-emerald-600">
                                ${formatCurrency(gameState.cash)}
                            </span>
                        </p>
                    </div>

                    {/* Target Location Selector */}
                    <div className="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 p-2">
                        <span className="text-xs font-bold tracking-wide text-stone-400 uppercase">
                            Ordering For:
                        </span>
                        <select
                            value={targetLocationId}
                            onChange={(e) =>
                                setTargetLocationId(e.target.value)
                            }
                            className="cursor-pointer bg-transparent text-sm font-bold text-stone-900 outline-none hover:text-amber-600"
                        >
                            {locations.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-shrink-0 gap-2">
                    <div className="relative flex-1">
                        <Search
                            className="absolute top-1/2 left-3 -translate-y-1/2 transform text-stone-400"
                            size={16}
                        />
                        <input
                            type="text"
                            placeholder="Search items..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full rounded-lg border border-stone-200 bg-white py-2 pr-4 pl-9 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20"
                        />
                    </div>
                    <select
                        value={selectedCategory}
                        onChange={(e) => setSelectedCategory(e.target.value)}
                        className="rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm outline-none"
                    >
                        <option value="all">All Categories</option>
                        {categories.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Item List */}
                <div className="scrollbar-thin flex-1 space-y-3 overflow-y-auto pr-2">
                    {getFilteredItems().map((item) => {
                        const vendorOptions = getVendorOptions(item.id);
                        const isExpanded = expandedItemId === item.id;

                        // Get current stock context
                        const pos = inventoryPositions.find(
                            (p) =>
                                p.skuId === item.id &&
                                p.locationId === targetLocationId,
                        );
                        const needsRestock = pos
                            ? pos.onHand <= pos.reorderPoint
                            : false;

                        return (
                            <div
                                key={item.id}
                                className={`rounded-xl border bg-white transition-all ${isExpanded ? 'border-amber-400 shadow-md ring-1 ring-amber-400/20' : 'border-stone-200 hover:border-amber-200'}`}
                            >
                                <div
                                    className="flex cursor-pointer items-center gap-4 p-4"
                                    onClick={() => {
                                        setExpandedItemId(
                                            isExpanded ? null : item.id,
                                        );
                                        setAddQty(0); // Reset qty on toggle
                                    }}
                                >
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-stone-100">
                                        <ProductIcon
                                            category={item.category}
                                            className="h-9 w-9"
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-bold text-stone-900">
                                                {item.name}
                                            </h3>
                                            {needsRestock && (
                                                <span className="flex items-center gap-1 rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">
                                                    <AlertTriangle size={10} />{' '}
                                                    Low Stock
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-1 flex gap-4 text-xs text-stone-500">
                                            <span>{item.category}</span>
                                            <span>•</span>
                                            <span>{item.unit}</span>
                                            {pos && (
                                                <>
                                                    <span>•</span>
                                                    <span
                                                        className={`${needsRestock ? 'font-bold text-rose-600' : 'text-stone-500'}`}
                                                    >
                                                        Stock: {pos.onHand}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    <div className="text-stone-400">
                                        {isExpanded ? (
                                            <ChevronUp size={20} />
                                        ) : (
                                            <ChevronDown size={20} />
                                        )}
                                    </div>
                                </div>

                                {/* Sourcing Panel */}
                                {isExpanded && (
                                    <div className="animate-in border-t border-stone-100 bg-stone-50/50 p-4 slide-in-from-top-2">
                                        <div className="mb-3 flex items-center justify-between">
                                            <div className="text-xs font-bold tracking-wider text-stone-400 uppercase">
                                                Available Suppliers
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-[10px] text-stone-400">
                                                    Urgency:
                                                </span>
                                                <select
                                                    className="rounded border border-stone-200 bg-white px-1 py-0.5 text-[10px] outline-none"
                                                    onChange={() => {}}
                                                >
                                                    <option value="standard">
                                                        Standard
                                                    </option>
                                                    <option value="high">
                                                        High
                                                    </option>
                                                    <option value="critical">
                                                        Critical
                                                    </option>
                                                    <option value="low">
                                                        Low
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            {vendorOptions.map((opt) => (
                                                <div
                                                    key={opt.supplierId}
                                                    className={`rounded-lg border bg-white p-3 transition-all ${
                                                        opt.supplierId ===
                                                        getRecommendedVendor(
                                                            item.id,
                                                            'standard',
                                                            addQty ||
                                                                opt.minOrderQty,
                                                        )?.vendor?.id
                                                            ? 'border-emerald-400 shadow-md ring-1 ring-emerald-400/20'
                                                            : 'border-stone-200 hover:shadow-sm'
                                                    }`}
                                                >
                                                    <div className="mb-2 flex items-start justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <div className="text-sm font-bold text-stone-800">
                                                                {
                                                                    opt.supplier
                                                                        ?.name
                                                                }
                                                            </div>
                                                            {opt.supplierId ===
                                                                getRecommendedVendor(
                                                                    item.id,
                                                                    'standard',
                                                                    addQty ||
                                                                        opt.minOrderQty,
                                                                )?.vendor
                                                                    ?.id && (
                                                                <span className="flex items-center gap-0.5 rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold text-emerald-700">
                                                                    <Zap
                                                                        size={8}
                                                                        className="fill-emerald-500"
                                                                    />{' '}
                                                                    BEST
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-1 rounded bg-stone-100 px-1.5 py-0.5 text-xs text-stone-600">
                                                            <Truck size={10} />{' '}
                                                            {opt.deliveryDays}d
                                                        </div>
                                                    </div>

                                                    <div className="flex items-end justify-between">
                                                        <div>
                                                            <div className="text-lg font-bold text-stone-900">
                                                                $
                                                                {
                                                                    opt.pricePerUnit
                                                                }
                                                            </div>
                                                            <div className="text-[10px] text-stone-400">
                                                                Min Order:{' '}
                                                                {
                                                                    opt.minOrderQty
                                                                }{' '}
                                                                {item.unit}
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                id="text-field-container"
                                                                type="number"
                                                                min={
                                                                    opt.minOrderQty
                                                                }
                                                                placeholder={opt.minOrderQty.toString()}
                                                                className="w-16 rounded-md border border-stone-200 bg-stone-50 px-2 py-1.5 text-sm text-stone-900 outline-none focus:border-amber-500"
                                                                onChange={(e) =>
                                                                    setAddQty(
                                                                        parseInt(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                onClick={(e) =>
                                                                    e.stopPropagation()
                                                                }
                                                            />
                                                            <button
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    handleAdd(
                                                                        opt,
                                                                        opt.supplier!,
                                                                    );
                                                                }}
                                                                disabled={
                                                                    addQty <
                                                                    opt.minOrderQty
                                                                }
                                                                className={`rounded-md p-1.5 text-white transition-colors ${addQty >= opt.minOrderQty ? 'bg-amber-600 hover:bg-amber-700' : 'cursor-not-allowed bg-stone-300'}`}
                                                            >
                                                                <Plus
                                                                    size={18}
                                                                />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                    {getFilteredItems().length === 0 && (
                        <div className="py-12 text-center text-stone-400">
                            No items match your search.
                        </div>
                    )}
                </div>
            </div>

            {/* --- RIGHT: Draft Workspaces --- */}
            <div className="flex min-w-0 flex-col gap-4 border-l border-stone-200 pl-6 xl:w-5/12 xl:border-l-0 xl:pl-0">
                <div className="flex-shrink-0 rounded-xl bg-stone-900 p-4 text-white shadow-lg">
                    <h2 className="flex items-center gap-2 text-xl font-bold">
                        <ShoppingBag className="text-amber-500" /> Draft Orders
                    </h2>
                    <p className="text-sm text-stone-400">
                        {drafts.length} Active Vendor Cart
                        {drafts.length !== 1 ? 's' : ''}
                    </p>
                </div>

                <div className="scrollbar-thin flex-1 space-y-4 overflow-y-auto pr-2">
                    {drafts.length === 0 ? (
                        <div className="flex h-64 flex-col items-center justify-center rounded-xl border-2 border-dashed border-stone-200 text-stone-400">
                            <Package size={48} className="mb-4 opacity-20" />
                            <p>Your draft carts are empty.</p>
                            <p className="mt-2 text-sm">
                                Select items from the catalog to build an order.
                            </p>
                        </div>
                    ) : (
                        drafts.map((draft) => {
                            const supplier = SUPPLIERS.find(
                                (s) => s.id === draft.vendorId,
                            );
                            const {
                                subtotal,
                                shippingCost,
                                total,
                                warnings,
                                progressToFreeShipping,
                                consolidationSuggestions,
                            } = analyzeDraft(draft);
                            const canAfford = total <= gameState.cash;

                            return (
                                <div
                                    key={draft.vendorId}
                                    className="flex flex-col overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm"
                                >
                                    {/* Vendor Header */}
                                    <div className="flex items-center justify-between border-b border-stone-200 bg-stone-50 p-4">
                                        <h3 className="font-bold text-stone-900">
                                            {supplier?.name}
                                        </h3>
                                        <span
                                            className={`rounded px-2 py-0.5 text-[10px] font-bold ${supplier?.deliverySpeed === 'Fast' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-600'}`}
                                        >
                                            {supplier?.deliverySpeed} Delivery
                                        </span>
                                    </div>

                                    {/* Line Items */}
                                    <div className="flex-1 space-y-3 p-4">
                                        {draft.items.map((line) => {
                                            const item = items.find(
                                                (i) => i.id === line.itemId,
                                            );
                                            const locName = locations.find(
                                                (l) => l.id === line.locationId,
                                            )?.name;
                                            return (
                                                <div
                                                    key={line.id}
                                                    className="group flex items-start justify-between"
                                                >
                                                    <div className="flex gap-2">
                                                        <div className="h-full w-1 rounded-full bg-stone-200"></div>
                                                        <div>
                                                            <div className="text-sm font-bold text-stone-900">
                                                                {item?.name}
                                                            </div>
                                                            <div className="text-xs text-stone-500">
                                                                {line.qty}{' '}
                                                                {item?.unit} @ $
                                                                {formatCurrency(
                                                                    line.unitPrice,
                                                                )}
                                                            </div>
                                                            <div className="mt-0.5 flex items-center gap-1 text-[10px] text-stone-400">
                                                                <Package
                                                                    size={8}
                                                                />{' '}
                                                                {locName}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-3">
                                                        <span className="text-sm font-medium text-stone-700">
                                                            $
                                                            {formatCurrency(
                                                                line.qty *
                                                                    line.unitPrice,
                                                            )}
                                                        </span>
                                                        <button
                                                            onClick={() =>
                                                                removeFromDraft(
                                                                    draft.vendorId,
                                                                    line.id,
                                                                )
                                                            }
                                                            className="text-stone-300 opacity-0 transition-colors group-hover:opacity-100 hover:text-rose-500"
                                                        >
                                                            <Trash2 size={14} />
                                                        </button>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Consolidation Suggestions */}
                                    {consolidationSuggestions.length > 0 && (
                                        <div className="px-4 pb-2">
                                            <div className="rounded-lg border border-amber-100 bg-amber-50 p-3">
                                                <div className="mb-2 flex items-center gap-2 text-xs font-bold text-amber-800 uppercase">
                                                    <Lightbulb
                                                        size={12}
                                                        className="fill-amber-500 text-amber-500"
                                                    />{' '}
                                                    Smart Additions
                                                </div>
                                                <div className="space-y-2">
                                                    {consolidationSuggestions.map(
                                                        (s) => (
                                                            <div
                                                                key={s.itemId}
                                                                className="flex items-center justify-between rounded border border-amber-100 bg-white p-2 shadow-sm"
                                                            >
                                                                <div>
                                                                    <div className="text-xs font-bold text-stone-800">
                                                                        {
                                                                            s.itemName
                                                                        }
                                                                    </div>
                                                                    <div className="text-[10px] text-stone-500">
                                                                        {
                                                                            s.reason
                                                                        }
                                                                    </div>
                                                                </div>
                                                                <button
                                                                    onClick={() =>
                                                                        addToDraft(
                                                                            draft.vendorId,
                                                                            targetLocationId,
                                                                            items.find(
                                                                                (
                                                                                    i,
                                                                                ) =>
                                                                                    i.id ===
                                                                                    s.itemId,
                                                                            )!,
                                                                            s.suggestedQty,
                                                                            0,
                                                                        )
                                                                    } // Price handled by addToDraft lookup usually, or pass correctly
                                                                    className="rounded bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-800 transition-colors hover:bg-amber-200"
                                                                >
                                                                    +{' '}
                                                                    {
                                                                        s.suggestedQty
                                                                    }
                                                                </button>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Warnings List */}
                                    {warnings.length > 0 && (
                                        <div className="space-y-2 px-4 pb-4">
                                            {warnings.map((w, idx) => (
                                                <div
                                                    key={idx}
                                                    className={`flex gap-3 rounded-lg border p-3 text-xs transition-all ${
                                                        w.kind ===
                                                        'CONSOLIDATION_OPP'
                                                            ? 'border-amber-100 bg-amber-50 text-amber-800'
                                                            : w.kind ===
                                                                'EXPIRY'
                                                              ? 'border-rose-100 bg-rose-50 text-rose-800'
                                                              : w.kind ===
                                                                  'STOCKOUT_RISK'
                                                                ? 'border-rose-100 bg-rose-50 text-rose-800'
                                                                : w.kind ===
                                                                    'TIER_BAD_DEAL'
                                                                  ? 'border-indigo-100 bg-indigo-50 text-indigo-800'
                                                                  : 'border-blue-100 bg-blue-50 text-blue-800'
                                                    }`}
                                                >
                                                    <div className="mt-0.5 flex-shrink-0">
                                                        {w.kind ===
                                                            'CONSOLIDATION_OPP' && (
                                                            <DollarSign
                                                                size={14}
                                                            />
                                                        )}
                                                        {w.kind ===
                                                            'EXPIRY' && (
                                                            <AlertTriangle
                                                                size={14}
                                                            />
                                                        )}
                                                        {w.kind ===
                                                            'STOCKOUT_RISK' && (
                                                            <Clock size={14} />
                                                        )}
                                                        {w.kind ===
                                                            'TIER_BAD_DEAL' && (
                                                            <TrendingUp
                                                                size={14}
                                                            />
                                                        )}
                                                        {w.kind ===
                                                            'MIN_ORDER' && (
                                                            <Info size={14} />
                                                        )}
                                                    </div>
                                                    <div>
                                                        <span className="mb-0.5 block font-bold opacity-90">
                                                            {w.kind ===
                                                            'CONSOLIDATION_OPP'
                                                                ? 'Shipping Savings'
                                                                : w.kind ===
                                                                    'TIER_BAD_DEAL'
                                                                  ? 'Bulk Discount Opportunity'
                                                                  : w.kind ===
                                                                      'EXPIRY'
                                                                    ? 'Spoilage Risk'
                                                                    : w.kind ===
                                                                        'STOCKOUT_RISK'
                                                                      ? 'Stockout Risk'
                                                                      : 'Order Requirement'}
                                                        </span>
                                                        <span className="block leading-snug opacity-80">
                                                            {w.message}
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Summary & Checkout */}
                                    <div className="border-t border-stone-200 bg-stone-50/30 p-4">
                                        {/* Free Shipping Progress */}
                                        <div className="mb-4">
                                            <div className="mb-1 flex justify-between text-[10px] text-stone-500">
                                                <span>
                                                    Free Shipping Progress
                                                </span>
                                                <span>
                                                    ${formatCurrency(subtotal)}{' '}
                                                    / $
                                                    {formatCurrency(
                                                        supplier?.freeShippingThreshold ??
                                                            0,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-stone-100">
                                                <div
                                                    className={`h-full rounded-full transition-all duration-500 ${progressToFreeShipping >= 100 ? 'bg-emerald-500' : 'bg-amber-500'}`}
                                                    style={{
                                                        width: `${progressToFreeShipping}%`,
                                                    }}
                                                ></div>
                                            </div>
                                        </div>

                                        <div className="mb-1 flex justify-between text-sm">
                                            <span className="text-stone-500">
                                                Subtotal
                                            </span>
                                            <span className="font-medium">
                                                ${formatCurrency(subtotal)}
                                            </span>
                                        </div>
                                        <div className="mb-3 flex justify-between text-sm">
                                            <span className="text-stone-500">
                                                Shipping
                                            </span>
                                            <span className="font-medium">
                                                {shippingCost === 0 ? (
                                                    <span className="font-bold text-emerald-600">
                                                        FREE
                                                    </span>
                                                ) : (
                                                    `$${formatCurrency(shippingCost)}`
                                                )}
                                            </span>
                                        </div>

                                        <button
                                            onClick={() =>
                                                handleSubmitWithFX(
                                                    draft.vendorId,
                                                )
                                            }
                                            disabled={!canAfford}
                                            className={`flex w-full items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-bold text-white shadow-lg transition-all active:scale-95 ${canAfford ? 'bg-stone-900 shadow-stone-900/10 hover:bg-stone-800' : 'cursor-not-allowed bg-stone-400'}`}
                                        >
                                            {canAfford ? (
                                                <>
                                                    <span>
                                                        Authorize Payment
                                                    </span>
                                                    <span className="font-normal opacity-80">
                                                        | $
                                                        {formatCurrency(total)}
                                                    </span>
                                                    <Send size={14} />
                                                </>
                                            ) : (
                                                <span>Insufficient Funds</span>
                                            )}
                                        </button>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </div>
    );
};

export default Ordering;
