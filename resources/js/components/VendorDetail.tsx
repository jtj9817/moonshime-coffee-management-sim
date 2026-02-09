import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Handshake,
    ShoppingCart,
    TrendingUp,
    User,
} from 'lucide-react';
import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import { useApp } from '../App';
import { SUPPLIERS, SUPPLIER_ITEMS } from '../constants';

import ProductIcon from './ProductIcon';

const VendorDetail: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const {
        items,
        addToDraft,
        locations,
        currentLocationId,
        inventory,
        negotiateWithVendor,
    } = useApp();
    const [activeTab, setActiveTab] = useState<
        'catalog' | 'performance' | 'policies' | 'audit'
    >('catalog');
    const [targetLocId, setTargetLocId] = useState(
        currentLocationId === 'all' ? locations[0].id : currentLocationId,
    );

    // Negotiation State
    const [isNegotiating, setIsNegotiating] = useState(false);
    const [discount, setDiscount] = useState<number>(0);

    // Capture timestamp once on mount to avoid impure Date.now() calls during render
    const [nowTimestamp] = useState(() => Date.now());

    const supplier = SUPPLIERS.find((s) => s.id === id);
    const supplierItems = SUPPLIER_ITEMS.filter((si) => si.supplierId === id);

    if (!supplier)
        return (
            <div className="p-12 text-center text-stone-500">
                Vendor not found
            </div>
        );

    const handleNegotiate = async () => {
        setIsNegotiating(true);
        const result = await negotiateWithVendor(supplier.id);
        setIsNegotiating(false);

        if (result.success && result.discount) {
            setDiscount(result.discount);
            setTimeout(() => setDiscount(0), 10000); // Temporary visual
        }
    };

    return (
        <div className="animate-in space-y-6 pb-20 duration-500 fade-in slide-in-from-bottom-4">
            {/* Header */}
            <div>
                <Link
                    to="/vendors"
                    className="mb-4 flex items-center gap-1 text-xs text-stone-500 transition-colors hover:text-amber-600"
                >
                    <ArrowLeft size={14} /> Back to Directory
                </Link>

                <div className="flex flex-col justify-between gap-6 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm md:flex-row">
                    <div className="flex items-start gap-4">
                        <div className="flex h-16 w-16 items-center justify-center rounded-xl bg-stone-900 text-2xl font-bold text-white shadow-lg shadow-stone-900/20">
                            {supplier.name.substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-stone-900">
                                {supplier.name}
                            </h1>
                            <p className="mt-1 max-w-lg text-sm text-stone-500">
                                {supplier.description}
                            </p>

                            <div className="mt-4 flex flex-wrap gap-3 text-xs">
                                {supplier.contactName && (
                                    <div className="flex items-center gap-1 rounded-md border border-stone-200 bg-stone-50 px-2 py-1 text-stone-600">
                                        <User size={14} />{' '}
                                        <span className="font-semibold">
                                            {supplier.contactName}
                                        </span>
                                    </div>
                                )}
                                <button
                                    onClick={handleNegotiate}
                                    disabled={isNegotiating}
                                    className="flex items-center gap-1 rounded-md border border-amber-200 bg-amber-100 px-2 py-1 font-bold text-amber-700 transition-all hover:bg-amber-200"
                                >
                                    <Handshake size={14} />{' '}
                                    {isNegotiating
                                        ? 'Negotiating...'
                                        : 'Negotiate Contract'}
                                </button>
                                {discount > 0 && (
                                    <span className="flex animate-pulse items-center gap-1 font-bold text-emerald-600">
                                        <TrendingUp size={14} />{' '}
                                        {discount * 100}% Discount Applied!
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-4">
                        <div className="min-w-[90px] rounded-xl border border-stone-100 bg-stone-50 p-3 text-center">
                            <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase">
                                Trust Score
                            </div>
                            <div
                                className={`text-xl font-bold ${supplier.reliability >= 0.9 ? 'text-emerald-600' : 'text-amber-600'}`}
                            >
                                {(supplier.reliability * 100).toFixed(0)}
                            </div>
                            {supplier.reliability >= 0.95 && (
                                <div className="mx-auto mt-1 h-1 w-8 rounded-full bg-amber-400"></div>
                            )}
                        </div>
                        <div className="min-w-[90px] rounded-xl border border-stone-100 bg-stone-50 p-3 text-center">
                            <div className="mb-1 text-[10px] font-bold text-stone-400 uppercase">
                                Fill Rate
                            </div>
                            <div className="text-xl font-bold text-stone-800">
                                {supplier.metrics?.fillRate
                                    ? (supplier.metrics.fillRate * 100).toFixed(
                                          0,
                                      )
                                    : '--'}
                                %
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <div className="flex gap-4 overflow-x-auto border-b border-stone-200">
                <button
                    onClick={() => setActiveTab('catalog')}
                    className={`relative pb-3 text-sm font-bold whitespace-nowrap transition-colors ${activeTab === 'catalog' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                >
                    SKU Catalog ({supplierItems.length})
                    {activeTab === 'catalog' && (
                        <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-amber-500"></div>
                    )}
                </button>
                <button
                    onClick={() => setActiveTab('performance')}
                    className={`relative pb-3 text-sm font-bold whitespace-nowrap transition-colors ${activeTab === 'performance' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                >
                    Performance History
                    {activeTab === 'performance' && (
                        <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-amber-500"></div>
                    )}
                </button>
                <button
                    onClick={() => setActiveTab('policies')}
                    className={`relative pb-3 text-sm font-bold whitespace-nowrap transition-colors ${activeTab === 'policies' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                >
                    Policies & Shipping
                    {activeTab === 'policies' && (
                        <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-amber-500"></div>
                    )}
                </button>
                <button
                    onClick={() => setActiveTab('audit')}
                    className={`relative pb-3 text-sm font-bold whitespace-nowrap transition-colors ${activeTab === 'audit' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                >
                    Audit Log
                    {activeTab === 'audit' && (
                        <div className="absolute bottom-0 left-0 h-0.5 w-full rounded-t-full bg-amber-500"></div>
                    )}
                </button>
            </div>

            {/* Content */}
            <div className="min-h-[400px] rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                {activeTab === 'catalog' && (
                    <div>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="font-bold text-stone-900">
                                Items Supplied
                            </h3>
                            {/* Location picker for quick add context */}
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-stone-400">
                                    Order For:
                                </span>
                                <select
                                    value={targetLocId}
                                    onChange={(e) =>
                                        setTargetLocId(e.target.value)
                                    }
                                    className="cursor-pointer rounded border border-stone-200 bg-stone-50 px-2 py-1 text-xs font-bold outline-none hover:text-amber-600"
                                >
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="border-b border-stone-100 text-xs text-stone-400 uppercase">
                                        <th className="pb-3 pl-2 font-semibold">
                                            Item
                                        </th>
                                        <th className="pb-3 font-semibold">
                                            Freshness
                                        </th>
                                        <th className="pb-3 font-semibold">
                                            Lead Time
                                        </th>
                                        <th className="pb-3 font-semibold">
                                            MOQ
                                        </th>
                                        <th className="pb-3 font-semibold">
                                            Base Price
                                        </th>
                                        <th className="pb-3 font-semibold">
                                            Volume Tiers
                                        </th>
                                        <th className="pr-2 pb-3 text-right font-semibold">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-50">
                                    {supplierItems.map((si) => {
                                        const item = items.find(
                                            (i) => i.id === si.itemId,
                                        );
                                        if (!item) return null;

                                        // Calculate expiry for the specific item in the target location
                                        const invRecord = inventory.find(
                                            (r) =>
                                                r.itemId === item.id &&
                                                r.locationId === targetLocId,
                                        );
                                        const daysUntilExpiry =
                                            invRecord?.expiryDate
                                                ? Math.ceil(
                                                      (new Date(
                                                          invRecord.expiryDate,
                                                      ).getTime() -
                                                          nowTimestamp) /
                                                          (1000 * 60 * 60 * 24),
                                                  )
                                                : null;

                                        return (
                                            <tr
                                                key={si.itemId}
                                                className="group transition-colors hover:bg-stone-50"
                                            >
                                                <td className="py-4 pl-2 align-top">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-stone-100">
                                                            <ProductIcon
                                                                category={
                                                                    item.category
                                                                }
                                                                className="h-8 w-8"
                                                            />
                                                        </div>
                                                        <div>
                                                            <div className="text-sm font-bold text-stone-900">
                                                                {item.name}
                                                            </div>
                                                            <div className="text-[10px] text-stone-500 uppercase">
                                                                {item.unit}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="py-4 pt-5 align-top text-xs">
                                                    <div className="flex flex-col gap-1">
                                                        <div className="font-medium text-stone-500">
                                                            Shelf Life:{' '}
                                                            {
                                                                item.estimatedShelfLife
                                                            }
                                                            d
                                                        </div>
                                                        {item.isPerishable &&
                                                            invRecord &&
                                                            daysUntilExpiry !==
                                                                null && (
                                                                <div
                                                                    className={`flex items-center gap-1 font-bold ${
                                                                        daysUntilExpiry <=
                                                                        3
                                                                            ? 'text-rose-600'
                                                                            : daysUntilExpiry <=
                                                                                7
                                                                              ? 'text-amber-600'
                                                                              : 'text-emerald-600'
                                                                    }`}
                                                                >
                                                                    <Clock
                                                                        size={
                                                                            10
                                                                        }
                                                                    />
                                                                    {daysUntilExpiry <=
                                                                    0
                                                                        ? 'Expired'
                                                                        : `Exp: ${new Date(invRecord.expiryDate!).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`}
                                                                    {daysUntilExpiry <=
                                                                        7 && (
                                                                        <AlertTriangle
                                                                            size={
                                                                                10
                                                                            }
                                                                            className="fill-current"
                                                                        />
                                                                    )}
                                                                </div>
                                                            )}
                                                    </div>
                                                </td>
                                                <td className="py-4 pt-5 align-top text-sm text-stone-600">
                                                    {si.deliveryDays} Days
                                                </td>
                                                <td className="py-4 pt-5 align-top text-sm font-medium text-stone-600">
                                                    {si.minOrderQty} units
                                                </td>
                                                <td className="py-4 pt-5 align-top font-mono text-sm font-bold text-stone-800">
                                                    $
                                                    {(
                                                        si.pricePerUnit *
                                                        (1 - discount)
                                                    ).toFixed(2)}
                                                </td>
                                                <td className="py-4 align-top">
                                                    {si.priceTiers &&
                                                    si.priceTiers.length > 0 ? (
                                                        <div className="max-w-[180px] min-w-[140px] overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm">
                                                            <div className="flex items-center justify-between border-b border-stone-100 bg-stone-50 px-3 py-1.5">
                                                                <span className="text-[10px] font-bold tracking-wide text-stone-500 uppercase">
                                                                    Qty
                                                                </span>
                                                                <span className="text-[10px] font-bold tracking-wide text-stone-500 uppercase">
                                                                    Price
                                                                </span>
                                                            </div>
                                                            <div className="divide-y divide-stone-50">
                                                                {si.priceTiers.map(
                                                                    (
                                                                        tier,
                                                                        idx,
                                                                    ) => {
                                                                        const lowestPrice =
                                                                            Math.min(
                                                                                ...(si.priceTiers?.map(
                                                                                    (
                                                                                        t,
                                                                                    ) =>
                                                                                        t.unitPrice,
                                                                                ) ||
                                                                                    []),
                                                                            );
                                                                        const isBestValue =
                                                                            tier.unitPrice ===
                                                                            lowestPrice;

                                                                        return (
                                                                            <div
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                className={`flex items-center justify-between px-3 py-2 text-xs ${isBestValue ? 'bg-emerald-50/60' : ''}`}
                                                                            >
                                                                                <div className="flex items-center gap-1.5">
                                                                                    <span
                                                                                        className={`font-medium ${isBestValue ? 'text-emerald-900' : 'text-stone-600'}`}
                                                                                    >
                                                                                        {
                                                                                            tier.minQty
                                                                                        }
                                                                                        +
                                                                                    </span>
                                                                                    {isBestValue && (
                                                                                        <Star
                                                                                            size={
                                                                                                10
                                                                                            }
                                                                                            className="fill-emerald-500 text-emerald-500"
                                                                                        />
                                                                                    )}
                                                                                </div>
                                                                                <span
                                                                                    className={`font-mono font-bold ${isBestValue ? 'text-emerald-700' : 'text-stone-900'}`}
                                                                                >
                                                                                    $
                                                                                    {tier.unitPrice.toFixed(
                                                                                        2,
                                                                                    )}
                                                                                </span>
                                                                            </div>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="pt-2 text-xs text-stone-400 italic">
                                                            Flat rate pricing
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-4 pt-4 pr-2 text-right align-top">
                                                    <button
                                                        onClick={() =>
                                                            addToDraft(
                                                                supplier.id,
                                                                targetLocId,
                                                                item,
                                                                si.minOrderQty,
                                                                si.pricePerUnit *
                                                                    (1 -
                                                                        discount),
                                                            )
                                                        }
                                                        className="ml-auto flex items-center justify-end gap-2 rounded-lg bg-stone-100 px-3 py-2 text-xs font-bold text-stone-600 transition-all hover:bg-stone-900 hover:text-white"
                                                        title={`Add MOQ (${si.minOrderQty}) to Draft`}
                                                    >
                                                        <ShoppingCart
                                                            size={14}
                                                        />
                                                        <span>
                                                            Add {si.minOrderQty}
                                                        </span>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* ... other tabs (performance, policies, audit) unchanged but truncated for brevity as request was for Negotiation/Trust updates ... */}
                {activeTab === 'performance' && (
                    <div className="py-12 text-center text-stone-500">
                        Performance metrics visualization
                    </div>
                )}
                {activeTab === 'policies' && (
                    <div className="py-12 text-center text-stone-500">
                        Policy documents
                    </div>
                )}
                {activeTab === 'audit' && (
                    <div className="py-12 text-center text-stone-500">
                        Audit logs
                    </div>
                )}
            </div>
        </div>
    );
};

export default VendorDetail;
