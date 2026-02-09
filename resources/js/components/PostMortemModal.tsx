import {
    AlertOctagon,
    CheckCircle2,
    MessageSquare,
    Save,
    X,
} from 'lucide-react';
import React, { useState } from 'react';

import { Item, SpikeFeedback, SpikeSignal } from '../types';

interface PostMortemModalProps {
    isOpen: boolean;
    onClose: () => void;
    signal: SpikeSignal;
    item: Item;
    onComplete: (feedback: SpikeFeedback) => void;
    defaultIsFalsePositive?: boolean;
}

const PostMortemModal: React.FC<PostMortemModalProps> = ({
    isOpen,
    onClose,
    item,
    onComplete,
    defaultIsFalsePositive = false,
}) => {
    const [isFalsePositive, setIsFalsePositive] = useState(
        defaultIsFalsePositive,
    );
    const [classification, setClassification] = useState<
        SpikeFeedback['classification']
    >(defaultIsFalsePositive ? 'DATA_ERROR' : 'REAL_DEMAND');
    const [rootCause, setRootCause] =
        useState<SpikeFeedback['rootCause']>('UNKNOWN');
    const [notes, setNotes] = useState('');

    if (!isOpen) return null;

    const handleSubmit = () => {
        onComplete({
            isFalsePositive,
            classification,
            rootCause: isFalsePositive ? undefined : rootCause,
            notes,
        });
    };

    return (
        <div className="fixed inset-0 z-[60] flex animate-in items-center justify-center bg-stone-900/60 p-4 backdrop-blur-sm duration-200 fade-in">
            <div className="w-full max-w-md animate-in overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl duration-200 zoom-in-95">
                <div className="flex items-center justify-between border-b border-stone-100 bg-stone-50 p-5">
                    <h3 className="flex items-center gap-2 font-bold text-stone-900">
                        <MessageSquare size={18} className="text-indigo-500" />
                        Event Post-mortem
                    </h3>
                    <button
                        onClick={onClose}
                        className="rounded-full p-2 text-stone-500 transition-colors hover:bg-stone-200"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="space-y-6 p-6">
                    <div>
                        <p className="mb-4 text-sm text-stone-600">
                            Help refine the detection algorithm. Was this spike
                            for{' '}
                            <span className="font-bold text-stone-900">
                                {item.name}
                            </span>{' '}
                            a valid anomaly?
                        </p>

                        <div className="grid grid-cols-2 gap-3">
                            <button
                                onClick={() => {
                                    setIsFalsePositive(false);
                                    setClassification('REAL_DEMAND');
                                }}
                                className={`flex flex-col items-center gap-2 rounded-xl border p-3 text-sm font-bold transition-all ${
                                    !isFalsePositive
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500/20'
                                        : 'border-stone-200 bg-white text-stone-500 hover:border-stone-300'
                                }`}
                            >
                                <CheckCircle2 size={24} />
                                Valid Event
                            </button>
                            <button
                                onClick={() => {
                                    setIsFalsePositive(true);
                                    setClassification('DATA_ERROR');
                                }}
                                className={`flex flex-col items-center gap-2 rounded-xl border p-3 text-sm font-bold transition-all ${
                                    isFalsePositive
                                        ? 'border-rose-500 bg-rose-50 text-rose-700 ring-1 ring-rose-500/20'
                                        : 'border-stone-200 bg-white text-stone-500 hover:border-stone-300'
                                }`}
                            >
                                <AlertOctagon size={24} />
                                False Alarm
                            </button>
                        </div>
                    </div>

                    {/* Dynamic Fields based on Type */}
                    <div className="animate-in space-y-4 duration-300 fade-in">
                        <div className="space-y-2">
                            <label className="text-xs font-bold text-stone-500 uppercase">
                                Primary Classification
                            </label>
                            <select
                                value={classification}
                                onChange={(e) =>
                                    setClassification(
                                        e.target
                                            .value as SpikeFeedback['classification'],
                                    )
                                }
                                className="w-full rounded-lg border border-stone-200 bg-stone-50 p-2.5 text-sm font-medium outline-none focus:border-indigo-500"
                            >
                                {!isFalsePositive ? (
                                    <>
                                        <option value="REAL_DEMAND">
                                            Real Demand Surge
                                        </option>
                                        <option value="ONE_OFF_EVENT">
                                            One-off Local Event
                                        </option>
                                        <option value="OPERATIONAL_WASTE">
                                            Accidental Spillage/Waste
                                        </option>
                                    </>
                                ) : (
                                    <>
                                        <option value="DATA_ERROR">
                                            Sensor/Data Error
                                        </option>
                                        <option value="SYSTEM_GLITCH">
                                            Algorithm Sensitivity
                                        </option>
                                    </>
                                )}
                            </select>
                        </div>

                        {!isFalsePositive && (
                            <div className="space-y-2">
                                <label className="text-xs font-bold text-stone-500 uppercase">
                                    Likely Root Cause
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        'WEATHER',
                                        'LOCAL_EVENT',
                                        'PROMOTION',
                                        'SUPPLY_FAILURE',
                                        'UNKNOWN',
                                    ].map((cause) => (
                                        <button
                                            key={cause}
                                            onClick={() =>
                                                setRootCause(
                                                    cause as SpikeFeedback['rootCause'],
                                                )
                                            }
                                            className={`rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors ${
                                                rootCause === cause
                                                    ? 'border-stone-800 bg-stone-800 text-white'
                                                    : 'border-stone-200 bg-white text-stone-600 hover:bg-stone-50'
                                            }`}
                                        >
                                            {cause.replace('_', ' ')}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="space-y-2">
                            <label className="text-xs font-bold text-stone-500 uppercase">
                                Context Notes
                            </label>
                            <textarea
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="Briefly describe what happened..."
                                className="min-h-[80px] w-full rounded-lg border border-stone-200 bg-stone-50 p-3 text-sm outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>
                </div>

                <div className="flex justify-end border-t border-stone-100 bg-stone-50 p-4">
                    <button
                        onClick={handleSubmit}
                        className="flex items-center gap-2 rounded-lg bg-stone-900 px-6 py-2.5 text-sm font-bold text-white shadow-lg transition-all hover:bg-stone-800"
                    >
                        <Save size={16} /> Complete & Archive
                    </button>
                </div>
            </div>
        </div>
    );
};

export default PostMortemModal;
