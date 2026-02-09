import {
    AlertTriangle,
    ArrowUpDown,
    CheckCircle2,
    Clock,
    Eye,
    LayoutGrid,
    List,
    Package,
    RefreshCw,
    Search,
    TrendingDown,
    Truck,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';

import { useApp } from '../App';
import { calculateInventoryPositions } from '../services/inventoryService';
import { InventoryPosition } from '../types';

import ProductIcon from './ProductIcon';

type SortField = 'riskScore' | 'sku' | 'onHand' | 'daysCover' | 'status';
type SortOrder = 'asc' | 'desc';

// --- VISUAL SHELF COMPONENT ---
const ShelfItem: React.FC<{ pos: InventoryPosition }> = ({ pos }) => {
    // Determine visual fullness (0-4 stacks)
    const fillLevel = Math.min(
        4,
        Math.floor((pos.onHand / pos.item.bulkThreshold) * 4),
    );

    return (
        <Link
            to={`/inventory/${pos.locationId}/${pos.skuId}`}
            className="group relative flex h-32 flex-col items-center justify-end rounded-lg border-b-4 border-stone-300 bg-stone-100 p-3 shadow-inner transition-all hover:-translate-y-1 hover:border-amber-400"
        >
            {/* Visual Stacks */}
            <div className="mb-1 flex items-end gap-0.5">
                {/* Only show icon if stock > 0 */}
                {pos.onHand > 0 ? (
                    [...Array(Math.max(1, fillLevel))].map((_, i) => (
                        <div
                            key={i}
                            className={`relative transition-transform group-hover:scale-105 ${i === 0 ? 'z-10' : 'z-0 -ml-2'}`}
                        >
                            <ProductIcon
                                category={pos.item.category}
                                className="h-10 w-10 drop-shadow-md"
                            />
                        </div>
                    ))
                ) : (
                    <div className="opacity-20 grayscale">
                        <ProductIcon
                            category={pos.item.category}
                            className="h-10 w-10"
                        />
                    </div>
                )}
            </div>

            {/* Status Indicator */}
            {pos.status.code !== 'OK' && (
                <div className="absolute top-2 right-2">
                    {pos.status.code === 'STOCKOUT_RISK' ? (
                        <AlertTriangle
                            size={16}
                            className="animate-bounce text-rose-500"
                        />
                    ) : (
                        <Clock size={16} className="text-amber-500" />
                    )}
                </div>
            )}

            <div className="w-full text-center">
                <div className="truncate px-1 text-xs font-bold text-stone-700">
                    {pos.item.name}
                </div>
                <div
                    className={`font-mono text-[10px] font-bold ${pos.onHand <= pos.reorderPoint ? 'text-rose-600' : 'text-stone-500'}`}
                >
                    {pos.onHand} {pos.item.unit}
                </div>
            </div>
        </Link>
    );
};

const Inventory: React.FC = () => {
    const { inventory, items, locations, currentLocationId } = useApp();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    // --- State ---
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const [viewMode, setViewMode] = useState<'list' | 'grid'>('list');

    // Filters
    const [searchText, setSearchText] = useState(
        searchParams.get('search') || '',
    );
    const [categoryFilter, setCategoryFilter] = useState<string>('all');
    const [filterBelowROP, setFilterBelowROP] = useState(false);
    const [filterPerishables, setFilterPerishables] = useState(false);

    // Sorting
    const [sortField, setSortField] = useState<SortField>('riskScore');
    const [sortOrder, setSortOrder] = useState<SortOrder>('desc');

    // --- Data Loading ---
    const positions = useMemo(
        () => calculateInventoryPositions(inventory, items, locations),
        [inventory, items, locations],
    );

    // --- Filtering & Sorting Logic ---
    const filteredPositions = useMemo(() => {
        return positions
            .filter((pos) => {
                // Location Context
                if (
                    currentLocationId !== 'all' &&
                    pos.locationId !== currentLocationId
                )
                    return false;

                // Text Search
                if (searchText) {
                    const query = searchText.toLowerCase();
                    if (
                        !pos.item.name.toLowerCase().includes(query) &&
                        !pos.skuId.toLowerCase().includes(query)
                    )
                        return false;
                }

                // Category
                if (
                    categoryFilter !== 'all' &&
                    pos.item.category !== categoryFilter
                )
                    return false;

                // Toggles
                if (filterBelowROP && pos.onHand > pos.reorderPoint)
                    return false;
                if (filterPerishables && !pos.item.isPerishable) return false;

                return true;
            })
            .sort((a, b) => {
                let valA: string | number = a[
                    sortField as keyof InventoryPosition
                ] as string | number;
                let valB: string | number = b[
                    sortField as keyof InventoryPosition
                ] as string | number;

                // Handle nested/special sorts
                if (sortField === 'riskScore') {
                    valA = a.status.riskScore;
                    valB = b.status.riskScore;
                } else if (sortField === 'sku') {
                    valA = a.item.name;
                    valB = b.item.name;
                } else if (sortField === 'status') {
                    valA = a.status.code;
                    valB = b.status.code;
                }

                if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
                if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
                return 0;
            });
    }, [
        positions,
        currentLocationId,
        searchText,
        categoryFilter,
        filterBelowROP,
        filterPerishables,
        sortField,
        sortOrder,
    ]);

    // --- Handlers ---
    const handleSort = (field: SortField) => {
        if (sortField === field) {
            setSortOrder((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortField(field);
            setSortOrder('desc'); // Default to descending for new metrics usually
        }
    };

    const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.checked) {
            setSelectedIds(new Set(filteredPositions.map((p) => p.id)));
        } else {
            setSelectedIds(new Set());
        }
    };

    const handleSelectRow = (id: string) => {
        const newSet = new Set(selectedIds);
        if (newSet.has(id)) newSet.delete(id);
        else newSet.add(id);
        setSelectedIds(newSet);
    };

    const handleBulkAction = (action: 'draft' | 'transfer') => {
        if (selectedIds.size === 0) return;

        // Simple simulation for demo
        const count = selectedIds.size;
        if (action === 'draft') {
            // Just pick the first one to redirect to ordering as a "start here"
            const firstId = Array.from(selectedIds)[0];
            const pos = positions.find((p) => p.id === firstId);
            if (pos) {
                navigate(
                    `/ordering?locId=${pos.locationId}&itemId=${pos.skuId}`,
                );
            }
        } else {
            alert(
                `Transfer proposed for ${count} items. Approval request sent to regional manager.`,
            );
            setSelectedIds(new Set());
        }
    };

    // --- Derived Lists ---
    const categories = Array.from(new Set(items.map((i) => i.category)));

    return (
        <div className="animate-in space-y-6 pb-20 duration-500 fade-in">
            {/* Header */}
            <div className="flex flex-col items-end justify-between gap-4 md:flex-row">
                <div>
                    <h2 className="text-2xl font-bold text-stone-900">
                        Inventory Inspection
                    </h2>
                    <p className="text-stone-500">
                        {currentLocationId === 'all'
                            ? 'Managing global stock positions'
                            : `Managing stock for ${locations.find((l) => l.id === currentLocationId)?.name}`}
                    </p>
                </div>
                <div className="flex gap-2">
                    {/* View Toggle */}
                    <div className="flex items-center rounded-lg bg-stone-200 p-1">
                        <button
                            onClick={() => setViewMode('list')}
                            className={`rounded-md p-1.5 transition-all ${viewMode === 'list' ? 'bg-white text-stone-900 shadow' : 'text-stone-500 hover:text-stone-700'}`}
                        >
                            <List size={16} />
                        </button>
                        <button
                            onClick={() => setViewMode('grid')}
                            className={`rounded-md p-1.5 transition-all ${viewMode === 'grid' ? 'bg-white text-stone-900 shadow' : 'text-stone-500 hover:text-stone-700'}`}
                        >
                            <LayoutGrid size={16} />
                        </button>
                    </div>
                </div>
            </div>

            {/* Control Bar */}
            <div className="space-y-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                <div className="flex flex-col justify-between gap-4 xl:flex-row">
                    {/* Left: Filters */}
                    <div className="flex flex-1 flex-col gap-3 md:flex-row">
                        <div className="relative min-w-[240px] flex-1">
                            <Search
                                className="absolute top-1/2 left-3 -translate-y-1/2 transform text-stone-400"
                                size={16}
                            />
                            <input
                                type="text"
                                placeholder="Search SKU or Product Name..."
                                value={searchText}
                                onChange={(e) => setSearchText(e.target.value)}
                                className="w-full rounded-lg border border-stone-200 bg-stone-50 py-2 pr-4 pl-9 text-sm transition-all focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                            />
                        </div>

                        <select
                            value={categoryFilter}
                            onChange={(e) => setCategoryFilter(e.target.value)}
                            className="rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-700 focus:border-amber-500 focus:outline-none"
                        >
                            <option value="all">All Categories</option>
                            {categories.map((c) => (
                                <option key={c} value={c}>
                                    {c}
                                </option>
                            ))}
                        </select>

                        <div className="flex items-center gap-2 border-l border-stone-100 pl-3">
                            <button
                                onClick={() =>
                                    setFilterBelowROP(!filterBelowROP)
                                }
                                className={`flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors ${
                                    filterBelowROP
                                        ? 'border-amber-200 bg-amber-50 text-amber-700'
                                        : 'border-stone-200 bg-white text-stone-600 hover:bg-stone-50'
                                }`}
                            >
                                <AlertTriangle size={12} />
                                Below ROP
                            </button>
                            <button
                                onClick={() =>
                                    setFilterPerishables(!filterPerishables)
                                }
                                className={`flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors ${
                                    filterPerishables
                                        ? 'border-blue-200 bg-blue-50 text-blue-700'
                                        : 'border-stone-200 bg-white text-stone-600 hover:bg-stone-50'
                                }`}
                            >
                                <Clock size={12} />
                                Perishables
                            </button>
                        </div>
                    </div>

                    {/* Right: Actions (if selection) */}
                    {selectedIds.size > 0 && (
                        <div className="flex animate-in items-center gap-2 fade-in slide-in-from-right-4">
                            <span className="mr-2 text-xs font-medium text-stone-500">
                                {selectedIds.size} selected
                            </span>
                            <button
                                onClick={() => handleBulkAction('draft')}
                                className="flex items-center gap-2 rounded-lg bg-stone-900 px-4 py-2 text-xs font-bold text-white hover:bg-stone-800"
                            >
                                <Package size={14} /> Draft Order
                            </button>
                            <button
                                onClick={() => handleBulkAction('transfer')}
                                className="flex items-center gap-2 rounded-lg border border-stone-300 bg-white px-4 py-2 text-xs font-bold text-stone-700 hover:bg-stone-50"
                            >
                                <Truck size={14} /> Transfer
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {/* Main Content */}
            {viewMode === 'list' ? (
                <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                    <div className="min-h-[400px] overflow-x-auto">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="border-b border-stone-200 bg-stone-50/80 text-xs font-semibold tracking-wider text-stone-500 uppercase">
                                    <th className="w-10 p-4">
                                        <input
                                            type="checkbox"
                                            onChange={handleSelectAll}
                                            checked={
                                                selectedIds.size ===
                                                    filteredPositions.length &&
                                                filteredPositions.length > 0
                                            }
                                            className="cursor-pointer rounded border-stone-300 text-amber-600 focus:ring-amber-500"
                                        />
                                    </th>
                                    <th
                                        className="group cursor-pointer p-4 hover:text-stone-700"
                                        onClick={() => handleSort('sku')}
                                    >
                                        <div className="flex items-center gap-1">
                                            Item / SKU
                                            <ArrowUpDown
                                                size={12}
                                                className={`opacity-0 transition-opacity group-hover:opacity-100 ${sortField === 'sku' ? 'text-amber-500 opacity-100' : ''}`}
                                            />
                                        </div>
                                    </th>
                                    <th
                                        className="group cursor-pointer p-4 text-right hover:text-stone-700"
                                        onClick={() => handleSort('onHand')}
                                    >
                                        <div className="flex items-center justify-end gap-1">
                                            On Hand
                                            <ArrowUpDown
                                                size={12}
                                                className={`opacity-0 transition-opacity group-hover:opacity-100 ${sortField === 'onHand' ? 'text-amber-500 opacity-100' : ''}`}
                                            />
                                        </div>
                                    </th>
                                    <th className="hidden p-4 text-right sm:table-cell">
                                        On Order
                                    </th>
                                    <th
                                        className="group cursor-pointer p-4 text-right hover:text-stone-700"
                                        onClick={() => handleSort('daysCover')}
                                    >
                                        <div className="flex items-center justify-end gap-1">
                                            Days Cover
                                            <ArrowUpDown
                                                size={12}
                                                className={`opacity-0 transition-opacity group-hover:opacity-100 ${sortField === 'daysCover' ? 'text-amber-500 opacity-100' : ''}`}
                                            />
                                        </div>
                                    </th>
                                    <th className="hidden p-4 text-right md:table-cell">
                                        Metrics (ROP / Safe)
                                    </th>
                                    <th
                                        className="group cursor-pointer p-4 hover:text-stone-700"
                                        onClick={() => handleSort('status')}
                                    >
                                        <div className="flex items-center gap-1">
                                            Status & Expiry
                                            <ArrowUpDown
                                                size={12}
                                                className={`opacity-0 transition-opacity group-hover:opacity-100 ${sortField === 'status' ? 'text-amber-500 opacity-100' : ''}`}
                                            />
                                        </div>
                                    </th>
                                    <th className="w-10 p-4"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100">
                                {filteredPositions.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="p-12 text-center text-stone-400"
                                        >
                                            <div className="flex flex-col items-center gap-3">
                                                <Search
                                                    size={32}
                                                    className="opacity-20"
                                                />
                                                <p>
                                                    No inventory found matching
                                                    your filters.
                                                </p>
                                                <button
                                                    onClick={() => {
                                                        setSearchText('');
                                                        setCategoryFilter(
                                                            'all',
                                                        );
                                                        setFilterBelowROP(
                                                            false,
                                                        );
                                                        setFilterPerishables(
                                                            false,
                                                        );
                                                    }}
                                                    className="mt-2 rounded-lg bg-stone-900 px-6 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-stone-800"
                                                >
                                                    Clear Filters & Search
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredPositions.map((pos) => (
                                        <tr
                                            key={pos.id}
                                            className={`group transition-colors ${selectedIds.has(pos.id) ? 'bg-amber-50/40' : 'hover:bg-stone-50'}`}
                                        >
                                            <td className="p-4 align-top">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedIds.has(
                                                        pos.id,
                                                    )}
                                                    onChange={() =>
                                                        handleSelectRow(pos.id)
                                                    }
                                                    className="mt-1 cursor-pointer rounded border-stone-300 text-amber-600 focus:ring-amber-500"
                                                />
                                            </td>
                                            <td className="p-4 align-top">
                                                <Link
                                                    to={`/inventory/${pos.locationId}/${pos.skuId}`}
                                                    className="block"
                                                >
                                                    <div className="flex items-start gap-3 transition-opacity group-hover:opacity-80">
                                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-stone-100">
                                                            <ProductIcon
                                                                category={
                                                                    pos.item
                                                                        .category
                                                                }
                                                                className="h-8 w-8"
                                                            />
                                                        </div>
                                                        <div>
                                                            <div className="text-sm font-bold text-stone-900 transition-colors group-hover:text-amber-600">
                                                                {pos.item.name}
                                                            </div>
                                                            <div className="mt-0.5 flex items-center gap-1 text-[10px] tracking-wide text-stone-500 uppercase">
                                                                {
                                                                    pos.item
                                                                        .category
                                                                }
                                                                {currentLocationId ===
                                                                    'all' && (
                                                                    <span className="text-stone-300">
                                                                        •{' '}
                                                                        {
                                                                            pos.locationName
                                                                        }
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </Link>
                                            </td>
                                            <td className="p-4 text-right align-top">
                                                <div className="font-mono font-bold text-stone-900">
                                                    {pos.onHand}
                                                </div>
                                                <div className="text-xs text-stone-400">
                                                    {pos.item.unit}
                                                </div>
                                            </td>
                                            <td className="hidden p-4 text-right align-top sm:table-cell">
                                                {pos.onOrder > 0 ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600">
                                                        <Truck size={10} /> +
                                                        {pos.onOrder}
                                                    </span>
                                                ) : (
                                                    <span className="text-stone-300">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-4 text-right align-top">
                                                <div
                                                    className={`font-mono font-medium ${pos.daysCover < 3 ? 'text-rose-600' : 'text-stone-700'}`}
                                                >
                                                    {pos.daysCover}d
                                                </div>
                                                <div className="text-[10px] text-stone-400">
                                                    @{pos.dailyUsage}/day
                                                </div>
                                            </td>
                                            <td className="hidden p-4 text-right align-top md:table-cell">
                                                <div className="text-xs text-stone-600">
                                                    <span className="text-stone-400">
                                                        ROP:
                                                    </span>{' '}
                                                    <span className="font-mono font-medium">
                                                        {Math.ceil(
                                                            pos.reorderPoint,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-stone-600">
                                                    <span className="text-stone-400">
                                                        Safe:
                                                    </span>{' '}
                                                    <span className="font-mono font-medium">
                                                        {pos.safetyStock}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="p-4 align-top">
                                                <div className="flex flex-col items-start gap-2">
                                                    {/* Status Badge */}
                                                    <span
                                                        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold ${
                                                            pos.status
                                                                .badgeColor ===
                                                            'emerald'
                                                                ? 'border-emerald-200 bg-emerald-100 text-emerald-800'
                                                                : pos.status
                                                                        .badgeColor ===
                                                                    'amber'
                                                                  ? 'border-amber-200 bg-amber-100 text-amber-800'
                                                                  : pos.status
                                                                          .badgeColor ===
                                                                      'rose'
                                                                    ? 'border-rose-200 bg-rose-100 text-rose-800'
                                                                    : 'border-blue-200 bg-blue-100 text-blue-800'
                                                        }`}
                                                    >
                                                        {pos.status
                                                            .badgeColor ===
                                                        'emerald' ? (
                                                            <CheckCircle2
                                                                size={12}
                                                            />
                                                        ) : pos.status
                                                              .badgeColor ===
                                                          'blue' ? (
                                                            <TrendingDown
                                                                size={12}
                                                            />
                                                        ) : (
                                                            <AlertTriangle
                                                                size={12}
                                                            />
                                                        )}
                                                        {pos.status.explanation}
                                                    </span>

                                                    {/* Enhanced FEFO Visualization */}
                                                    {pos.item.isPerishable &&
                                                        pos.expiryLots.length >
                                                            0 && (
                                                            <div className="w-full max-w-[160px]">
                                                                <div className="flex h-3 w-full overflow-hidden rounded-md bg-stone-100 shadow-sm ring-1 ring-stone-200">
                                                                    {pos.expiryLots
                                                                        .sort(
                                                                            (
                                                                                a,
                                                                                b,
                                                                            ) =>
                                                                                a.daysUntilExpiry -
                                                                                b.daysUntilExpiry,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                lot,
                                                                                idx,
                                                                            ) => (
                                                                                <div
                                                                                    key={
                                                                                        idx
                                                                                    }
                                                                                    className={`group/lot relative h-full cursor-help border-r border-white/20 transition-all last:border-0 hover:opacity-80 ${
                                                                                        lot.riskLevel ===
                                                                                        'critical'
                                                                                            ? 'bg-rose-500'
                                                                                            : lot.riskLevel ===
                                                                                                'warning'
                                                                                              ? 'bg-amber-400'
                                                                                              : 'bg-emerald-400'
                                                                                    }`}
                                                                                    style={{
                                                                                        width: `${(lot.quantity / pos.onHand) * 100}%`,
                                                                                    }}
                                                                                >
                                                                                    {/* Tooltip */}
                                                                                    <div className="absolute bottom-full left-1/2 z-20 mb-1 hidden -translate-x-1/2 rounded-lg border border-stone-700 bg-stone-900 px-2 py-1.5 text-[10px] whitespace-nowrap text-white shadow-xl group-hover/lot:block">
                                                                                        <span className="font-bold">
                                                                                            {
                                                                                                lot.quantity
                                                                                            }{' '}
                                                                                            units
                                                                                        </span>{' '}
                                                                                        expire
                                                                                        in{' '}
                                                                                        {
                                                                                            lot.daysUntilExpiry
                                                                                        }
                                                                                        d
                                                                                    </div>
                                                                                </div>
                                                                            ),
                                                                        )}
                                                                </div>
                                                            </div>
                                                        )}
                                                </div>
                                            </td>
                                            <td className="relative p-4 text-center align-top">
                                                <div className="mt-1 flex items-center justify-end gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                                                    <Link
                                                        to={`/inventory/${pos.locationId}/${pos.skuId}`}
                                                        title="View Analysis"
                                                        className="rounded-lg p-1.5 text-stone-400 transition-colors hover:bg-stone-100 hover:text-stone-800"
                                                    >
                                                        <Eye size={16} />
                                                    </Link>
                                                    <button
                                                        title="Restock"
                                                        onClick={() =>
                                                            navigate(
                                                                `/ordering?locId=${pos.locationId}&itemId=${pos.skuId}`,
                                                            )
                                                        }
                                                        className="rounded-lg p-1.5 text-stone-400 transition-colors hover:bg-amber-50 hover:text-amber-600"
                                                    >
                                                        <RefreshCw size={16} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    {filteredPositions.length > 0 && (
                        <div className="border-t border-stone-200 bg-stone-50 p-3 text-center">
                            <span className="text-xs text-stone-400">
                                Showing {filteredPositions.length} positions
                                sorted by {sortField}
                            </span>
                        </div>
                    )}
                </div>
            ) : (
                // GRID VIEW (PANTRY)
                <div className="grid grid-cols-2 gap-6 md:grid-cols-4 lg:grid-cols-6">
                    {filteredPositions.map((pos) => (
                        <ShelfItem key={pos.id} pos={pos} />
                    ))}
                    {filteredPositions.length === 0 && (
                        <div className="col-span-full py-12 text-center text-stone-400">
                            No items found.
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default Inventory;
