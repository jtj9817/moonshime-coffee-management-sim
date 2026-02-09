import {
    AlertOctagon,
    ArrowRight,
    Calendar,
    ChevronDown,
    ChevronUp,
    Clock,
    DollarSign,
    Download,
    FileText,
    List,
    PieChart as PieIcon,
    Search,
    Settings,
    Trash2,
    TrendingUp,
    User,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { useApp } from '../App';
import { formatCurrency } from '../lib/formatCurrency';
import {
    generateMockPolicyChanges,
    generateMockWasteData,
} from '../services/wasteService';

const WasteReports: React.FC = () => {
    const { items, locations, currentLocationId } = useApp();

    // State
    const [dateRange, setDateRange] = useState({
        start: '2023-09-01',
        end: '2023-10-31',
    });
    const [activeTab, setActiveTab] = useState<'overview' | 'policies'>(
        'overview',
    );
    const [reasonFilter, setReasonFilter] = useState<string>('all');
    const [searchTerm, setSearchTerm] = useState('');

    // Track expanded policy item
    const [expandedPolicyId, setExpandedPolicyId] = useState<string | null>(
        null,
    );

    // Derive data from inputs
    const { wasteEvents, policyLogs } = useMemo(() => {
        const start = new Date(dateRange.start);
        const end = new Date(dateRange.end);
        return {
            wasteEvents: generateMockWasteData(items, locations, start, end),
            policyLogs: generateMockPolicyChanges(items, locations, start, end),
        };
    }, [dateRange, items, locations]);

    // --- Filtering Logic ---
    const filteredWaste = useMemo(() => {
        return wasteEvents.filter((e) => {
            // Location Filter
            if (
                currentLocationId !== 'all' &&
                e.locationId !== currentLocationId
            )
                return false;
            // Reason Filter
            if (reasonFilter !== 'all' && e.reason !== reasonFilter)
                return false;
            // Search
            if (searchTerm) {
                const item = items.find((i) => i.id === e.skuId);
                if (
                    item &&
                    !item.name.toLowerCase().includes(searchTerm.toLowerCase())
                )
                    return false;
            }
            return true;
        });
    }, [wasteEvents, currentLocationId, reasonFilter, searchTerm, items]);

    const filteredPolicies = useMemo(() => {
        return policyLogs.filter((l) => {
            if (
                currentLocationId !== 'all' &&
                l.locationId !== currentLocationId
            )
                return false;
            if (searchTerm) {
                const term = searchTerm.toLowerCase();
                const item = items.find((i) => i.id === l.skuId);
                const matchesItem = item
                    ? item.name.toLowerCase().includes(term)
                    : false;
                const matchesUser = l.user.toLowerCase().includes(term);
                return matchesItem || matchesUser;
            }
            return true;
        });
    }, [policyLogs, currentLocationId, searchTerm, items]);

    // --- CSV Export Helper ---
    const downloadCSV = (
        data: Record<string, string | number | boolean | null | undefined>[],
        filename: string,
    ) => {
        if (!data || data.length === 0) {
            alert('No data to export');
            return;
        }

        // Extract headers from the first object
        const headers = Object.keys(data[0]);

        // Convert data to CSV format
        const csvContent = [
            headers.join(','),
            ...data.map((row) =>
                headers
                    .map((fieldName) => {
                        const value = row[fieldName];
                        // Escape quotes and wrap in quotes if string contains comma
                        return typeof value === 'string' && value.includes(',')
                            ? `"${value.replace(/"/g, '""')}"`
                            : value;
                    })
                    .join(','),
            ),
        ].join('\n');

        // Create download link
        const blob = new Blob([csvContent], {
            type: 'text/csv;charset=utf-8;',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // --- Aggregations ---

    const totalWasteCost = useMemo(
        () => filteredWaste.reduce((acc, e) => acc + e.qty * e.unitCost, 0),
        [filteredWaste],
    );

    // Monthly Trend Data
    const trendData = useMemo(() => {
        const grouped: Record<string, number> = {};
        filteredWaste.forEach((e) => {
            // Group by Week or Month? Let's do Month-Day sort of aggregation for the chart
            // Simplify to just Date string for the Area chart
            const d = new Date(e.date).toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
            });
            grouped[d] = (grouped[d] || 0) + e.qty * e.unitCost;
        });
        return Object.entries(grouped).map(([date, value]) => ({
            date,
            value,
        }));
    }, [filteredWaste]);

    // Reason Breakdown
    const reasonData = useMemo(() => {
        const grouped: Record<string, number> = {};
        filteredWaste.forEach((e) => {
            grouped[e.reason] = (grouped[e.reason] || 0) + e.qty * e.unitCost;
        });
        return Object.entries(grouped)
            .map(([name, value]) => ({ name: name.replace('_', ' '), value }))
            .sort((a, b) => b.value - a.value);
    }, [filteredWaste]);

    // Location Breakdown
    const locationData = useMemo(() => {
        const grouped: Record<string, number> = {};
        filteredWaste.forEach((e) => {
            const loc = locations.find((l) => l.id === e.locationId);
            const name = loc ? loc.name : 'Unknown';
            grouped[name] = (grouped[name] || 0) + e.qty * e.unitCost;
        });
        return Object.entries(grouped)
            .map(([name, value]) => ({ name, value }))
            .sort((a, b) => b.value - a.value);
    }, [filteredWaste, locations]);

    const topWastedItems = useMemo(() => {
        const grouped: Record<
            string,
            { name: string; cost: number; qty: number }
        > = {};
        filteredWaste.forEach((e) => {
            const item = items.find((i) => i.id === e.skuId);
            if (!item) return;
            if (!grouped[e.skuId])
                grouped[e.skuId] = { name: item.name, cost: 0, qty: 0 };
            grouped[e.skuId].cost += e.qty * e.unitCost;
            grouped[e.skuId].qty += e.qty;
        });
        return Object.values(grouped)
            .sort((a, b) => b.cost - a.cost)
            .slice(0, 5);
    }, [filteredWaste, items]);

    const COLORS = ['#f59e0b', '#ef4444', '#3b82f6', '#10b981', '#6b7280'];

    return (
        <div className="animate-in space-y-6 pb-20 duration-500 fade-in">
            {/* Header */}
            <div className="flex flex-col items-end justify-between gap-4 md:flex-row">
                <div>
                    <h2 className="flex items-center gap-2 text-2xl font-bold text-stone-900">
                        <Trash2 className="text-stone-400" /> Waste &
                        Performance
                    </h2>
                    <p className="text-stone-500">
                        Track spoilage costs and analyze policy effectiveness.
                    </p>
                </div>

                <div className="flex items-center gap-2 rounded-lg border border-stone-200 bg-white p-1 shadow-sm">
                    <div className="relative mr-2 border-r border-stone-100 pr-2">
                        <Calendar
                            size={14}
                            className="absolute top-1/2 left-2 -translate-y-1/2 text-stone-400"
                        />
                        <input
                            type="date"
                            value={dateRange.start}
                            onChange={(e) =>
                                setDateRange((prev) => ({
                                    ...prev,
                                    start: e.target.value,
                                }))
                            }
                            className="w-28 bg-transparent py-1 pl-7 text-xs font-medium text-stone-600 outline-none"
                        />
                    </div>
                    <div className="relative">
                        <input
                            type="date"
                            value={dateRange.end}
                            onChange={(e) =>
                                setDateRange((prev) => ({
                                    ...prev,
                                    end: e.target.value,
                                }))
                            }
                            className="w-28 bg-transparent py-1 text-xs font-medium text-stone-600 outline-none"
                        />
                    </div>
                </div>
            </div>

            {/* Navigation Tabs */}
            <div className="flex gap-4 border-b border-stone-200">
                <button
                    onClick={() => {
                        setActiveTab('overview');
                        setSearchTerm('');
                    }}
                    className={`relative flex items-center gap-2 pb-3 text-sm font-bold transition-colors ${activeTab === 'overview' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                >
                    <PieIcon size={16} /> Waste Analysis
                    {activeTab === 'overview' && (
                        <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-amber-500"></div>
                    )}
                </button>
                <button
                    onClick={() => {
                        setActiveTab('policies');
                        setSearchTerm('');
                    }}
                    className={`relative flex items-center gap-2 pb-3 text-sm font-bold transition-colors ${activeTab === 'policies' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                >
                    <Settings size={16} /> Policy Impact Report
                    {activeTab === 'policies' && (
                        <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-amber-500"></div>
                    )}
                </button>
            </div>

            {/* --- TAB CONTENT --- */}

            {activeTab === 'overview' && (
                <div className="animate-in space-y-6 fade-in slide-in-from-bottom-2">
                    {/* KPI Cards */}
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                            <div className="mb-2 text-xs font-bold tracking-wider text-stone-500 uppercase">
                                Total Waste Cost
                            </div>
                            <div className="flex items-baseline gap-2 text-3xl font-bold text-stone-900">
                                ${formatCurrency(totalWasteCost)}
                                <span className="flex items-center text-sm font-medium text-rose-500">
                                    <TrendingUp size={14} /> +4.2%
                                </span>
                            </div>
                            <div className="mt-1 text-xs text-stone-400">
                                vs previous period
                            </div>
                        </div>

                        <div className="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                            <div className="mb-2 text-xs font-bold tracking-wider text-stone-500 uppercase">
                                Top Driver
                            </div>
                            <div className="text-2xl font-bold text-stone-900">
                                {reasonData[0]?.name || '--'}
                            </div>
                            <div className="mt-1 text-xs text-stone-400">
                                Accounted for{' '}
                                {(
                                    ((reasonData[0]?.value || 0) /
                                        totalWasteCost) *
                                    100
                                ).toFixed(0)}
                                % of loss
                            </div>
                        </div>

                        <div className="flex flex-col items-center justify-center rounded-xl border border-stone-200 bg-white p-5 text-center shadow-sm">
                            <div className="mb-2 rounded-full bg-emerald-50 p-3 text-emerald-600">
                                <FileText size={24} />
                            </div>
                            <button
                                onClick={() =>
                                    downloadCSV(
                                        filteredWaste,
                                        `waste_report_${dateRange.start}_${dateRange.end}.csv`,
                                    )
                                }
                                className="flex items-center gap-1 text-xs font-bold text-stone-600 transition-colors hover:text-amber-600"
                            >
                                Download Full Report <Download size={12} />
                            </button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* Left: Charts */}
                        <div className="space-y-6 lg:col-span-2">
                            {/* Cost Trend */}
                            <div className="h-80 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                                <h3 className="mb-4 font-bold text-stone-900">
                                    Daily Waste Cost Trend
                                </h3>
                                <ResponsiveContainer width="100%" height="90%">
                                    <AreaChart data={trendData}>
                                        <defs>
                                            <linearGradient
                                                id="colorWaste"
                                                x1="0"
                                                y1="0"
                                                x2="0"
                                                y2="1"
                                            >
                                                <stop
                                                    offset="5%"
                                                    stopColor="#ef4444"
                                                    stopOpacity={0.2}
                                                />
                                                <stop
                                                    offset="95%"
                                                    stopColor="#ef4444"
                                                    stopOpacity={0}
                                                />
                                            </linearGradient>
                                        </defs>
                                        <XAxis
                                            dataKey="date"
                                            stroke="#a8a29e"
                                            fontSize={10}
                                            tickLine={false}
                                            axisLine={false}
                                            minTickGap={30}
                                        />
                                        <YAxis
                                            stroke="#a8a29e"
                                            fontSize={10}
                                            tickLine={false}
                                            axisLine={false}
                                            tickFormatter={(v) => `$${v}`}
                                        />
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            vertical={false}
                                            stroke="#f5f5f4"
                                        />
                                        <Tooltip
                                            contentStyle={{
                                                borderRadius: '8px',
                                                border: 'none',
                                                boxShadow:
                                                    '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                                fontSize: '12px',
                                            }}
                                            formatter={(value: number) => [
                                                `$${value.toFixed(2)}`,
                                                'Cost',
                                            ]}
                                        />
                                        <Area
                                            type="monotone"
                                            dataKey="value"
                                            stroke="#ef4444"
                                            strokeWidth={2}
                                            fill="url(#colorWaste)"
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>

                            {/* Detailed Table */}
                            <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                                <div className="flex flex-col justify-between gap-4 border-b border-stone-100 bg-stone-50 p-4 sm:flex-row">
                                    <h3 className="flex items-center gap-2 font-bold text-stone-900">
                                        <List size={18} /> Waste Events
                                    </h3>
                                    <div className="flex gap-2">
                                        <div className="relative">
                                            <Search
                                                size={14}
                                                className="absolute top-1/2 left-2 -translate-y-1/2 text-stone-400"
                                            />
                                            <input
                                                type="text"
                                                placeholder="Search SKU..."
                                                value={searchTerm}
                                                onChange={(e) =>
                                                    setSearchTerm(
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-40 rounded-lg border border-stone-200 py-1.5 pr-3 pl-7 text-xs outline-none focus:border-amber-400"
                                            />
                                        </div>
                                        <select
                                            value={reasonFilter}
                                            onChange={(e) =>
                                                setReasonFilter(e.target.value)
                                            }
                                            className="cursor-pointer rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-xs outline-none"
                                        >
                                            <option value="all">
                                                All Reasons
                                            </option>
                                            <option value="EXPIRY">
                                                Expiry
                                            </option>
                                            <option value="OVER_ORDER">
                                                Over Order
                                            </option>
                                            <option value="FORECAST_MISS">
                                                Forecast Miss
                                            </option>
                                            <option value="SUPPLIER_DELAY">
                                                Supplier Delay
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div className="max-h-96 overflow-y-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="sticky top-0 z-10 bg-stone-50 text-[10px] text-stone-500 uppercase">
                                            <tr>
                                                <th className="px-4 py-3 font-semibold">
                                                    Date
                                                </th>
                                                <th className="px-4 py-3 font-semibold">
                                                    Item / Location
                                                </th>
                                                <th className="px-4 py-3 font-semibold">
                                                    Reason
                                                </th>
                                                <th className="px-4 py-3 text-right font-semibold">
                                                    Qty
                                                </th>
                                                <th className="px-4 py-3 text-right font-semibold">
                                                    Cost
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-stone-50 text-xs">
                                            {filteredWaste
                                                .slice(0, 50)
                                                .map((e) => {
                                                    const item = items.find(
                                                        (i) => i.id === e.skuId,
                                                    );
                                                    const loc = locations.find(
                                                        (l) =>
                                                            l.id ===
                                                            e.locationId,
                                                    );
                                                    return (
                                                        <tr
                                                            key={e.id}
                                                            className="hover:bg-stone-50"
                                                        >
                                                            <td className="px-4 py-3 whitespace-nowrap text-stone-500">
                                                                {e.date}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="font-bold text-stone-900">
                                                                    {item?.name}
                                                                </div>
                                                                <div className="text-[10px] text-stone-400">
                                                                    {loc?.name}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <span
                                                                    className={`rounded-full px-2 py-1 text-[10px] font-bold ${
                                                                        e.reason ===
                                                                        'EXPIRY'
                                                                            ? 'bg-amber-100 text-amber-700'
                                                                            : e.reason ===
                                                                                'SUPPLIER_DELAY'
                                                                              ? 'bg-blue-100 text-blue-700'
                                                                              : 'bg-stone-100 text-stone-600'
                                                                    }`}
                                                                >
                                                                    {e.reason.replace(
                                                                        '_',
                                                                        ' ',
                                                                    )}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-mono">
                                                                {e.qty}
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-mono font-medium text-stone-900">
                                                                $
                                                                {(
                                                                    e.qty *
                                                                    e.unitCost
                                                                ).toFixed(2)}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {/* Right: Breakdown Stats */}
                        <div className="space-y-6">
                            {/* Category Breakdown */}
                            <div className="min-h-[300px] rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                                <h3 className="mb-4 font-bold text-stone-900">
                                    Cost by Category
                                </h3>
                                <div className="h-48">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <PieChart>
                                            <Pie
                                                data={reasonData}
                                                innerRadius={40}
                                                outerRadius={70}
                                                paddingAngle={5}
                                                dataKey="value"
                                            >
                                                {reasonData.map(
                                                    (entry, index) => (
                                                        <Cell
                                                            key={`cell-${index}`}
                                                            fill={
                                                                COLORS[
                                                                    index %
                                                                        COLORS.length
                                                                ]
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </Pie>
                                            <Tooltip
                                                contentStyle={{
                                                    borderRadius: '8px',
                                                    border: 'none',
                                                    boxShadow:
                                                        '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                                    fontSize: '12px',
                                                }}
                                                formatter={(v: number) =>
                                                    `$${v.toFixed(0)}`
                                                }
                                            />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                                <div className="mt-2 space-y-2">
                                    {reasonData.map((entry, idx) => (
                                        <div
                                            key={entry.name}
                                            className="flex items-center justify-between text-xs"
                                        >
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="h-2 w-2 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            COLORS[
                                                                idx %
                                                                    COLORS.length
                                                            ],
                                                    }}
                                                ></div>
                                                <span className="text-stone-600">
                                                    {entry.name}
                                                </span>
                                            </div>
                                            <span className="font-bold text-stone-900">
                                                ${entry.value.toFixed(0)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Location Breakdown (New) */}
                            <div className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                                <h3 className="mb-4 font-bold text-stone-900">
                                    Cost by Location
                                </h3>
                                <div className="h-48">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <BarChart
                                            data={locationData}
                                            layout="vertical"
                                            margin={{ left: 0, right: 30 }}
                                        >
                                            <XAxis type="number" hide />
                                            <YAxis
                                                dataKey="name"
                                                type="category"
                                                width={90}
                                                tick={{
                                                    fontSize: 10,
                                                    fill: '#78716c',
                                                }}
                                                interval={0}
                                            />
                                            <Tooltip
                                                cursor={{ fill: 'transparent' }}
                                                contentStyle={{
                                                    borderRadius: '8px',
                                                    border: 'none',
                                                    boxShadow:
                                                        '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                                    fontSize: '12px',
                                                }}
                                                formatter={(v: number) => [
                                                    `$${v.toFixed(0)}`,
                                                    'Cost',
                                                ]}
                                            />
                                            <Bar
                                                dataKey="value"
                                                radius={[0, 4, 4, 0]}
                                                barSize={20}
                                            >
                                                {locationData.map(
                                                    (entry, index) => (
                                                        <Cell
                                                            key={`cell-${index}`}
                                                            fill={
                                                                COLORS[
                                                                    index %
                                                                        COLORS.length
                                                                ]
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>

                            {/* Top Offenders */}
                            <div className="rounded-2xl border border-stone-800 bg-stone-900 p-6 text-white shadow-lg">
                                <h3 className="mb-4 flex items-center gap-2 text-sm font-bold tracking-wider uppercase">
                                    <AlertOctagon
                                        size={16}
                                        className="text-rose-500"
                                    />{' '}
                                    Top Offenders
                                </h3>
                                <div className="space-y-4">
                                    {topWastedItems.map((item, idx) => (
                                        <div
                                            key={idx}
                                            className="flex items-center justify-between"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="font-mono text-xs text-stone-500">
                                                    0{idx + 1}
                                                </span>
                                                <div>
                                                    <div className="text-sm font-bold">
                                                        {item.name}
                                                    </div>
                                                    <div className="text-[10px] text-stone-400">
                                                        {item.qty} units lost
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="font-bold text-rose-400">
                                                    ${item.cost.toFixed(0)}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {activeTab === 'policies' && (
                <div className="grid animate-in grid-cols-1 gap-6 fade-in slide-in-from-right-2 lg:grid-cols-3">
                    {/* Left: Change Log */}
                    <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm lg:col-span-2">
                        <div className="flex flex-col items-center justify-between gap-4 border-b border-stone-100 bg-stone-50 p-5 sm:flex-row">
                            <div className="flex w-full items-center gap-4 sm:w-auto">
                                <h3 className="font-bold whitespace-nowrap text-stone-900">
                                    Policy Change History
                                </h3>
                                <div className="relative flex-1 sm:flex-none">
                                    <Search
                                        size={14}
                                        className="absolute top-1/2 left-2 -translate-y-1/2 text-stone-400"
                                    />
                                    <input
                                        type="text"
                                        placeholder="Search SKU or User..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-stone-200 bg-white py-1.5 pr-3 pl-7 text-xs outline-none focus:border-amber-400 sm:w-48"
                                    />
                                </div>
                            </div>
                            <button
                                onClick={() =>
                                    downloadCSV(
                                        filteredPolicies,
                                        `policy_log_${dateRange.start}_${dateRange.end}.csv`,
                                    )
                                }
                                className="flex items-center gap-1 rounded border border-stone-200 bg-white px-2 py-1 text-xs font-bold whitespace-nowrap text-stone-500 shadow-sm transition-colors hover:text-amber-600"
                            >
                                <Download size={12} /> Export CSV
                            </button>
                        </div>
                        <div className="divide-y divide-stone-100">
                            {filteredPolicies.map((log) => {
                                const item = items.find(
                                    (i) => i.id === log.skuId,
                                );
                                const loc = locations.find(
                                    (l) => l.id === log.locationId,
                                );
                                const isExpanded = expandedPolicyId === log.id;

                                return (
                                    <div
                                        key={log.id}
                                        onClick={() =>
                                            setExpandedPolicyId(
                                                isExpanded ? null : log.id,
                                            )
                                        }
                                        className={`cursor-pointer border-b border-stone-100 p-5 transition-all last:border-0 ${
                                            isExpanded
                                                ? 'bg-stone-50'
                                                : 'bg-white hover:bg-stone-50'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="mb-1.5 flex items-center gap-2">
                                                    <span
                                                        className={`rounded px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase ${
                                                            log.changeType ===
                                                            'REORDER_POINT'
                                                                ? 'bg-blue-100 text-blue-700'
                                                                : log.changeType ===
                                                                    'SAFETY_STOCK'
                                                                  ? 'bg-amber-100 text-amber-700'
                                                                  : 'bg-stone-200 text-stone-600'
                                                        }`}
                                                    >
                                                        {log.changeType.replace(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </span>
                                                    <span className="flex items-center gap-1 text-xs text-stone-400">
                                                        <Clock size={10} />{' '}
                                                        {log.date}
                                                    </span>
                                                </div>

                                                <h4 className="text-sm font-bold text-stone-900">
                                                    Updated{' '}
                                                    {item
                                                        ? item.name
                                                        : 'System'}{' '}
                                                    {loc
                                                        ? `at ${loc.name}`
                                                        : ''}
                                                </h4>

                                                {!isExpanded && (
                                                    <div className="mt-2 flex items-center gap-1 truncate text-xs text-stone-500">
                                                        <User size={10} />{' '}
                                                        {log.user}
                                                    </div>
                                                )}
                                            </div>

                                            <div className="mt-1 ml-4 text-stone-400">
                                                {isExpanded ? (
                                                    <ChevronUp size={16} />
                                                ) : (
                                                    <ChevronDown size={16} />
                                                )}
                                            </div>
                                        </div>

                                        {isExpanded && (
                                            <div
                                                className="mt-4 animate-in cursor-default duration-200 fade-in slide-in-from-top-1"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <div className="mb-3 flex w-full items-center gap-3 rounded-lg border border-stone-200 bg-white p-3 text-sm text-stone-600 shadow-sm">
                                                    <div className="flex-1 border-r border-stone-100 pr-2 text-center">
                                                        <div className="text-[10px] font-bold text-stone-400 uppercase">
                                                            Previous
                                                        </div>
                                                        <div className="mt-0.5 font-mono text-stone-400 line-through">
                                                            {log.oldValue}
                                                        </div>
                                                    </div>
                                                    <ArrowRight
                                                        size={14}
                                                        className="flex-shrink-0 text-stone-300"
                                                    />
                                                    <div className="flex-1 border-l border-stone-100 pl-2 text-center">
                                                        <div className="text-[10px] font-bold text-stone-400 uppercase">
                                                            New Value
                                                        </div>
                                                        <div className="mt-0.5 font-mono font-bold text-stone-900">
                                                            {log.newValue}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="space-y-3">
                                                    <div>
                                                        <span className="text-[10px] font-bold text-stone-400 uppercase">
                                                            Reason
                                                        </span>
                                                        <p className="mt-1 rounded border border-stone-100 bg-stone-100/50 p-2 text-xs text-stone-600 italic">
                                                            "{log.reason}"
                                                        </p>
                                                    </div>

                                                    <div className="flex items-end justify-between pt-2">
                                                        <div className="flex items-center gap-2 text-xs text-stone-500">
                                                            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-stone-200 text-stone-500">
                                                                <User
                                                                    size={12}
                                                                />
                                                            </div>
                                                            <div className="flex flex-col">
                                                                <span className="font-bold text-stone-700">
                                                                    {log.user}
                                                                </span>
                                                                <span className="text-[10px]">
                                                                    Authorized
                                                                    Change
                                                                </span>
                                                            </div>
                                                        </div>

                                                        {log.impactMetric && (
                                                            <span className="flex items-center gap-1 rounded border border-emerald-100 bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-600">
                                                                <DollarSign
                                                                    size={10}
                                                                />{' '}
                                                                {
                                                                    log.impactMetric
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                            {filteredPolicies.length === 0 && (
                                <div className="p-8 text-center text-sm text-stone-400">
                                    No policy changes recorded in this period.
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right: Correlation Insight (Static/Simulated) */}
                    <div className="space-y-6">
                        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-900 to-stone-900 p-6 text-white shadow-xl">
                            <div className="relative z-10">
                                <h3 className="mb-2 flex items-center gap-2 text-lg font-bold">
                                    <TrendingUp className="text-emerald-400" />{' '}
                                    Policy Impact Analysis
                                </h3>
                                <p className="mb-6 text-sm leading-relaxed text-indigo-100">
                                    Correlating recent Safety Stock adjustments
                                    with spoilage rates indicates a positive
                                    trend.
                                </p>

                                <div className="space-y-4">
                                    <div className="rounded-xl border border-white/10 bg-white/10 p-3">
                                        <div className="mb-1 text-xs font-bold text-indigo-200 uppercase">
                                            Waste Reduction
                                        </div>
                                        <div className="text-2xl font-bold text-emerald-400">
                                            -12%
                                        </div>
                                        <div className="mt-1 text-[10px] text-indigo-300">
                                            Since adjusting ROP for 'Milk'
                                        </div>
                                    </div>
                                    <div className="rounded-xl border border-white/10 bg-white/10 p-3">
                                        <div className="mb-1 text-xs font-bold text-indigo-200 uppercase">
                                            Stockout Events
                                        </div>
                                        <div className="text-2xl font-bold text-white">
                                            0
                                        </div>
                                        <div className="mt-1 text-[10px] text-indigo-300">
                                            Maintained 100% service level
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {/* Decorative BG */}
                            <div className="absolute -right-10 -bottom-10 h-40 w-40 rounded-full bg-indigo-500 opacity-20 blur-3xl"></div>
                        </div>

                        <div className="rounded-xl border border-amber-100 bg-amber-50 p-5">
                            <h4 className="mb-2 flex items-center gap-2 text-sm font-bold text-amber-900">
                                <AlertOctagon size={16} /> Recommendation
                            </h4>
                            <p className="text-xs leading-relaxed text-amber-800">
                                High spoilage recorded for{' '}
                                <strong>Pastry</strong> category at{' '}
                                <strong>Uptown Kiosk</strong> on weekends.
                                Consider reducing Standing Order quantity by 15%
                                on Saturdays.
                            </p>
                            <button className="mt-3 w-full rounded-lg border border-amber-200 bg-white py-2 text-xs font-bold text-amber-700 transition-colors hover:bg-amber-100">
                                Apply Adjustment
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default WasteReports;
