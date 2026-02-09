import { Box, Filter, Search, ShieldCheck, Truck } from 'lucide-react';
import React, { useState } from 'react';
import { Link } from 'react-router-dom';

import { SUPPLIERS } from '../constants';
import { ItemCategory } from '../types';

const Vendors: React.FC = () => {
    const [searchTerm, setSearchTerm] = useState('');
    const [categoryFilter, setCategoryFilter] = useState<string>('all');
    const [minReliability, setMinReliability] = useState(0);

    const filteredSuppliers = SUPPLIERS.filter((s) => {
        const matchesSearch = s.name
            .toLowerCase()
            .includes(searchTerm.toLowerCase());
        const matchesCategory =
            categoryFilter === 'all' ||
            s.categories.includes(categoryFilter as ItemCategory);
        const matchesReliability = s.reliability >= minReliability;
        return matchesSearch && matchesCategory && matchesReliability;
    });

    const categories = Object.values(ItemCategory);

    return (
        <div className="animate-in space-y-6 pb-20 duration-500 fade-in">
            {/* Header */}
            <div className="flex flex-col items-end justify-between gap-4 md:flex-row">
                <div>
                    <h2 className="text-2xl font-bold text-stone-900">
                        Vendor Directory
                    </h2>
                    <p className="text-stone-500">
                        Manage approved suppliers and monitor performance.
                    </p>
                </div>
            </div>

            {/* Control Bar */}
            <div className="flex flex-col gap-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm md:flex-row">
                <div className="relative flex-1">
                    <Search
                        className="absolute top-1/2 left-3 -translate-y-1/2 transform text-stone-400"
                        size={16}
                    />
                    <input
                        type="text"
                        placeholder="Search Vendor Name..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full rounded-lg border border-stone-200 bg-stone-50 py-2 pr-4 pl-9 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20"
                    />
                </div>

                <select
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value)}
                    className="rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-700 outline-none"
                >
                    <option value="all">All Categories</option>
                    {categories.map((c) => (
                        <option key={c} value={c}>
                            {c}
                        </option>
                    ))}
                </select>

                <div className="flex items-center gap-3 rounded-lg border border-stone-200 bg-stone-50 px-3">
                    <span className="text-xs font-medium text-stone-500">
                        Min Reliability:
                    </span>
                    <input
                        type="range"
                        min="0"
                        max="1"
                        step="0.1"
                        value={minReliability}
                        onChange={(e) =>
                            setMinReliability(parseFloat(e.target.value))
                        }
                        className="w-24 accent-amber-600"
                    />
                    <span className="text-xs font-bold text-stone-700">
                        {(minReliability * 100).toFixed(0)}%
                    </span>
                </div>
            </div>

            {/* Vendor Grid */}
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {filteredSuppliers.map((supplier) => (
                    <Link
                        key={supplier.id}
                        to={`/vendors/${supplier.id}`}
                        className="group"
                    >
                        <div className="flex h-full flex-col overflow-hidden rounded-xl border border-stone-200 bg-white transition-all hover:border-amber-200 hover:shadow-lg">
                            <div className="flex-1 p-6">
                                <div className="mb-4 flex items-start justify-between">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full border border-amber-100 bg-amber-50 text-lg font-bold text-amber-600">
                                        {supplier.name
                                            .substring(0, 2)
                                            .toUpperCase()}
                                    </div>
                                    <div
                                        className={`flex items-center gap-1 rounded-full px-2 py-1 text-xs font-bold ${
                                            supplier.reliability >= 0.9
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : supplier.reliability >= 0.8
                                                  ? 'bg-blue-100 text-blue-700'
                                                  : 'bg-amber-100 text-amber-700'
                                        }`}
                                    >
                                        <ShieldCheck size={12} />{' '}
                                        {(supplier.reliability * 100).toFixed(
                                            0,
                                        )}
                                        %
                                    </div>
                                </div>

                                <h3 className="mb-1 text-lg font-bold text-stone-900 transition-colors group-hover:text-amber-600">
                                    {supplier.name}
                                </h3>
                                <p className="line-clamp-2 min-h-[2.5em] text-xs text-stone-500">
                                    {supplier.description}
                                </p>

                                <div className="mt-4 flex flex-wrap gap-1.5">
                                    {supplier.categories.map((cat) => (
                                        <span
                                            key={cat}
                                            className="rounded border border-stone-200 bg-stone-100 px-2 py-0.5 text-[10px] text-stone-600"
                                        >
                                            {cat}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="flex items-center justify-between border-t border-stone-100 bg-stone-50/50 px-6 py-4 text-xs">
                                <div className="flex items-center gap-1 text-stone-500">
                                    <Truck size={14} />
                                    <span>
                                        {supplier.deliverySpeed} (
                                        {supplier.metrics?.lateRate
                                            ? (
                                                  (1 -
                                                      supplier.metrics
                                                          .lateRate) *
                                                  100
                                              ).toFixed(0) + '% On Time'
                                            : 'N/A'}
                                        )
                                    </span>
                                </div>
                                {supplier.freeShippingThreshold < 10000 && (
                                    <div className="flex items-center gap-1 font-medium text-emerald-600">
                                        <Box size={14} />
                                        <span>
                                            Free ship @ $
                                            {supplier.freeShippingThreshold}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </Link>
                ))}
            </div>

            {filteredSuppliers.length === 0 && (
                <div className="py-20 text-center text-stone-400">
                    <Filter size={48} className="mx-auto mb-4 opacity-20" />
                    <p>No vendors match your current filters.</p>
                </div>
            )}
        </div>
    );
};

export default Vendors;
