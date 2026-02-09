import {
    DollarSign,
    HelpCircle,
    Layers,
    RotateCcw,
    Save,
    ShieldCheck,
    Sliders,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { useApp } from '../App';
import { calculatePolicyImpact } from '../services/policyService';
import { PolicyProfile } from '../types';

const PolicyDeck: React.FC = () => {
    const { policies, updatePolicies, inventory, items, locations } = useApp();

    // Local state for "Draft" policies (sliders move this, not global state immediately)
    const [draftPolicy, setDraftPolicy] = useState<PolicyProfile>({
        ...policies,
    });
    const [isDirty, setIsDirty] = useState(false);

    // Sync draft if global policies change externally (unlikely but good practice)
    if (!isDirty && draftPolicy !== policies) {
        setDraftPolicy(policies);
    }

    // Recalculate impact whenever draft changes
    const impact = useMemo(
        () =>
            calculatePolicyImpact(
                policies,
                draftPolicy,
                inventory,
                items,
                locations,
            ),
        [draftPolicy, policies, inventory, items, locations],
    );

    const handleChange = (field: keyof PolicyProfile, value: number) => {
        setDraftPolicy((prev) => ({ ...prev, [field]: value }));
        setIsDirty(true);
    };

    const handleSave = () => {
        updatePolicies(draftPolicy);
        setIsDirty(false);
    };

    const handleReset = () => {
        setDraftPolicy(policies);
        setIsDirty(false);
    };

    // Mock data for Service Level vs Capital Curve
    const curveData = useMemo(() => {
        // Generate a curve to show non-linear cost growth
        const data = [];
        for (let sl = 80; sl <= 99; sl += 1) {
            const p = { ...draftPolicy, globalServiceLevel: sl / 100 };
            const res = calculatePolicyImpact(
                policies,
                p,
                inventory,
                items,
                locations,
            );
            data.push({ sl, cost: res.capitalRequired });
        }
        return data;
    }, [draftPolicy, policies, inventory, items, locations]);

    return (
        <div className="animate-in space-y-6 pb-20 duration-500 fade-in">
            <div className="flex flex-col items-end justify-between gap-4 md:flex-row">
                <div>
                    <h2 className="flex items-center gap-2 text-2xl font-bold text-stone-900">
                        <Layers className="text-stone-400" /> Strategy Deck
                    </h2>
                    <p className="text-stone-500">
                        Configure global supply chain parameters and risk
                        tolerance.
                    </p>
                </div>
                <div className="flex gap-3">
                    <button
                        onClick={handleReset}
                        disabled={!isDirty}
                        className="rounded-lg px-4 py-2 text-sm font-bold text-stone-500 transition-colors hover:bg-stone-100 disabled:opacity-30"
                    >
                        <RotateCcw size={16} /> Reset
                    </button>
                    <button
                        onClick={handleSave}
                        disabled={!isDirty}
                        className="flex items-center gap-2 rounded-lg bg-stone-900 px-6 py-2 text-sm font-bold text-white shadow-lg shadow-stone-900/10 transition-all hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Save size={16} /> Apply Policy
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Controls */}
                <div className="space-y-8 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                    <div>
                        <h3 className="mb-4 flex items-center gap-2 font-bold text-stone-900">
                            <Sliders size={18} className="text-amber-500" />{' '}
                            Configuration
                        </h3>

                        {/* Service Level Slider */}
                        <div className="mb-6 space-y-3">
                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-1 text-xs font-bold text-stone-500 uppercase">
                                    Target Service Level
                                    <HelpCircle
                                        size={12}
                                        className="text-stone-300"
                                        title="Probability of NOT creating a stockout during lead time."
                                    />
                                </label>
                                <span className="font-mono font-bold text-stone-900">
                                    {(
                                        draftPolicy.globalServiceLevel * 100
                                    ).toFixed(1)}
                                    %
                                </span>
                            </div>
                            <input
                                type="range"
                                min="0.80"
                                max="0.999"
                                step="0.001"
                                value={draftPolicy.globalServiceLevel}
                                onChange={(e) =>
                                    handleChange(
                                        'globalServiceLevel',
                                        parseFloat(e.target.value),
                                    )
                                }
                                className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-stone-100 accent-amber-600"
                            />
                            <div className="flex justify-between font-mono text-[10px] text-stone-400">
                                <span>80% (Lean)</span>
                                <span>99.9% (Safe)</span>
                            </div>
                        </div>

                        {/* Safety Stock Buffer */}
                        <div className="mb-6 space-y-3">
                            <div className="flex items-center justify-between">
                                <label className="text-xs font-bold text-stone-500 uppercase">
                                    Safety Stock Buffer
                                </label>
                                <span className="font-mono font-bold text-stone-900">
                                    +
                                    {Math.round(
                                        draftPolicy.safetyStockBufferPct * 100,
                                    )}
                                    %
                                </span>
                            </div>
                            <input
                                type="range"
                                min="0"
                                max="0.5"
                                step="0.01"
                                value={draftPolicy.safetyStockBufferPct}
                                onChange={(e) =>
                                    handleChange(
                                        'safetyStockBufferPct',
                                        parseFloat(e.target.value),
                                    )
                                }
                                className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-stone-100 accent-stone-600"
                            />
                        </div>

                        {/* Holding Cost Rate */}
                        <div className="mb-6 space-y-3">
                            <div className="flex items-center justify-between">
                                <label className="text-xs font-bold text-stone-500 uppercase">
                                    Annual Holding Cost
                                </label>
                                <span className="font-mono font-bold text-stone-900">
                                    {Math.round(
                                        draftPolicy.holdingCostRate * 100,
                                    )}
                                    %
                                </span>
                            </div>
                            <input
                                type="range"
                                min="0.1"
                                max="0.5"
                                step="0.01"
                                value={draftPolicy.holdingCostRate}
                                onChange={(e) =>
                                    handleChange(
                                        'holdingCostRate',
                                        parseFloat(e.target.value),
                                    )
                                }
                                className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-stone-100 accent-stone-600"
                            />
                        </div>

                        {/* Auto Transfer Threshold */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <label className="text-xs font-bold text-stone-500 uppercase">
                                    Transfer Trigger (vs ROP)
                                </label>
                                <span className="font-mono font-bold text-stone-900">
                                    {Math.round(
                                        draftPolicy.autoTransferThreshold * 100,
                                    )}
                                    %
                                </span>
                            </div>
                            <input
                                type="range"
                                min="0.1"
                                max="0.9"
                                step="0.05"
                                value={draftPolicy.autoTransferThreshold}
                                onChange={(e) =>
                                    handleChange(
                                        'autoTransferThreshold',
                                        parseFloat(e.target.value),
                                    )
                                }
                                className="h-2 w-full cursor-pointer appearance-none rounded-lg bg-stone-100 accent-blue-600"
                            />
                        </div>
                    </div>

                    <div className="rounded-xl border border-stone-100 bg-stone-50 p-4 text-xs leading-relaxed text-stone-500">
                        <p>
                            <strong>Note:</strong> Increasing Service Level
                            exponentially increases the capital required for
                            Safety Stock. Find the balance between reliability
                            and cash flow.
                        </p>
                    </div>
                </div>

                {/* Visualization & Impact */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Impact Cards */}
                    <div className="grid grid-cols-2 gap-4">
                        <div
                            className={`rounded-2xl border p-5 transition-colors ${
                                (impact?.deltaCapital || 0) > 0
                                    ? 'border-amber-200 bg-amber-50'
                                    : 'border-emerald-200 bg-emerald-50'
                            }`}
                        >
                            <div className="mb-2 flex items-start justify-between">
                                <div className="text-xs font-bold tracking-wider uppercase opacity-60">
                                    Capital Requirement
                                </div>
                                <DollarSign size={16} className="opacity-50" />
                            </div>
                            <div className="mb-1 text-2xl font-bold">
                                $
                                {impact?.capitalRequired.toLocaleString(
                                    undefined,
                                    { maximumFractionDigits: 0 },
                                )}
                            </div>
                            <div className="flex items-center gap-1 text-xs font-medium">
                                {(impact?.deltaCapital || 0) > 0 ? (
                                    <TrendingUp size={12} />
                                ) : (
                                    <TrendingDown size={12} />
                                )}
                                {impact?.deltaCapital === 0 ? (
                                    'No Change'
                                ) : (
                                    <span>
                                        {(impact?.deltaCapital ?? 0) > 0
                                            ? '+'
                                            : ''}
                                        $
                                        {impact?.deltaCapital.toLocaleString(
                                            undefined,
                                            { maximumFractionDigits: 0 },
                                        )}{' '}
                                        vs current
                                    </span>
                                )}
                            </div>
                        </div>

                        <div
                            className={`rounded-2xl border p-5 transition-colors ${
                                (impact?.projectedStockoutRisk || 0) > 5
                                    ? 'border-stone-200 bg-white'
                                    : 'border-emerald-200 bg-emerald-50'
                            }`}
                        >
                            <div className="mb-2 flex items-start justify-between">
                                <div className="text-xs font-bold tracking-wider uppercase opacity-60">
                                    Stockout Probability
                                </div>
                                <ShieldCheck size={16} className="opacity-50" />
                            </div>
                            <div className="mb-1 text-2xl font-bold">
                                {impact?.projectedStockoutRisk.toFixed(2)}%
                            </div>
                            <div className="text-xs text-stone-400">
                                Theoretical risk per cycle
                            </div>
                        </div>
                    </div>

                    {/* Curve Chart */}
                    <div className="h-80 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                        <h4 className="mb-4 font-bold text-stone-900">
                            Service Level vs. Capital Investment Curve
                        </h4>
                        <ResponsiveContainer width="100%" height="90%">
                            <AreaChart data={curveData}>
                                <defs>
                                    <linearGradient
                                        id="colorCost"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="5%"
                                            stopColor="#d97706"
                                            stopOpacity={0.1}
                                        />
                                        <stop
                                            offset="95%"
                                            stopColor="#d97706"
                                            stopOpacity={0}
                                        />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    vertical={false}
                                    stroke="#f5f5f4"
                                />
                                <XAxis
                                    dataKey="sl"
                                    type="number"
                                    domain={[80, 99]}
                                    tickFormatter={(v) => `${v}%`}
                                    stroke="#a8a29e"
                                    fontSize={10}
                                    tickLine={false}
                                    axisLine={false}
                                />
                                <YAxis
                                    stroke="#a8a29e"
                                    fontSize={10}
                                    tickLine={false}
                                    axisLine={false}
                                    tickFormatter={(v) => `$${v / 1000}k`}
                                />
                                <Tooltip
                                    contentStyle={{
                                        borderRadius: '8px',
                                        border: 'none',
                                        boxShadow:
                                            '0 4px 6px -1px rgb(0 0 0 / 0.1)',
                                        fontSize: '12px',
                                    }}
                                    formatter={(v: number) => [
                                        `$${v.toLocaleString(undefined, { maximumFractionDigits: 0 })}`,
                                        'Capital Needed',
                                    ]}
                                    labelFormatter={(v) =>
                                        `Service Level: ${v}%`
                                    }
                                />
                                <Area
                                    type="monotone"
                                    dataKey="cost"
                                    stroke="#d97706"
                                    strokeWidth={3}
                                    fill="url(#colorCost)"
                                    animationDuration={500}
                                />
                                {/* Active Dot */}
                                {/* We can't easily put a dot here via props without complex customized shape, but Recharts handles tooltips well */}
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default PolicyDeck;
