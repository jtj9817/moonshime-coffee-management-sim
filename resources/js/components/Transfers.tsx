import {
    AlertCircle,
    ArrowRight,
    ArrowRightLeft,
    CheckCircle2,
    Package,
    Plus,
    Truck,
    X,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';

import { useApp } from '../App';
import { generateTransferSuggestions } from '../services/transferService';
import { TransferSuggestion } from '../types';

import ProductIcon from './ProductIcon';

const Transfers: React.FC = () => {
    const {
        inventory,
        items,
        locations,
        transfers,
        createTransfer,
        updateTransferStatus,
    } = useApp();
    const [activeTab, setActiveTab] = useState<
        'active' | 'suggestions' | 'history'
    >('active');
    const [isModalOpen, setIsModalOpen] = useState(false);

    // Modal State
    const [sourceId, setSourceId] = useState('');
    const [targetId, setTargetId] = useState('');
    const [selectedSku, setSelectedSku] = useState('');
    const [qty, setQty] = useState(0);

    // Derived Data
    const suggestions = useMemo(
        () => generateTransferSuggestions(inventory, items, locations),
        [inventory, items, locations],
    );

    const activeTransfers = transfers.filter(
        (t) => t.status !== 'COMPLETED' && t.status !== 'CANCELLED',
    );
    const historyTransfers = transfers.filter(
        (t) => t.status === 'COMPLETED' || t.status === 'CANCELLED',
    );

    // Matrix View Helper
    const getStockMatrix = (skuId: string) => {
        return locations.map((loc) => {
            const rec = inventory.find(
                (r) => r.locationId === loc.id && r.itemId === skuId,
            );
            return { location: loc, qty: rec?.quantity || 0 };
        });
    };

    const handleOpenModal = (prefill?: Partial<TransferSuggestion>) => {
        setSourceId(prefill?.sourceLocationId || locations[0].id);
        setTargetId(prefill?.targetLocationId || locations[1].id);
        setSelectedSku(prefill?.skuId || items[0].id);
        setQty(prefill?.qty || 0);
        setIsModalOpen(true);
    };

    const handleSubmitTransfer = () => {
        if (qty <= 0) return;
        createTransfer(sourceId, targetId, [{ skuId: selectedSku, qty }]);
        setIsModalOpen(false);
    };

    return (
        <div className="animate-in space-y-6 pb-20 duration-500 fade-in">
            {/* Header */}
            <div className="flex items-end justify-between">
                <div>
                    <h2 className="text-2xl font-bold text-stone-900">
                        Stock Transfers
                    </h2>
                    <p className="text-stone-500">
                        Balance inventory levels between locations.
                    </p>
                </div>
                <button
                    onClick={() => handleOpenModal()}
                    className="flex items-center gap-2 rounded-lg bg-stone-900 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-stone-800"
                >
                    <Plus size={16} /> New Transfer
                </button>
            </div>

            {/* Tabs */}
            <div className="flex w-fit gap-1 rounded-xl bg-stone-100 p-1">
                <button
                    onClick={() => setActiveTab('active')}
                    className={`rounded-lg px-4 py-2 text-sm font-medium transition-all ${activeTab === 'active' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'}`}
                >
                    In Transit ({activeTransfers.length})
                </button>
                <button
                    onClick={() => setActiveTab('suggestions')}
                    className={`flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all ${activeTab === 'suggestions' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'}`}
                >
                    Suggestions
                    {suggestions.length > 0 && (
                        <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">
                            {suggestions.length}
                        </span>
                    )}
                </button>
                <button
                    onClick={() => setActiveTab('history')}
                    className={`rounded-lg px-4 py-2 text-sm font-medium transition-all ${activeTab === 'history' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'}`}
                >
                    History
                </button>
            </div>

            {/* Content */}
            <div className="min-h-[400px]">
                {activeTab === 'active' && (
                    <div className="space-y-4">
                        {activeTransfers.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-stone-200 bg-white py-16 text-center text-stone-400">
                                <Truck
                                    size={48}
                                    className="mx-auto mb-4 opacity-20"
                                />
                                <p>No active transfers.</p>
                            </div>
                        ) : (
                            activeTransfers.map((t) => {
                                const source = locations.find(
                                    (l) => l.id === t.sourceLocationId,
                                );
                                const target = locations.find(
                                    (l) => l.id === t.targetLocationId,
                                );
                                const itemCount = t.items.reduce(
                                    (acc, i) => acc + i.qty,
                                    0,
                                );

                                return (
                                    <div
                                        key={t.id}
                                        className="rounded-xl border border-stone-200 bg-white p-6 shadow-sm transition-colors hover:border-amber-200"
                                    >
                                        <div className="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                                    <Truck size={24} />
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2 text-sm font-bold text-stone-900">
                                                        <span>
                                                            {source?.name}
                                                        </span>
                                                        <ArrowRight
                                                            size={14}
                                                            className="text-stone-400"
                                                        />
                                                        <span>
                                                            {target?.name}
                                                        </span>
                                                    </div>
                                                    <div className="mt-1 flex items-center gap-2 text-xs text-stone-500">
                                                        <span className="rounded bg-stone-100 px-1 font-mono">
                                                            #{t.id}
                                                        </span>
                                                        <span>•</span>
                                                        <span>
                                                            {itemCount} units
                                                        </span>
                                                        <span>•</span>
                                                        <span className="font-medium text-blue-600">
                                                            ETA:{' '}
                                                            {new Date(
                                                                t.estimatedArrival,
                                                            ).toLocaleTimeString(
                                                                [],
                                                                {
                                                                    hour: '2-digit',
                                                                    minute: '2-digit',
                                                                },
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex w-full items-center gap-3 md:w-auto">
                                                <button className="rounded-lg border border-stone-200 px-3 py-2 text-xs font-medium text-stone-600 hover:bg-stone-50">
                                                    View Manifest
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        updateTransferStatus(
                                                            t.id,
                                                            'COMPLETED',
                                                        )
                                                    }
                                                    className="flex items-center gap-2 rounded-lg bg-stone-900 px-3 py-2 text-xs font-bold text-white hover:bg-stone-800"
                                                >
                                                    <CheckCircle2 size={14} />{' '}
                                                    Receive Stock
                                                </button>
                                            </div>
                                        </div>

                                        {/* Item Preview */}
                                        <div className="mt-4 grid grid-cols-1 gap-2 border-t border-stone-100 pt-4 sm:grid-cols-2">
                                            {t.items.map((line, idx) => {
                                                const item = items.find(
                                                    (i) => i.id === line.skuId,
                                                );
                                                return (
                                                    <div
                                                        key={idx}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        <div className="flex h-8 w-8 items-center justify-center rounded bg-stone-100">
                                                            {item && (
                                                                <ProductIcon
                                                                    category={
                                                                        item.category
                                                                    }
                                                                    className="h-6 w-6"
                                                                />
                                                            )}
                                                        </div>
                                                        <div>
                                                            <div className="font-medium text-stone-900">
                                                                {item?.name}
                                                            </div>
                                                            <div className="text-xs text-stone-500">
                                                                {line.qty}{' '}
                                                                {item?.unit}
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                )}

                {activeTab === 'suggestions' && (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {suggestions.length === 0 ? (
                            <div className="col-span-full rounded-2xl border border-dashed border-stone-200 bg-white py-16 text-center text-stone-400">
                                <CheckCircle2
                                    size={48}
                                    className="mx-auto mb-4 opacity-20"
                                />
                                <p>
                                    Inventory is balanced. No transfers
                                    recommended.
                                </p>
                            </div>
                        ) : (
                            suggestions.map((s) => {
                                const item = items.find(
                                    (i) => i.id === s.skuId,
                                );
                                const source = locations.find(
                                    (l) => l.id === s.sourceLocationId,
                                );
                                const target = locations.find(
                                    (l) => l.id === s.targetLocationId,
                                );

                                return (
                                    <div
                                        key={s.id}
                                        className="relative overflow-hidden rounded-xl border border-stone-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
                                    >
                                        <div className="absolute top-0 right-0 -mt-10 -mr-10 h-24 w-24 rounded-full bg-gradient-to-br from-emerald-50 to-transparent"></div>

                                        <div className="relative z-10 mb-4 flex items-start justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="rounded-lg bg-emerald-100 p-2 text-emerald-700">
                                                    <ArrowRightLeft size={20} />
                                                </div>
                                                <div>
                                                    <h3 className="text-sm font-bold text-stone-900">
                                                        {s.reason}
                                                    </h3>
                                                    <p className="text-xs font-medium text-emerald-600">
                                                        Save ${s.savings} •{' '}
                                                        {s.timeSavedDays} days
                                                        faster
                                                    </p>
                                                    <p className="mt-0.5 text-[10px] text-stone-400">
                                                        Est. Cost: $
                                                        {s.transferCost.toFixed(
                                                            2,
                                                        )}{' '}
                                                        ($
                                                        {
                                                            s
                                                                .transferCostBreakdown
                                                                .fixed
                                                        }{' '}
                                                        Fix + $
                                                        {s.transferCostBreakdown.handling.toFixed(
                                                            2,
                                                        )}{' '}
                                                        Var)
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="relative z-10 mb-4 rounded-lg border border-stone-100 bg-stone-50 p-3 text-sm">
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="text-xs font-bold text-stone-500 uppercase">
                                                    Transfer
                                                </span>
                                                <span className="font-bold text-stone-900">
                                                    {s.qty} x {item?.name}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2 text-xs text-stone-600">
                                                <span className="font-semibold">
                                                    {source?.name}
                                                </span>
                                                <ArrowRight
                                                    size={12}
                                                    className="text-stone-400"
                                                />
                                                <span className="font-semibold">
                                                    {target?.name}
                                                </span>
                                            </div>
                                        </div>

                                        <button
                                            onClick={() => handleOpenModal(s)}
                                            className="w-full rounded-lg bg-stone-900 py-2 text-xs font-bold text-white transition-colors hover:bg-stone-800"
                                        >
                                            Review & Approve
                                        </button>
                                    </div>
                                );
                            })
                        )}
                    </div>
                )}

                {activeTab === 'history' && (
                    <div className="overflow-hidden rounded-xl border border-stone-200 bg-white">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-stone-200 bg-stone-50 text-xs text-stone-500 uppercase">
                                <tr>
                                    <th className="px-6 py-3 font-semibold">
                                        ID
                                    </th>
                                    <th className="px-6 py-3 font-semibold">
                                        Route
                                    </th>
                                    <th className="px-6 py-3 font-semibold">
                                        Date
                                    </th>
                                    <th className="px-6 py-3 font-semibold">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100">
                                {historyTransfers.map((t) => {
                                    const source = locations.find(
                                        (l) => l.id === t.sourceLocationId,
                                    );
                                    const target = locations.find(
                                        (l) => l.id === t.targetLocationId,
                                    );
                                    return (
                                        <tr
                                            key={t.id}
                                            className="hover:bg-stone-50"
                                        >
                                            <td className="px-6 py-4 font-mono text-stone-400">
                                                {t.id}
                                            </td>
                                            <td className="px-6 py-4 font-medium text-stone-900">
                                                {source?.name} → {target?.name}
                                            </td>
                                            <td className="px-6 py-4 text-stone-500">
                                                {new Date(
                                                    t.createdDate,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span
                                                    className={`rounded px-2 py-1 text-xs font-bold ${
                                                        t.status === 'COMPLETED'
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-stone-100 text-stone-600'
                                                    }`}
                                                >
                                                    {t.status}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {historyTransfers.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="p-6 text-center text-stone-400"
                                        >
                                            No transfer history.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Creation Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-2xl animate-in overflow-hidden rounded-2xl bg-white shadow-2xl duration-200 zoom-in-95">
                        <div className="flex items-center justify-between border-b border-stone-100 bg-stone-50 p-5">
                            <h3 className="font-bold text-stone-900">
                                Create Stock Transfer
                            </h3>
                            <button
                                onClick={() => setIsModalOpen(false)}
                                className="rounded-full p-2 text-stone-500 hover:bg-stone-200"
                            >
                                <X size={20} />
                            </button>
                        </div>

                        <div className="space-y-6 p-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-xs font-bold text-stone-500 uppercase">
                                        From (Source)
                                    </label>
                                    <select
                                        value={sourceId}
                                        onChange={(e) =>
                                            setSourceId(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-stone-200 p-2 text-sm font-medium"
                                    >
                                        {locations.map((l) => (
                                            <option
                                                key={l.id}
                                                value={l.id}
                                                disabled={l.id === targetId}
                                            >
                                                {l.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-bold text-stone-500 uppercase">
                                        To (Destination)
                                    </label>
                                    <select
                                        value={targetId}
                                        onChange={(e) =>
                                            setTargetId(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-stone-200 p-2 text-sm font-medium"
                                    >
                                        {locations.map((l) => (
                                            <option
                                                key={l.id}
                                                value={l.id}
                                                disabled={l.id === sourceId}
                                            >
                                                {l.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="col-span-2">
                                    <label className="mb-1 block text-xs font-bold text-stone-500 uppercase">
                                        Item
                                    </label>
                                    <select
                                        value={selectedSku}
                                        onChange={(e) =>
                                            setSelectedSku(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-stone-200 p-2 text-sm font-medium"
                                    >
                                        {items.map((i) => (
                                            <option key={i.id} value={i.id}>
                                                {i.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-bold text-stone-500 uppercase">
                                        Quantity
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={qty}
                                        onChange={(e) =>
                                            setQty(parseInt(e.target.value))
                                        }
                                        className="w-full rounded-lg border border-stone-200 p-2 text-sm font-medium"
                                    />
                                </div>
                            </div>

                            {/* Stock Availability Matrix */}
                            <div className="rounded-xl border border-stone-100 bg-stone-50 p-4">
                                <h4 className="mb-3 flex items-center gap-2 text-xs font-bold text-stone-500 uppercase">
                                    <Package size={12} /> Stock Availability
                                    Check
                                </h4>
                                <div className="space-y-2">
                                    {getStockMatrix(selectedSku).map(
                                        ({ location, qty: stock }) => {
                                            const isSource =
                                                location.id === sourceId;
                                            const isTarget =
                                                location.id === targetId;
                                            let statusColor = 'bg-stone-200';
                                            if (isSource)
                                                statusColor =
                                                    stock >= qty
                                                        ? 'bg-emerald-500'
                                                        : 'bg-rose-500';
                                            if (isTarget)
                                                statusColor = 'bg-blue-500';

                                            return (
                                                <div
                                                    key={location.id}
                                                    className="flex items-center justify-between text-sm"
                                                >
                                                    <span
                                                        className={`flex items-center gap-2 ${isSource || isTarget ? 'font-bold text-stone-900' : 'text-stone-500'}`}
                                                    >
                                                        <div
                                                            className={`h-2 w-2 rounded-full ${statusColor}`}
                                                        ></div>
                                                        {location.name}{' '}
                                                        {isSource && '(Source)'}{' '}
                                                        {isTarget && '(Target)'}
                                                    </span>
                                                    <span className="font-mono">
                                                        {stock} units
                                                    </span>
                                                </div>
                                            );
                                        },
                                    )}
                                </div>
                                {(getStockMatrix(selectedSku).find(
                                    (m) => m.location.id === sourceId,
                                )?.qty ?? 0) < qty && (
                                    <div className="mt-3 flex items-center gap-1 text-xs font-bold text-rose-600">
                                        <AlertCircle size={12} /> Source
                                        location has insufficient stock.
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 border-t border-stone-100 bg-stone-50 p-4">
                            <button
                                onClick={() => setIsModalOpen(false)}
                                className="rounded-lg px-4 py-2 text-sm font-bold text-stone-600 transition-colors hover:bg-stone-200"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleSubmitTransfer}
                                disabled={
                                    qty <= 0 ||
                                    (getStockMatrix(selectedSku).find(
                                        (m) => m.location.id === sourceId,
                                    )?.qty ?? 0) < qty
                                }
                                className="rounded-lg bg-stone-900 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Confirm Transfer
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Transfers;
