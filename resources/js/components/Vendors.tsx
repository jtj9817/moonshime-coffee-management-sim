import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Search, Filter, Star, Truck, ShieldCheck, Box } from 'lucide-react';
import { SUPPLIERS } from '../constants';
import { ItemCategory } from '../types';

const Vendors: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [minReliability, setMinReliability] = useState(0);

  const filteredSuppliers = SUPPLIERS.filter(s => {
    const matchesSearch = s.name.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = categoryFilter === 'all' || s.categories.includes(categoryFilter as ItemCategory);
    const matchesReliability = s.reliability >= minReliability;
    return matchesSearch && matchesCategory && matchesReliability;
  });

  const categories = Object.values(ItemCategory);

  return (
    <div className="space-y-6 animate-in fade-in duration-500 pb-20">
      
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
          <h2 className="text-2xl font-bold text-stone-900">Vendor Directory</h2>
          <p className="text-stone-500">Manage approved suppliers and monitor performance.</p>
        </div>
      </div>

      {/* Control Bar */}
      <div className="bg-white p-4 rounded-xl border border-stone-200 shadow-sm flex flex-col md:flex-row gap-4">
        <div className="relative flex-1">
           <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-stone-400" size={16} />
           <input 
              type="text" 
              placeholder="Search Vendor Name..." 
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-9 pr-4 py-2 bg-stone-50 border border-stone-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
           />
        </div>
        
        <select 
           value={categoryFilter}
           onChange={(e) => setCategoryFilter(e.target.value)}
           className="bg-stone-50 border border-stone-200 text-stone-700 py-2 px-3 rounded-lg text-sm outline-none"
        >
           <option value="all">All Categories</option>
           {categories.map(c => <option key={c} value={c}>{c}</option>)}
        </select>

        <div className="flex items-center gap-3 bg-stone-50 px-3 rounded-lg border border-stone-200">
           <span className="text-xs text-stone-500 font-medium">Min Reliability:</span>
           <input 
             type="range" 
             min="0" max="1" step="0.1" 
             value={minReliability}
             onChange={(e) => setMinReliability(parseFloat(e.target.value))}
             className="w-24 accent-amber-600"
           />
           <span className="text-xs font-bold text-stone-700">{(minReliability * 100).toFixed(0)}%</span>
        </div>
      </div>

      {/* Vendor Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
         {filteredSuppliers.map(supplier => (
            <Link key={supplier.id} to={`/vendors/${supplier.id}`} className="group">
              <div className="bg-white border border-stone-200 rounded-xl overflow-hidden hover:shadow-lg hover:border-amber-200 transition-all h-full flex flex-col">
                 <div className="p-6 flex-1">
                    <div className="flex justify-between items-start mb-4">
                       <div className="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-lg border border-amber-100">
                          {supplier.name.substring(0, 2).toUpperCase()}
                       </div>
                       <div className={`flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full ${
                          supplier.reliability >= 0.9 ? 'bg-emerald-100 text-emerald-700' : 
                          supplier.reliability >= 0.8 ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'
                       }`}>
                          <ShieldCheck size={12} /> {(supplier.reliability * 100).toFixed(0)}%
                       </div>
                    </div>
                    
                    <h3 className="text-lg font-bold text-stone-900 group-hover:text-amber-600 transition-colors mb-1">{supplier.name}</h3>
                    <p className="text-xs text-stone-500 line-clamp-2 min-h-[2.5em]">{supplier.description}</p>
                    
                    <div className="flex flex-wrap gap-1.5 mt-4">
                       {supplier.categories.map(cat => (
                          <span key={cat} className="text-[10px] bg-stone-100 text-stone-600 px-2 py-0.5 rounded border border-stone-200">
                             {cat}
                          </span>
                       ))}
                    </div>
                 </div>

                 <div className="px-6 py-4 bg-stone-50/50 border-t border-stone-100 flex justify-between items-center text-xs">
                    <div className="flex items-center gap-1 text-stone-500">
                       <Truck size={14} /> 
                       <span>{supplier.deliverySpeed} ({supplier.metrics?.lateRate ? ((1-supplier.metrics.lateRate)*100).toFixed(0) + '% On Time' : 'N/A'})</span>
                    </div>
                    {supplier.freeShippingThreshold < 10000 && (
                       <div className="flex items-center gap-1 text-emerald-600 font-medium">
                          <Box size={14} />
                          <span>Free ship @ ${supplier.freeShippingThreshold}</span>
                       </div>
                    )}
                 </div>
              </div>
            </Link>
         ))}
      </div>
      
      {filteredSuppliers.length === 0 && (
         <div className="text-center py-20 text-stone-400">
            <Filter size={48} className="mx-auto mb-4 opacity-20" />
            <p>No vendors match your current filters.</p>
         </div>
      )}
    </div>
  );
};

export default Vendors;