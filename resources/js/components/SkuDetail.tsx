import {
    AlertTriangle,
    ArrowLeft,
    ArrowRightLeft,
    Calculator,
    Check,
    ChevronDown,
    ChevronUp,
    DollarSign,
    Edit2,
    Info,
    Layers,
    Loader2,
    Package,
    RotateCcw,
    Scale,
    ShoppingCart,
    TrendingUp,
    Truck,
    X,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { useApp } from '../App';
import { SUPPLIERS, SUPPLIER_ITEMS } from '../constants';
import {
    calcLandedCostPerUnit,
    calculateROP,
    calculateSafetyStock,
    generateMockForecast,
    getZScore,
} from '../services/skuMath';
import { CostBreakdown, LandedCostBreakdown } from '../types';

import ProductIcon from './ProductIcon';

const SkuDetail: React.FC = () => {
    const { locationId, itemId } = useParams<{
        locationId: string;
        itemId: string;
    }>();
    const { items, locations, inventory, placeOrder } = useApp();

    // --- Data Resolution ---
    const item = items.find((i) => i.id === itemId);
    const location = locations.find((l) => l.id === locationId);
    const invRecord = inventory.find(
        (r) => r.locationId === locationId && r.itemId === itemId,
    );

    // --- Simulation State ("What-If" Model) ---
    // We initialize these with "defaults" but allow user override via sliders
    const [serviceLevel, setServiceLevel] = useState<number>(0.95);
    const [avgLeadTime, setAvgLeadTime] = useState<number>(3); // Default mocked
    const [avgDailyUsage, setAvgDailyUsage] = useState<number>(25); // Default mocked
    const [demandStdDev] = useState<number>(5);
    const [leadTimeStdDev] = useState<number>(1);
    const [activeTab, setActiveTab] = useState<'math' | 'vendors' | 'expiry'>(
        'math',
    );

    // New State for Policy Edit Mode
    const [isEditingPolicy, setIsEditingPolicy] = useState(false);

    // New State for Scenario Comparison Modal
    const [comparingSupplierId, setComparingSupplierId] = useState<
        string | null
    >(null);

    // New State for Expanded Vendor Row
    const [expandedVendorId, setExpandedVendorId] = useState<string | null>(
        null,
    );

    // New State for Quick Ordering
    const [orderQuantities, setOrderQuantities] = useState<
        Record<string, number>
    >({});
    const [orderingStatus, setOrderingStatus] = useState<
        Record<string, 'idle' | 'ordering' | 'success'>
    >({});

    // --- Derived Calculations ---
    const zScore = useMemo(() => getZScore(serviceLevel), [serviceLevel]);

    const safetyStock = useMemo(
        () =>
            calculateSafetyStock(
                demandStdDev,
                avgLeadTime,
                leadTimeStdDev,
                avgDailyUsage,
                zScore,
            ),
        [demandStdDev, avgLeadTime, leadTimeStdDev, avgDailyUsage, zScore],
    );

    const rop = useMemo(
        () => calculateROP(avgDailyUsage, avgLeadTime, safetyStock),
        [avgDailyUsage, avgLeadTime, safetyStock],
    );

    const leadTimeDemand = avgDailyUsage * avgLeadTime;
    const currentStock = invRecord?.quantity || 0;

    // Calculate On Order (Mocked logic from inventoryService but local here)
    const onOrder =
        (item?.id.charCodeAt(0) || 0) % 2 === 0 ? Math.floor(rop * 0.8) : 0;
    const daysCover =
        avgDailyUsage > 0 ? (currentStock / avgDailyUsage).toFixed(1) : '∞';

    // Vendor TCO Analysis using Landed Cost logic
    const vendorAnalysis = useMemo(() => {
        if (!item) return [];
        const relevantSupplierItems = SUPPLIER_ITEMS.filter(
            (si) => si.itemId === item.id,
        );

        const analysis = relevantSupplierItems
            .map((si) => {
                const supplier = SUPPLIERS.find((s) => s.id === si.supplierId);
                if (!supplier) return null;
                // Default to MOQ for comparison
                return calcLandedCostPerUnit(
                    item,
                    supplier,
                    si,
                    si.minOrderQty,
                );
            })
            .filter((x): x is LandedCostBreakdown => x !== null);

        // Mark best value
        const minTotal = Math.min(...analysis.map((a) => a.totalPerUnit));
        analysis.forEach(
            (a) => (a.isBestValue = Math.abs(a.totalPerUnit - minTotal) < 0.01),
        );

        return analysis.sort((a, b) => a.totalPerUnit - b.totalPerUnit);
    }, [item]);

    // Forecast Data
    const forecastData = useMemo(
        () => generateMockForecast(14, avgDailyUsage, demandStdDev, 1.1),
        [avgDailyUsage, demandStdDev],
    );

    if (!item || !location) {
        return (
            <div className="p-8 text-center">Item or Location not found</div>
        );
    }

    const handleQuickOrder = async (v: CostBreakdown, qty: number) => {
        const supplier = SUPPLIERS.find((s) => s.id === v.supplierId);
        if (!supplier || !locationId || !item) return;

        setOrderingStatus((prev) => ({ ...prev, [v.supplierId]: 'ordering' }));

        // Simulate API delay for UX
        await new Promise((resolve) => setTimeout(resolve, 800));

        placeOrder(locationId, item, qty, supplier);

        setOrderingStatus((prev) => ({ ...prev, [v.supplierId]: 'success' }));

        // Reset after success message
        setTimeout(() => {
            setOrderingStatus((prev) => ({ ...prev, [v.supplierId]: 'idle' }));
            // Optional: Reset quantity to default? keeping it might be better for repetitive tasks
        }, 2500);
    };

    // --- Helper for Comparison Rendering ---
    const renderComparisonModal = () => {
        if (!comparingSupplierId) return null;
        const supplier = SUPPLIERS.find((s) => s.id === comparingSupplierId);
        const sItem = SUPPLIER_ITEMS.find(
            (si) =>
                si.supplierId === comparingSupplierId && si.itemId === itemId,
        );
        if (!supplier || !sItem) return null;

        // Calculate Scenario Values
        const scenarioLeadTime = sItem.deliveryDays;
        // Note: In a real app, vendor reliability might also affect leadTimeStdDev. We keep it simple here.
        const scenarioSS = calculateSafetyStock(
            demandStdDev,
            scenarioLeadTime,
            leadTimeStdDev,
            avgDailyUsage,
            zScore,
        );
        const scenarioROP = calculateROP(
            avgDailyUsage,
            scenarioLeadTime,
            scenarioSS,
        );

        // Deltas
        const ssDelta = scenarioSS - safetyStock;
        const ropDelta = scenarioROP - rop;
        const leadTimeDelta = scenarioLeadTime - avgLeadTime;

        // Cost Impact (Monthly Est)
        // Holding Cost Impact due to SS change
        const monthlyHoldingImpact = ssDelta * item.storageCostPerUnit;

        return (
            <div className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-stone-900/60 p-4 backdrop-blur-sm duration-200 fade-in">
                <div className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    {/* Modal Header */}
                    <div className="flex items-center justify-between border-b border-stone-100 bg-stone-50 p-5">
                        <div>
                            <h3 className="flex items-center gap-2 text-lg font-bold text-stone-900">
                                <Scale className="text-amber-600" size={20} />{' '}
                                Scenario Analysis
                            </h3>
                            <p className="mt-1 text-xs text-stone-500">
                                Comparing current policy vs. exclusive sourcing
                                from{' '}
                                <span className="font-bold text-stone-700">
                                    {supplier.name}
                                </span>
                            </p>
                        </div>
                        <button
                            onClick={() => setComparingSupplierId(null)}
                            className="rounded-full p-2 text-stone-500 transition-colors hover:bg-stone-200"
                        >
                            <X size={20} />
                        </button>
                    </div>

                    <div className="overflow-y-auto p-6">
                        <div className="relative grid grid-cols-1 gap-8 md:grid-cols-2">
                            {/* Vertical Divider (Desktop) */}
                            <div className="absolute top-0 bottom-0 left-1/2 hidden w-px -translate-x-1/2 bg-stone-200 md:block"></div>

                            {/* Left: Current State */}
                            <div className="space-y-4">
                                <div className="mb-4 flex items-center gap-2">
                                    <span className="rounded bg-stone-200 px-2 py-1 text-[10px] font-bold tracking-wider text-stone-600 uppercase">
                                        Baseline
                                    </span>
                                    <span className="font-bold text-stone-900">
                                        Current Policy
                                    </span>
                                </div>

                                <div className="space-y-3 rounded-xl border border-stone-200 bg-stone-50 p-4">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-stone-500">
                                            Avg Lead Time
                                        </span>
                                        <span className="font-mono font-bold">
                                            {avgLeadTime} days
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-stone-500">
                                            Reorder Point
                                        </span>
                                        <span className="font-mono font-bold">
                                            {rop} units
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-stone-500">
                                            Safety Stock
                                        </span>
                                        <span className="font-mono font-bold text-amber-600">
                                            {safetyStock} units
                                        </span>
                                    </div>
                                </div>

                                <div className="text-xs text-stone-400 italic">
                                    Based on your current manual inputs in the
                                    simulation sidebar.
                                </div>
                            </div>

                            {/* Right: Future State */}
                            <div className="space-y-4">
                                <div className="mb-4 flex items-center gap-2">
                                    <span className="rounded bg-amber-100 px-2 py-1 text-[10px] font-bold tracking-wider text-amber-700 uppercase">
                                        Scenario
                                    </span>
                                    <span className="font-bold text-stone-900">
                                        With {supplier.name}
                                    </span>
                                </div>

                                <div className="space-y-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-stone-600">
                                            Vendor Lead Time
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-bold">
                                                {scenarioLeadTime} days
                                            </span>
                                            {leadTimeDelta !== 0 && (
                                                <span
                                                    className={`rounded px-1.5 py-0.5 text-[10px] font-bold ${leadTimeDelta > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}`}
                                                >
                                                    {leadTimeDelta > 0
                                                        ? '+'
                                                        : ''}
                                                    {leadTimeDelta}d
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-stone-600">
                                            New ROP
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-bold">
                                                {scenarioROP} units
                                            </span>
                                            {ropDelta !== 0 && (
                                                <span
                                                    className={`rounded px-1.5 py-0.5 text-[10px] font-bold ${ropDelta > 0 ? 'bg-stone-200 text-stone-700' : 'bg-stone-200 text-stone-700'}`}
                                                >
                                                    {ropDelta > 0 ? '+' : ''}
                                                    {ropDelta}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-stone-600">
                                            Req. Safety Stock
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-bold text-amber-700">
                                                {scenarioSS} units
                                            </span>
                                            {ssDelta !== 0 && (
                                                <span
                                                    className={`rounded px-1.5 py-0.5 text-[10px] font-bold ${ssDelta > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}`}
                                                >
                                                    {ssDelta > 0 ? '+' : ''}
                                                    {ssDelta}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Impact Summary */}
                        <div className="mt-8 rounded-xl bg-stone-900 p-6 text-white">
                            <h4 className="mb-4 text-sm font-bold tracking-wider text-stone-400 uppercase">
                                Projected Business Impact
                            </h4>
                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <p className="mb-1 text-xs text-stone-400">
                                        Holding Cost Change
                                    </p>
                                    <div className="flex items-baseline gap-2">
                                        <span
                                            className={`text-2xl font-bold ${monthlyHoldingImpact > 0 ? 'text-rose-400' : 'text-emerald-400'}`}
                                        >
                                            {monthlyHoldingImpact > 0
                                                ? '+'
                                                : ''}
                                            ${monthlyHoldingImpact.toFixed(2)}
                                        </span>
                                        <span className="text-xs text-stone-500">
                                            / month
                                        </span>
                                    </div>
                                    <p className="mt-1 text-[10px] text-stone-500">
                                        {monthlyHoldingImpact > 0
                                            ? `Slower delivery requires keeping ${ssDelta} more units of safety stock to maintain ${(serviceLevel * 100).toFixed(1)}% service level.`
                                            : `Faster delivery allows reducing safety stock by ${Math.abs(ssDelta)} units while maintaining service level.`}
                                    </p>
                                </div>
                                <div>
                                    <p className="mb-1 text-xs text-stone-400">
                                        Unit Price Impact
                                    </p>
                                    <div className="flex items-baseline gap-2">
                                        <span className="text-2xl font-bold text-white">
                                            ${sItem.pricePerUnit.toFixed(2)}
                                        </span>
                                        <span className="text-xs text-stone-500">
                                            / unit
                                        </span>
                                    </div>
                                    <p className="mt-1 text-[10px] text-stone-500">
                                        Vendor Base Price. Does not include bulk
                                        volume discounts or shipping fees.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-stone-100 bg-stone-50 p-4">
                        <button
                            onClick={() => setComparingSupplierId(null)}
                            className="rounded-lg border border-stone-200 bg-white px-4 py-2 text-sm font-bold text-stone-700 transition-colors hover:bg-stone-50"
                        >
                            Close Analysis
                        </button>
                        <Link
                            to={`/ordering?locId=${locationId}&itemId=${itemId}`}
                            className="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-amber-600/20 transition-all hover:bg-amber-700"
                        >
                            Proceed with {supplier.name}
                        </Link>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <div className="relative animate-in space-y-6 pb-20 duration-500 fade-in slide-in-from-bottom-4">
            {/* --- Breadcrumb & Header --- */}
            <div className="flex flex-col gap-4">
                <div className="flex items-center gap-2 text-sm text-stone-500">
                    <Link
                        to="/inventory"
                        className="flex items-center gap-1 transition-colors hover:text-amber-600"
                    >
                        <ArrowLeft size={14} /> Back to Inventory
                    </Link>
                    <span>/</span>
                    <span>{location.name}</span>
                    <span>/</span>
                    <span className="font-semibold text-stone-900">
                        {item.name}
                    </span>
                </div>

                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-stone-100 shadow-sm">
                            <ProductIcon
                                category={item.category}
                                className="h-12 w-12"
                            />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold text-stone-900">
                                {item.name}
                            </h1>
                            <div className="mt-1 flex items-center gap-2">
                                <span className="rounded-md border border-stone-200 bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">
                                    {item.category}
                                </span>
                                <span className="text-xs text-stone-400">
                                    •
                                </span>
                                <span className="text-xs text-stone-500">
                                    {item.unit}
                                </span>
                                <span className="text-xs text-stone-400">
                                    •
                                </span>
                                <span className="flex items-center gap-1 text-xs text-stone-500">
                                    <DollarSign size={10} />{' '}
                                    {item.storageCostPerUnit}/unit storage
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-sm text-stone-500">
                            Managing Policy For
                        </div>
                        <div className="flex items-center justify-end gap-2 font-bold text-stone-900">
                            <Package size={16} className="text-amber-600" />{' '}
                            {location.name}
                        </div>
                    </div>
                </div>
            </div>

            {/* --- Current Position KPIs --- */}
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div className="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                    <div className="mb-1 text-xs font-bold tracking-wider text-stone-500 uppercase">
                        On Hand
                    </div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-stone-900">
                            {currentStock}
                        </span>
                        <span className="text-sm text-stone-400">
                            {item.unit}
                        </span>
                    </div>
                    <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-stone-100">
                        <div
                            className="h-full rounded-full bg-emerald-500"
                            style={{
                                width: `${Math.min(100, (currentStock / item.bulkThreshold) * 100)}%`,
                            }}
                        ></div>
                    </div>
                    <div className="mt-1 text-right text-[10px] text-stone-400">
                        {Math.round((currentStock / item.bulkThreshold) * 100)}%
                        of Max Capacity
                    </div>
                </div>

                <div className="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                    <div className="mb-1 text-xs font-bold tracking-wider text-stone-500 uppercase">
                        On Order
                    </div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-blue-600">
                            {onOrder}
                        </span>
                        <span className="text-sm text-blue-400">incoming</span>
                    </div>
                    <div className="mt-3 flex items-center gap-1 text-xs text-stone-500">
                        <Truck size={12} /> Expected in ~
                        {Math.ceil(avgLeadTime)} days
                    </div>
                </div>

                <div className="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                    <div className="mb-1 text-xs font-bold tracking-wider text-stone-500 uppercase">
                        Days Cover
                    </div>
                    <div className="flex items-baseline gap-2">
                        <span
                            className={`text-3xl font-bold ${Number(daysCover) < 3 ? 'text-rose-600' : 'text-stone-900'}`}
                        >
                            {daysCover}
                        </span>
                        <span className="text-sm text-stone-400">days</span>
                    </div>
                    <div className="mt-3 flex items-center gap-1 text-xs text-stone-500">
                        <TrendingUp size={12} /> Based on {avgDailyUsage}{' '}
                        avg/day
                    </div>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-stone-800 bg-stone-900 p-4 text-white shadow-sm">
                    <div className="absolute top-0 right-0 p-4 opacity-10">
                        <Calculator size={48} />
                    </div>
                    <div className="mb-1 text-xs font-bold tracking-wider text-stone-400 uppercase">
                        Reorder Point
                    </div>
                    <div className="relative z-10 flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-amber-500">
                            {rop}
                        </span>
                        <span className="text-sm text-stone-500">trigger</span>
                    </div>
                    <div className="relative z-10 mt-3 flex items-center gap-1 text-xs text-stone-400">
                        Status:{' '}
                        {currentStock <= rop ? (
                            <span className="flex items-center gap-1 font-bold text-rose-400">
                                <AlertTriangle size={10} /> ORDER NOW
                            </span>
                        ) : (
                            <span className="text-emerald-400">OK</span>
                        )}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* --- Left Column: The Truth Engine (Math & Sliders) --- */}
                <div className="space-y-6 lg:col-span-1">
                    {/* Formula Card */}
                    <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-stone-200 bg-stone-50 p-4">
                            <h3 className="flex items-center gap-2 font-bold text-stone-900">
                                <Layers size={18} className="text-amber-600" />
                                Reorder Policy
                            </h3>
                            {!isEditingPolicy && (
                                <button
                                    onClick={() => setIsEditingPolicy(true)}
                                    className="flex items-center gap-1.5 rounded-md border border-stone-200 bg-white px-2.5 py-1.5 text-xs font-medium text-stone-600 shadow-sm transition-colors hover:border-amber-500 hover:text-amber-600"
                                >
                                    <Edit2 size={12} /> Edit Inputs
                                </button>
                            )}
                        </div>

                        <div className="space-y-6 p-6">
                            {/* Visual Formula Decomposition */}
                            <div className="flex items-center justify-between text-center font-mono text-sm">
                                <div className="flex flex-col gap-1">
                                    <span className="text-[10px] text-stone-400 uppercase">
                                        Lead Time Demand
                                    </span>
                                    <span className="rounded bg-stone-100 px-2 py-1 font-bold text-stone-700">
                                        {Math.round(leadTimeDemand)}
                                    </span>
                                </div>
                                <div className="pb-5 text-stone-300">+</div>
                                <div className="flex flex-col gap-1">
                                    <span className="text-[10px] font-bold text-amber-500 uppercase">
                                        Safety Stock
                                    </span>
                                    <span className="rounded border border-amber-100 bg-amber-50 px-2 py-1 font-bold text-amber-600">
                                        {safetyStock}
                                    </span>
                                </div>
                                <div className="pb-5 text-stone-300">=</div>
                                <div className="flex flex-col gap-1">
                                    <span className="text-[10px] font-bold text-stone-900 uppercase">
                                        ROP
                                    </span>
                                    <span className="rounded bg-stone-900 px-3 py-1 font-bold text-white shadow-md">
                                        {rop}
                                    </span>
                                </div>
                            </div>

                            <hr className="border-stone-100" />

                            {isEditingPolicy ? (
                                <div className="animate-in space-y-5 duration-300 fade-in slide-in-from-top-2">
                                    <div className="mb-2 flex items-center justify-between">
                                        <h4 className="text-xs font-bold tracking-wide text-stone-900 uppercase">
                                            Adjust Parameters
                                        </h4>
                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => {
                                                    setServiceLevel(0.95);
                                                    setAvgLeadTime(3);
                                                    setAvgDailyUsage(25);
                                                }}
                                                className="rounded p-1.5 text-stone-400 transition-colors hover:bg-stone-100 hover:text-stone-600"
                                                title="Reset to Defaults"
                                            >
                                                <RotateCcw size={14} />
                                            </button>
                                            <button
                                                onClick={() =>
                                                    setIsEditingPolicy(false)
                                                }
                                                className="flex items-center gap-1 rounded-md bg-stone-900 px-3 py-1 text-xs font-bold text-white shadow-md transition-colors hover:bg-stone-800"
                                            >
                                                <Check size={12} /> Apply
                                            </button>
                                        </div>
                                    </div>

                                    {/* Slider: Service Level */}
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-xs">
                                            <span className="font-medium text-stone-600">
                                                Target Service Level
                                            </span>
                                            <span className="font-bold text-stone-900">
                                                {(serviceLevel * 100).toFixed(
                                                    1,
                                                )}
                                                %
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min="0.80"
                                            max="0.999"
                                            step="0.001"
                                            value={serviceLevel}
                                            onChange={(e) =>
                                                setServiceLevel(
                                                    parseFloat(e.target.value),
                                                )
                                            }
                                            className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-stone-200 accent-amber-600"
                                        />
                                    </div>

                                    {/* Slider: Avg Daily Usage */}
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-xs">
                                            <span className="font-medium text-stone-600">
                                                Avg Daily Demand
                                            </span>
                                            <span className="font-bold text-stone-900">
                                                {avgDailyUsage} units
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min="5"
                                            max="100"
                                            step="1"
                                            value={avgDailyUsage}
                                            onChange={(e) =>
                                                setAvgDailyUsage(
                                                    parseInt(e.target.value),
                                                )
                                            }
                                            className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-stone-200 accent-stone-600"
                                        />
                                    </div>

                                    {/* Slider: Lead Time */}
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-xs">
                                            <span className="font-medium text-stone-600">
                                                Vendor Lead Time
                                            </span>
                                            <span className="font-bold text-stone-900">
                                                {avgLeadTime} days
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min="1"
                                            max="14"
                                            step="0.5"
                                            value={avgLeadTime}
                                            onChange={(e) =>
                                                setAvgLeadTime(
                                                    parseFloat(e.target.value),
                                                )
                                            }
                                            className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-stone-200 accent-stone-600"
                                        />
                                    </div>
                                </div>
                            ) : (
                                <div className="grid grid-cols-3 gap-3">
                                    <div
                                        className="group cursor-pointer rounded-lg border border-stone-100 bg-stone-50 p-3 text-center transition-colors hover:border-stone-200"
                                        onClick={() => setIsEditingPolicy(true)}
                                    >
                                        <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase group-hover:text-amber-600">
                                            Service Level
                                        </div>
                                        <div className="text-lg font-bold text-stone-800">
                                            {(serviceLevel * 100).toFixed(1)}%
                                        </div>
                                    </div>
                                    <div
                                        className="group cursor-pointer rounded-lg border border-stone-100 bg-stone-50 p-3 text-center transition-colors hover:border-stone-200"
                                        onClick={() => setIsEditingPolicy(true)}
                                    >
                                        <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase group-hover:text-amber-600">
                                            Avg Demand
                                        </div>
                                        <div className="text-lg font-bold text-stone-800">
                                            {avgDailyUsage}{' '}
                                            <span className="text-xs font-medium text-stone-400">
                                                /day
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        className="group cursor-pointer rounded-lg border border-stone-100 bg-stone-50 p-3 text-center transition-colors hover:border-stone-200"
                                        onClick={() => setIsEditingPolicy(true)}
                                    >
                                        <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase group-hover:text-amber-600">
                                            Lead Time
                                        </div>
                                        <div className="text-lg font-bold text-stone-800">
                                            {avgLeadTime}{' '}
                                            <span className="text-xs font-medium text-stone-400">
                                                days
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <div className="mt-4 rounded-lg border border-amber-100 bg-amber-50 p-3">
                                <div className="flex items-start gap-2">
                                    <TrendingUp
                                        size={16}
                                        className="mt-0.5 text-amber-600"
                                    />
                                    <div>
                                        <p className="text-xs font-bold text-amber-900">
                                            Impact Analysis
                                        </p>
                                        <p className="mt-1 text-[11px] leading-snug text-amber-800">
                                            Increasing service level to 99.9%
                                            would require holding{' '}
                                            <strong>
                                                {Math.ceil(
                                                    calculateSafetyStock(
                                                        demandStdDev,
                                                        avgLeadTime,
                                                        leadTimeStdDev,
                                                        avgDailyUsage,
                                                        3.09,
                                                    ) - safetyStock,
                                                )}
                                            </strong>{' '}
                                            additional units of safety stock.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* --- Right Column: Visualization & Context --- */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Tab Navigation */}
                    <div className="flex gap-4 border-b border-stone-200">
                        <button
                            onClick={() => setActiveTab('math')}
                            className={`relative pb-3 text-sm font-medium transition-colors ${activeTab === 'math' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                        >
                            Demand Forecast
                            {activeTab === 'math' && (
                                <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-stone-900"></div>
                            )}
                        </button>
                        <button
                            onClick={() => setActiveTab('vendors')}
                            className={`relative pb-3 text-sm font-medium transition-colors ${activeTab === 'vendors' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                        >
                            Vendor TCO Analysis
                            {activeTab === 'vendors' && (
                                <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-stone-900"></div>
                            )}
                        </button>
                        {item.isPerishable && (
                            <button
                                onClick={() => setActiveTab('expiry')}
                                className={`relative pb-3 text-sm font-medium transition-colors ${activeTab === 'expiry' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                            >
                                Expiry / FEFO
                                {activeTab === 'expiry' && (
                                    <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-stone-900"></div>
                                )}
                            </button>
                        )}
                    </div>

                    {/* Content Area */}
                    <div className="min-h-[400px] rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                        {activeTab === 'math' && (
                            <div className="flex h-full flex-col space-y-4">
                                <div className="mb-2 flex items-center justify-between">
                                    <h4 className="font-bold text-stone-900">
                                        14-Day Demand Forecast
                                    </h4>
                                    <div className="flex items-center gap-4 text-xs">
                                        <span className="flex items-center gap-1 text-stone-500">
                                            <div className="h-2 w-2 rounded-full bg-amber-500"></div>{' '}
                                            Forecast
                                        </span>
                                        <span className="flex items-center gap-1 text-stone-500">
                                            <div className="h-2 w-2 rounded-full bg-stone-200"></div>{' '}
                                            Confidence Interval
                                        </span>
                                    </div>
                                </div>
                                <div className="min-h-[300px] flex-1">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <AreaChart
                                            data={forecastData}
                                            margin={{
                                                top: 10,
                                                right: 10,
                                                left: -20,
                                                bottom: 0,
                                            }}
                                        >
                                            <defs>
                                                <linearGradient
                                                    id="colorPredicted"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor="#f59e0b"
                                                        stopOpacity={0.8}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor="#f59e0b"
                                                        stopOpacity={0}
                                                    />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                vertical={false}
                                                stroke="#e7e5e4"
                                            />
                                            <XAxis
                                                dataKey="date"
                                                tickLine={false}
                                                axisLine={false}
                                                fontSize={12}
                                                stroke="#78716c"
                                                dy={10}
                                            />
                                            <YAxis
                                                tickLine={false}
                                                axisLine={false}
                                                fontSize={12}
                                                stroke="#78716c"
                                            />
                                            <Tooltip
                                                contentStyle={{
                                                    borderRadius: '12px',
                                                    border: 'none',
                                                    boxShadow:
                                                        '0 10px 15px -3px rgb(0 0 0 / 0.1)',
                                                }}
                                                cursor={{
                                                    stroke: '#a8a29e',
                                                    strokeWidth: 1,
                                                    strokeDasharray: '4 4',
                                                }}
                                            />
                                            <ReferenceLine
                                                y={rop}
                                                label="ROP"
                                                stroke="red"
                                                strokeDasharray="3 3"
                                            />
                                            {/* Confidence Interval using stacked areas logic or just simple overlay */}
                                            <Area
                                                type="monotone"
                                                dataKey="upperBound"
                                                stroke="none"
                                                fill="#e7e5e4"
                                                fillOpacity={0.5}
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="predicted"
                                                stroke="#d97706"
                                                strokeWidth={3}
                                                fill="url(#colorPredicted)"
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                                <div className="flex gap-2 rounded-lg bg-stone-50 p-3 text-xs text-stone-500">
                                    <Info
                                        size={16}
                                        className="flex-shrink-0 text-stone-400"
                                    />
                                    <p>
                                        Forecast generated using seasonal ARIMA
                                        model. The red line indicates your
                                        current Reorder Point. If the forecast
                                        curve crosses it consistently, consider
                                        increasing Safety Stock.
                                    </p>
                                </div>
                            </div>
                        )}

                        {activeTab === 'vendors' && (
                            <div className="animate-in space-y-4 fade-in">
                                <h4 className="mb-4 font-bold text-stone-900">
                                    True Cost of Ownership Analysis
                                </h4>
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse text-left">
                                        <thead>
                                            <tr className="border-b border-stone-200 text-xs text-stone-400 uppercase">
                                                <th className="pb-3 font-semibold">
                                                    Vendor
                                                </th>
                                                <th className="pb-3 text-right font-semibold">
                                                    Base Price
                                                </th>
                                                <th className="pb-3 text-right font-semibold">
                                                    Duties & Ship
                                                </th>
                                                <th className="pb-3 text-right font-semibold">
                                                    Risk & Hold
                                                </th>
                                                <th className="pb-3 text-right font-semibold">
                                                    Total / Unit
                                                </th>
                                                <th className="pb-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-stone-100">
                                            {vendorAnalysis.map((v) => {
                                                const sItem =
                                                    SUPPLIER_ITEMS.find(
                                                        (si) =>
                                                            si.supplierId ===
                                                                v.supplierId &&
                                                            si.itemId ===
                                                                itemId,
                                                    );
                                                const moq =
                                                    sItem?.minOrderQty || 1;
                                                const currentQty =
                                                    orderQuantities[
                                                        v.supplierId
                                                    ] ?? moq;

                                                return (
                                                    <React.Fragment
                                                        key={v.supplierId}
                                                    >
                                                        <tr
                                                            onClick={() =>
                                                                setExpandedVendorId(
                                                                    expandedVendorId ===
                                                                        v.supplierId
                                                                        ? null
                                                                        : v.supplierId,
                                                                )
                                                            }
                                                            className={`group cursor-pointer transition-colors ${v.isBestValue ? 'bg-emerald-50/30' : 'hover:bg-stone-50'}`}
                                                        >
                                                            <td className="py-4 pl-2">
                                                                <div className="flex items-center gap-3">
                                                                    <button className="rounded p-1 text-stone-400 hover:bg-stone-200">
                                                                        {expandedVendorId ===
                                                                        v.supplierId ? (
                                                                            <ChevronUp
                                                                                size={
                                                                                    14
                                                                                }
                                                                            />
                                                                        ) : (
                                                                            <ChevronDown
                                                                                size={
                                                                                    14
                                                                                }
                                                                            />
                                                                        )}
                                                                    </button>
                                                                    <div>
                                                                        <div className="font-bold text-stone-900">
                                                                            {
                                                                                v.supplierName
                                                                            }
                                                                        </div>
                                                                        {v.isBestValue && (
                                                                            <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                                                                BEST
                                                                                VALUE
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td className="py-4 text-right text-stone-600">
                                                                $
                                                                {v.unitPrice.toFixed(
                                                                    2,
                                                                )}
                                                            </td>
                                                            <td className="py-4 text-right text-xs text-stone-500">
                                                                +$
                                                                {(
                                                                    v.deliveryFeePerUnit +
                                                                    v.dutiesPerUnit
                                                                ).toFixed(2)}
                                                            </td>
                                                            <td className="py-4 text-right text-xs text-stone-500">
                                                                +$
                                                                {(
                                                                    v.stockoutRiskCost +
                                                                    v.holdingCost
                                                                ).toFixed(2)}
                                                            </td>
                                                            <td className="py-4 text-right">
                                                                <div className="font-bold text-stone-900">
                                                                    $
                                                                    {v.totalPerUnit.toFixed(
                                                                        2,
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="py-4 text-right">
                                                                <div
                                                                    className="flex items-center justify-end gap-2 opacity-0 transition-opacity group-hover:opacity-100"
                                                                    onClick={(
                                                                        e,
                                                                    ) =>
                                                                        e.stopPropagation()
                                                                    }
                                                                >
                                                                    {/* Comparison Button */}
                                                                    <button
                                                                        onClick={() =>
                                                                            setComparingSupplierId(
                                                                                v.supplierId,
                                                                            )
                                                                        }
                                                                        className="rounded-lg p-2 text-stone-400 transition-colors hover:bg-stone-100 hover:text-stone-600"
                                                                        title="Simulate Scenario"
                                                                    >
                                                                        <ArrowRightLeft
                                                                            size={
                                                                                16
                                                                            }
                                                                        />
                                                                    </button>

                                                                    {/* Vertical Divider */}
                                                                    <div className="mx-1 h-6 w-px bg-stone-200"></div>

                                                                    {/* Quick Order Input Group */}
                                                                    <div className="flex items-center gap-1 rounded-lg border border-stone-200 bg-white p-0.5 shadow-sm transition-colors group-hover:border-amber-300">
                                                                        <input
                                                                            type="number"
                                                                            min={
                                                                                moq
                                                                            }
                                                                            value={
                                                                                currentQty
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                setOrderQuantities(
                                                                                    (
                                                                                        prev,
                                                                                    ) => ({
                                                                                        ...prev,
                                                                                        [v.supplierId]:
                                                                                            parseInt(
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            ),
                                                                                    }),
                                                                                )
                                                                            }
                                                                            onClick={(
                                                                                e,
                                                                            ) =>
                                                                                e.stopPropagation()
                                                                            }
                                                                            className="w-16 appearance-none bg-transparent px-2 py-1 text-right text-xs font-bold outline-none"
                                                                        />
                                                                        <button
                                                                            onClick={(
                                                                                e,
                                                                            ) => {
                                                                                e.stopPropagation();
                                                                                handleQuickOrder(
                                                                                    v,
                                                                                    currentQty,
                                                                                );
                                                                            }}
                                                                            disabled={
                                                                                orderingStatus[
                                                                                    v
                                                                                        .supplierId
                                                                                ] ===
                                                                                    'ordering' ||
                                                                                orderingStatus[
                                                                                    v
                                                                                        .supplierId
                                                                                ] ===
                                                                                    'success' ||
                                                                                currentQty <
                                                                                    moq
                                                                            }
                                                                            className={`flex h-7 items-center justify-center rounded-md px-3 transition-all ${
                                                                                orderingStatus[
                                                                                    v
                                                                                        .supplierId
                                                                                ] ===
                                                                                'success'
                                                                                    ? 'bg-emerald-500 text-white'
                                                                                    : orderingStatus[
                                                                                            v
                                                                                                .supplierId
                                                                                        ] ===
                                                                                        'ordering'
                                                                                      ? 'bg-stone-100 text-stone-400'
                                                                                      : 'bg-amber-100 text-amber-700 hover:bg-amber-500 hover:text-white'
                                                                            } ${currentQty < moq ? 'cursor-not-allowed opacity-50' : ''}`}
                                                                            title={
                                                                                currentQty <
                                                                                moq
                                                                                    ? `Minimum Order Qty: ${moq}`
                                                                                    : 'Place Order'
                                                                            }
                                                                        >
                                                                            {orderingStatus[
                                                                                v
                                                                                    .supplierId
                                                                            ] ===
                                                                            'ordering' ? (
                                                                                <Loader2
                                                                                    size={
                                                                                        12
                                                                                    }
                                                                                    className="animate-spin"
                                                                                />
                                                                            ) : orderingStatus[
                                                                                  v
                                                                                      .supplierId
                                                                              ] ===
                                                                              'success' ? (
                                                                                <Check
                                                                                    size={
                                                                                        14
                                                                                    }
                                                                                />
                                                                            ) : (
                                                                                <ShoppingCart
                                                                                    size={
                                                                                        14
                                                                                    }
                                                                                />
                                                                            )}
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        {expandedVendorId ===
                                                            v.supplierId && (
                                                            <tr>
                                                                <td
                                                                    colSpan={6}
                                                                    className="border-none p-0"
                                                                >
                                                                    <div className="animate-in border-y border-stone-200 bg-stone-50 p-4 shadow-inner duration-200 slide-in-from-top-2">
                                                                        <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                                                                            <div className="rounded-lg border border-stone-200 bg-white p-3 shadow-sm">
                                                                                <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase">
                                                                                    Unit
                                                                                    Price
                                                                                </div>
                                                                                <div className="text-sm font-bold text-stone-900">
                                                                                    $
                                                                                    {v.unitPrice.toFixed(
                                                                                        2,
                                                                                    )}
                                                                                </div>
                                                                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-stone-100">
                                                                                    <div
                                                                                        className="h-full bg-stone-500"
                                                                                        style={{
                                                                                            width: '100%',
                                                                                        }}
                                                                                    ></div>
                                                                                </div>
                                                                            </div>
                                                                            <div className="rounded-lg border border-stone-200 bg-white p-3 shadow-sm">
                                                                                <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase">
                                                                                    Logistics
                                                                                    &
                                                                                    Duties
                                                                                </div>
                                                                                <div className="text-sm font-bold text-stone-900">
                                                                                    $
                                                                                    {(
                                                                                        v.deliveryFeePerUnit +
                                                                                        v.dutiesPerUnit
                                                                                    ).toFixed(
                                                                                        2,
                                                                                    )}
                                                                                </div>
                                                                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-stone-100">
                                                                                    <div
                                                                                        className="h-full bg-blue-400"
                                                                                        style={{
                                                                                            width: `${((v.deliveryFeePerUnit + v.dutiesPerUnit) / v.totalPerUnit) * 100}%`,
                                                                                        }}
                                                                                    ></div>
                                                                                </div>
                                                                            </div>
                                                                            <div className="rounded-lg border border-stone-200 bg-white p-3 shadow-sm">
                                                                                <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase">
                                                                                    Holding
                                                                                    Cost
                                                                                </div>
                                                                                <div className="text-sm font-bold text-stone-900">
                                                                                    $
                                                                                    {v.holdingCost.toFixed(
                                                                                        2,
                                                                                    )}
                                                                                </div>
                                                                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-stone-100">
                                                                                    <div
                                                                                        className="h-full bg-amber-400"
                                                                                        style={{
                                                                                            width: `${(v.holdingCost / v.totalPerUnit) * 100}%`,
                                                                                        }}
                                                                                    ></div>
                                                                                </div>
                                                                            </div>
                                                                            <div className="rounded-lg border border-stone-200 bg-white p-3 shadow-sm">
                                                                                <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase">
                                                                                    Risk
                                                                                    Premium
                                                                                </div>
                                                                                <div className="text-sm font-bold text-stone-900">
                                                                                    $
                                                                                    {v.stockoutRiskCost.toFixed(
                                                                                        2,
                                                                                    )}
                                                                                </div>
                                                                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-stone-100">
                                                                                    <div
                                                                                        className="h-full bg-rose-400"
                                                                                        style={{
                                                                                            width: `${(v.stockoutRiskCost / v.totalPerUnit) * 100}%`,
                                                                                        }}
                                                                                    ></div>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        {/* Visual Chart Section */}
                                                                        <div className="mb-6 px-4">
                                                                            <div className="h-16 w-full">
                                                                                <ResponsiveContainer
                                                                                    width="100%"
                                                                                    height="100%"
                                                                                >
                                                                                    <BarChart
                                                                                        layout="vertical"
                                                                                        data={[
                                                                                            {
                                                                                                name: 'Cost',
                                                                                                'Base Price':
                                                                                                    v.unitPrice,
                                                                                                'Duties & Ship':
                                                                                                    v.deliveryFeePerUnit +
                                                                                                    v.dutiesPerUnit,
                                                                                                Holding:
                                                                                                    v.holdingCost,
                                                                                                Risk: v.stockoutRiskCost,
                                                                                            },
                                                                                        ]}
                                                                                    >
                                                                                        <XAxis
                                                                                            type="number"
                                                                                            hide
                                                                                        />
                                                                                        <YAxis
                                                                                            type="category"
                                                                                            dataKey="name"
                                                                                            hide
                                                                                        />
                                                                                        <Tooltip
                                                                                            cursor={{
                                                                                                fill: 'transparent',
                                                                                            }}
                                                                                            formatter={(
                                                                                                value: number,
                                                                                            ) => [
                                                                                                `$${value.toFixed(2)}`,
                                                                                                '',
                                                                                            ]}
                                                                                            contentStyle={{
                                                                                                borderRadius:
                                                                                                    '8px',
                                                                                                border: 'none',
                                                                                                boxShadow:
                                                                                                    '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                                                                                fontSize:
                                                                                                    '12px',
                                                                                            }}
                                                                                        />
                                                                                        <Legend
                                                                                            iconSize={
                                                                                                10
                                                                                            }
                                                                                            wrapperStyle={{
                                                                                                fontSize:
                                                                                                    '11px',
                                                                                                paddingTop:
                                                                                                    '6px',
                                                                                            }}
                                                                                        />
                                                                                        <Bar
                                                                                            dataKey="Base Price"
                                                                                            stackId="a"
                                                                                            fill="#78716c"
                                                                                            radius={[
                                                                                                4,
                                                                                                0,
                                                                                                0,
                                                                                                4,
                                                                                            ]}
                                                                                        />
                                                                                        <Bar
                                                                                            dataKey="Duties & Ship"
                                                                                            stackId="a"
                                                                                            fill="#60a5fa"
                                                                                        />
                                                                                        <Bar
                                                                                            dataKey="Holding"
                                                                                            stackId="a"
                                                                                            fill="#fbbf24"
                                                                                        />
                                                                                        <Bar
                                                                                            dataKey="Risk"
                                                                                            stackId="a"
                                                                                            fill="#fb7185"
                                                                                            radius={[
                                                                                                0,
                                                                                                4,
                                                                                                4,
                                                                                                0,
                                                                                            ]}
                                                                                        />
                                                                                    </BarChart>
                                                                                </ResponsiveContainer>
                                                                            </div>
                                                                        </div>

                                                                        <div className="text-center text-xs text-stone-400">
                                                                            Detailed
                                                                            Total:{' '}
                                                                            <span className="font-mono text-stone-700">
                                                                                $
                                                                                {v.totalPerUnit.toFixed(
                                                                                    2,
                                                                                )}
                                                                            </span>{' '}
                                                                            per
                                                                            unit
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        )}
                                                    </React.Fragment>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="mt-4 rounded-xl border border-stone-100 bg-stone-50 p-4">
                                    <h5 className="mb-2 text-xs font-bold text-stone-900 uppercase">
                                        Cost Breakdown Logic
                                    </h5>
                                    <ul className="list-inside list-disc space-y-1 text-xs text-stone-500">
                                        <li>
                                            <strong>Base Price:</strong> Vendor
                                            list price based on MOQ.
                                        </li>
                                        <li>
                                            <strong>Duties & Ship:</strong> Flat
                                            shipping and estimated import duties
                                            if international.
                                        </li>
                                        <li>
                                            <strong>Holding:</strong> Storage
                                            cost amortized over lead time
                                            duration.
                                        </li>
                                        <li>
                                            <strong>Risk Premium:</strong>{' '}
                                            Calculated based on vendor
                                            reliability score.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        )}

                        {activeTab === 'expiry' && (
                            <div className="animate-in space-y-6 fade-in">
                                <div className="flex items-center gap-3 rounded-xl border border-rose-100 bg-rose-50 p-4 text-rose-800">
                                    <AlertTriangle size={24} />
                                    <div>
                                        <h4 className="text-sm font-bold">
                                            Perishability Constraints Active
                                        </h4>
                                        <p className="mt-1 text-xs">
                                            First-Expired-First-Out (FEFO) logic
                                            recommended. Max sensible order
                                            quantity limited by shelf life.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <h5 className="mb-4 text-sm font-bold text-stone-900">
                                        Batch Expiry Timeline
                                    </h5>
                                    {/* Mock Visual Timeline */}
                                    <div className="relative pt-6 pb-2">
                                        <div className="absolute top-8 left-0 h-1 w-full rounded-full bg-stone-200"></div>
                                        <div className="relative grid grid-cols-4 gap-4">
                                            {/* Batch 1 */}
                                            <div className="text-center">
                                                <div className="relative z-10 mx-auto h-4 w-4 rounded-full border-4 border-white bg-rose-500 shadow-sm"></div>
                                                <div className="mt-2">
                                                    <p className="text-xs font-bold text-rose-600">
                                                        Batch A
                                                    </p>
                                                    <p className="text-[10px] text-stone-500">
                                                        Exp: 2 Days
                                                    </p>
                                                    <p className="mt-1 text-xs font-bold text-stone-900">
                                                        15 units
                                                    </p>
                                                </div>
                                            </div>
                                            {/* Batch 2 */}
                                            <div className="text-center">
                                                <div className="relative z-10 mx-auto h-4 w-4 rounded-full border-4 border-white bg-amber-500 shadow-sm"></div>
                                                <div className="mt-2">
                                                    <p className="text-xs font-bold text-amber-600">
                                                        Batch B
                                                    </p>
                                                    <p className="text-[10px] text-stone-500">
                                                        Exp: 7 Days
                                                    </p>
                                                    <p className="mt-1 text-xs font-bold text-stone-900">
                                                        45 units
                                                    </p>
                                                </div>
                                            </div>
                                            {/* Batch 3 */}
                                            <div className="text-center opacity-50">
                                                <div className="relative z-10 mx-auto h-4 w-4 rounded-full border-4 border-white bg-emerald-500 shadow-sm"></div>
                                                <div className="mt-2">
                                                    <p className="text-xs font-bold text-emerald-600">
                                                        Batch C
                                                    </p>
                                                    <p className="text-[10px] text-stone-500">
                                                        Exp: 14 Days
                                                    </p>
                                                    <p className="mt-1 text-xs font-bold text-stone-900">
                                                        --
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Comparison Modal */}
            {renderComparisonModal()}
        </div>
    );
};

export default SkuDetail;
