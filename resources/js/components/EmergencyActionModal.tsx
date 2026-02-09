import { AlertTriangle, ArrowRight, Truck, X, Zap } from 'lucide-react';
import React, { useState } from 'react';

import { EmergencyOption, Item, SpikeSignal } from '../types';

interface EmergencyActionModalProps {
    isOpen: boolean;
    onClose: () => void;
    signal: SpikeSignal;
    item: Item;
    options: EmergencyOption[];
    onSelectOption: (option: EmergencyOption) => void;
}

const EmergencyActionModal: React.FC<EmergencyActionModalProps> = ({
    isOpen,
    onClose,
    signal,
    item,
    options,
    onSelectOption,
}) => {
    const [selectedId, setSelectedId] = useState<string | null>(null);

    if (!isOpen) return null;

    const handleConfirm = () => {
        const opt = options.find((o) => o.id === selectedId);
        if (opt) onSelectOption(opt);
    };

    return (
        <div className="fixed inset-0 z-50 flex animate-in items-center justify-center bg-stone-900/80 p-4 backdrop-blur-sm duration-200 fade-in">
            <div className="w-full max-w-lg animate-in overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl duration-200 zoom-in-95">
                {/* Header - Urgency Theme */}
                <div className="flex items-start justify-between bg-amber-500 p-6 text-white">
                    <div>
                        <div className="mb-2 flex w-fit items-center gap-2 rounded bg-black/20 px-2 py-1 text-xs font-bold tracking-wider uppercase">
                            <Zap size={12} className="fill-current" /> Emergency
                            Response
                        </div>
                        <h2 className="text-2xl leading-none font-bold">
                            Stockout Imminent
                        </h2>
                        <p className="mt-2 text-sm opacity-90">
                            {item.name} usage is{' '}
                            <strong>{signal.multiplier}x</strong> normal pace.
                            <br />
                            Projected dry at{' '}
                            <strong>{signal.shortageAt}</strong>.
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-full p-2 transition-colors hover:bg-white/20"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="space-y-6 p-6">
                    <div className="text-sm font-medium text-stone-600">
                        Select an action plan to mitigate revenue loss:
                    </div>

                    <div className="space-y-3">
                        {options.map((opt) => (
                            <div
                                key={opt.id}
                                onClick={() => setSelectedId(opt.id)}
                                className={`relative cursor-pointer rounded-xl border-2 p-4 transition-all ${
                                    selectedId === opt.id
                                        ? 'border-amber-500 bg-amber-50 shadow-md ring-1 ring-amber-500/30'
                                        : 'border-stone-100 hover:border-amber-300 hover:bg-stone-50'
                                }`}
                            >
                                {opt.recommended && (
                                    <span className="absolute top-0 right-0 rounded-tr-sm rounded-bl-lg bg-emerald-500 px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">
                                        Recommended
                                    </span>
                                )}

                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div
                                            className={`flex h-10 w-10 items-center justify-center rounded-full ${
                                                opt.type === 'COURIER'
                                                    ? 'bg-amber-100 text-amber-600'
                                                    : opt.type === 'IGNORE'
                                                      ? 'bg-stone-100 text-stone-500'
                                                      : 'bg-blue-100 text-blue-600'
                                            }`}
                                        >
                                            {opt.type === 'COURIER' && (
                                                <Zap
                                                    size={20}
                                                    className="fill-current"
                                                />
                                            )}
                                            {opt.type === 'VENDOR_EXPEDITE' && (
                                                <Truck size={20} />
                                            )}
                                            {opt.type === 'IGNORE' && (
                                                <AlertTriangle size={20} />
                                            )}
                                            {opt.type === 'TRANSFER' && (
                                                <ArrowRight size={20} />
                                            )}
                                        </div>
                                        <div>
                                            <div className="font-bold text-stone-900">
                                                {opt.providerName}
                                            </div>
                                            <div className="flex items-center gap-1 text-xs text-stone-500">
                                                {opt.type === 'COURIER'
                                                    ? 'Instant Delivery'
                                                    : opt.type === 'IGNORE'
                                                      ? 'Revenue Risk'
                                                      : 'Standard Expedite'}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-lg font-bold text-stone-900">
                                            ${opt.cost.toFixed(0)}
                                        </div>
                                        <div
                                            className={`text-xs font-bold ${opt.etaHours < 2 ? 'text-emerald-600' : 'text-stone-500'}`}
                                        >
                                            {opt.etaLabel}
                                        </div>
                                    </div>
                                </div>
                                {opt.riskDescription && (
                                    <div className="mt-3 flex items-start gap-1.5 rounded border border-stone-100 bg-white/50 p-2 text-xs text-stone-500">
                                        <AlertTriangle
                                            size={12}
                                            className="mt-0.5 flex-shrink-0 text-amber-500"
                                        />
                                        {opt.riskDescription}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex justify-end gap-3 border-t border-stone-100 bg-stone-50 p-4">
                    <button
                        onClick={onClose}
                        className="rounded-lg px-4 py-2 text-sm font-bold text-stone-500 transition-colors hover:bg-stone-200"
                    >
                        Dismiss
                    </button>
                    <button
                        onClick={handleConfirm}
                        disabled={!selectedId}
                        className="flex items-center gap-2 rounded-lg bg-stone-900 px-6 py-2 text-sm font-bold text-white shadow-lg shadow-stone-900/10 transition-all hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Execute Plan <ArrowRight size={16} />
                    </button>
                </div>
            </div>
        </div>
    );
};

export default EmergencyActionModal;
