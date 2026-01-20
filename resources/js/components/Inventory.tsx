

import { 
  Search, Filter, ArrowUpDown, AlertTriangle, CheckCircle2, Clock, 
  MoreHorizontal, TrendingDown, Truck, Package, ArrowRight, 
  RefreshCw, Copy, Eye, Info, LayoutGrid, List
} from 'lucide-react';
import React, { useState, useMemo, useEffect } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';

import { useApp } from '../App';
import { calculateInventoryPositions } from '../services/inventoryService';
import { InventoryPosition } from '../types';

import ProductIcon from './ProductIcon';

type SortField = 'riskScore' | 'sku' | 'onHand' | 'daysCover' | 'status';
type SortOrder = 'asc' | 'desc';

// --- VISUAL SHELF COMPONENT ---
const ShelfItem: React.FC<{ pos: InventoryPosition }> = ({ pos }) => {
    // Determine visual fullness (0-4 stacks)
    const fillLevel = Math.min(4, Math.floor((pos.onHand / pos.item.bulkThreshold) * 4));
    
    return (
        <Link to={`/inventory/${pos.locationId}/${pos.skuId}`} className="group relative bg-stone-100 rounded-lg p-3 border-b-4 border-stone-300 hover:border-amber-400 hover:-translate-y-1 transition-all h-32 flex flex-col justify-end items-center shadow-inner">
            {/* Visual Stacks */}
            <div className="flex gap-0.5 items-end mb-1">
                 {/* Only show icon if stock > 0 */}
                 {pos.onHand > 0 ? (
                     [...Array(Math.max(1, fillLevel))].map((_, i) => (
                         <div key={i} className={`relative transition-transform group-hover:scale-105 ${i === 0 ? 'z-10' : 'z-0 -ml-2'}`}>
                             <ProductIcon category={pos.item.category} className="w-10 h-10 drop-shadow-md" />
                         </div>
                     ))
                 ) : (
                     <div className="opacity-20 grayscale"><ProductIcon category={pos.item.category} className="w-10 h-10" /></div>
                 )}
            </div>

            {/* Status Indicator */}
            {pos.status.code !== 'OK' && (
                <div className="absolute top-2 right-2">
                    {pos.status.code === 'STOCKOUT_RISK' ? <AlertTriangle size={16} className="text-rose-500 animate-bounce" /> : <Clock size={16} className="text-amber-500" />}
                </div>
            )}

            <div className="w-full text-center">
                <div className="text-xs font-bold text-stone-700 truncate px-1">{pos.item.name}</div>
                <div className={`text-[10px] font-mono font-bold ${pos.onHand <= pos.reorderPoint ? 'text-rose-600' : 'text-stone-500'}`}>
                    {pos.onHand} {pos.item.unit}
                </div>
            </div>
        </Link>
    );
}

const Inventory: React.FC = () => {
  const { inventory, items, locations, currentLocationId, setCurrentLocationId } = useApp();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  // --- State ---
  const [positions, setPositions] = useState<InventoryPosition[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('list');
  
  // Filters
  const [searchText, setSearchText] = useState(searchParams.get('search') || '');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [filterBelowROP, setFilterBelowROP] = useState(false);
  const [filterPerishables, setFilterPerishables] = useState(false);
  
  // Sorting
  const [sortField, setSortField] = useState<SortField>('riskScore');
  const [sortOrder, setSortOrder] = useState<SortOrder>('desc');

  // --- Data Loading ---
  useEffect(() => {
    // Transform raw data into detailed positions
    const pos = calculateInventoryPositions(inventory, items, locations);
    setPositions(pos);
  }, [inventory, items, locations]);

  // --- Filtering & Sorting Logic ---
  const filteredPositions = useMemo(() => {
    return positions.filter(pos => {
      // Location Context
      if (currentLocationId !== 'all' && pos.locationId !== currentLocationId) return false;

      // Text Search
      if (searchText) {
        const query = searchText.toLowerCase();
        if (!pos.item.name.toLowerCase().includes(query) && !pos.skuId.toLowerCase().includes(query)) return false;
      }

      // Category
      if (categoryFilter !== 'all' && pos.item.category !== categoryFilter) return false;

      // Toggles
      if (filterBelowROP && pos.onHand > pos.reorderPoint) return false;
      if (filterPerishables && !pos.item.isPerishable) return false;

      return true;
    }).sort((a, b) => {
      let valA: any = a[sortField as keyof InventoryPosition];
      let valB: any = b[sortField as keyof InventoryPosition];

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
  }, [positions, currentLocationId, searchText, categoryFilter, filterBelowROP, filterPerishables, sortField, sortOrder]);

  // --- Handlers ---
  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortOrder(prev => prev === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('desc'); // Default to descending for new metrics usually
    }
  };

  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked) {
      setSelectedIds(new Set(filteredPositions.map(p => p.id)));
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
       const pos = positions.find(p => p.id === firstId);
       if (pos) {
         navigate(`/ordering?locId=${pos.locationId}&itemId=${pos.skuId}`);
       }
    } else {
       alert(`Transfer proposed for ${count} items. Approval request sent to regional manager.`);
       setSelectedIds(new Set());
    }
  };

  // --- Derived Lists ---
  const categories = Array.from(new Set(items.map(i => i.category)));

  return (
    <div className="space-y-6 animate-in fade-in duration-500 pb-20">
      
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
          <h2 className="text-2xl font-bold text-stone-900">Inventory Inspection</h2>
          <p className="text-stone-500">
             {currentLocationId === 'all' 
                ? 'Managing global stock positions' 
                : `Managing stock for ${locations.find(l => l.id === currentLocationId)?.name}`}
          </p>
        </div>
        <div className="flex gap-2">
           {/* View Toggle */}
           <div className="bg-stone-200 p-1 rounded-lg flex items-center">
               <button 
                onClick={() => setViewMode('list')}
                className={`p-1.5 rounded-md transition-all ${viewMode === 'list' ? 'bg-white shadow text-stone-900' : 'text-stone-500 hover:text-stone-700'}`}
               >
                   <List size={16} />
               </button>
               <button 
                onClick={() => setViewMode('grid')}
                className={`p-1.5 rounded-md transition-all ${viewMode === 'grid' ? 'bg-white shadow text-stone-900' : 'text-stone-500 hover:text-stone-700'}`}
               >
                   <LayoutGrid size={16} />
               </button>
           </div>
        </div>
      </div>

      {/* Control Bar */}
      <div className="bg-white p-4 rounded-xl border border-stone-200 shadow-sm space-y-4">
         <div className="flex flex-col xl:flex-row gap-4 justify-between">
            {/* Left: Filters */}
            <div className="flex flex-col md:flex-row gap-3 flex-1">
               <div className="relative flex-1 min-w-[240px]">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-stone-400" size={16} />
                  <input 
                     type="text" 
                     placeholder="Search SKU or Product Name..." 
                     value={searchText}
                     onChange={(e) => setSearchText(e.target.value)}
                     className="w-full pl-9 pr-4 py-2 bg-stone-50 border border-stone-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"
                  />
               </div>
               
               <select 
                  value={categoryFilter}
                  onChange={(e) => setCategoryFilter(e.target.value)}
                  className="bg-stone-50 border border-stone-200 text-stone-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:border-amber-500"
               >
                  <option value="all">All Categories</option>
                  {categories.map(c => <option key={c} value={c}>{c}</option>)}
               </select>

               <div className="flex items-center gap-2 border-l border-stone-100 pl-3">
                  <button 
                     onClick={() => setFilterBelowROP(!filterBelowROP)}
                     className={`px-3 py-1.5 rounded-lg text-xs font-medium border flex items-center gap-2 transition-colors ${
                        filterBelowROP 
                        ? 'bg-amber-50 border-amber-200 text-amber-700' 
                        : 'bg-white border-stone-200 text-stone-600 hover:bg-stone-50'
                     }`}
                  >
                     <AlertTriangle size={12} />
                     Below ROP
                  </button>
                  <button 
                     onClick={() => setFilterPerishables(!filterPerishables)}
                     className={`px-3 py-1.5 rounded-lg text-xs font-medium border flex items-center gap-2 transition-colors ${
                        filterPerishables
                        ? 'bg-blue-50 border-blue-200 text-blue-700' 
                        : 'bg-white border-stone-200 text-stone-600 hover:bg-stone-50'
                     }`}
                  >
                     <Clock size={12} />
                     Perishables
                  </button>
               </div>
            </div>

            {/* Right: Actions (if selection) */}
            {selectedIds.size > 0 && (
               <div className="flex items-center gap-2 animate-in slide-in-from-right-4 fade-in">
                  <span className="text-xs font-medium text-stone-500 mr-2">{selectedIds.size} selected</span>
                  <button 
                     onClick={() => handleBulkAction('draft')}
                     className="px-4 py-2 bg-stone-900 text-white text-xs font-bold rounded-lg hover:bg-stone-800 flex items-center gap-2"
                  >
                     <Package size={14} /> Draft Order
                  </button>
                  <button 
                     onClick={() => handleBulkAction('transfer')}
                     className="px-4 py-2 bg-white border border-stone-300 text-stone-700 text-xs font-bold rounded-lg hover:bg-stone-50 flex items-center gap-2"
                  >
                     <Truck size={14} /> Transfer
                  </button>
               </div>
            )}
         </div>
      </div>

      {/* Main Content */}
      {viewMode === 'list' ? (
         <div className="bg-white border border-stone-200 rounded-2xl shadow-sm overflow-hidden">
            <div className="overflow-x-auto min-h-[400px]">
                <table className="w-full text-left border-collapse">
                   <thead>
                      <tr className="bg-stone-50/80 border-b border-stone-200 text-xs uppercase tracking-wider text-stone-500 font-semibold">
                         <th className="p-4 w-10">
                            <input 
                               type="checkbox" 
                               onChange={handleSelectAll}
                               checked={selectedIds.size === filteredPositions.length && filteredPositions.length > 0}
                               className="rounded border-stone-300 text-amber-600 focus:ring-amber-500 cursor-pointer"
                            />
                         </th>
                         <th className="p-4 cursor-pointer hover:text-stone-700 group" onClick={() => handleSort('sku')}>
                            <div className="flex items-center gap-1">
                               Item / SKU
                               <ArrowUpDown size={12} className={`opacity-0 group-hover:opacity-100 transition-opacity ${sortField === 'sku' ? 'opacity-100 text-amber-500' : ''}`} />
                            </div>
                         </th>
                         <th className="p-4 cursor-pointer hover:text-stone-700 group text-right" onClick={() => handleSort('onHand')}>
                            <div className="flex items-center justify-end gap-1">
                               On Hand
                               <ArrowUpDown size={12} className={`opacity-0 group-hover:opacity-100 transition-opacity ${sortField === 'onHand' ? 'opacity-100 text-amber-500' : ''}`} />
                            </div>
                         </th>
                         <th className="p-4 text-right hidden sm:table-cell">On Order</th>
                         <th className="p-4 text-right cursor-pointer hover:text-stone-700 group" onClick={() => handleSort('daysCover')}>
                             <div className="flex items-center justify-end gap-1">
                               Days Cover
                               <ArrowUpDown size={12} className={`opacity-0 group-hover:opacity-100 transition-opacity ${sortField === 'daysCover' ? 'opacity-100 text-amber-500' : ''}`} />
                            </div>
                         </th>
                         <th className="p-4 text-right hidden md:table-cell">Metrics (ROP / Safe)</th>
                         <th className="p-4 cursor-pointer hover:text-stone-700 group" onClick={() => handleSort('status')}>
                             <div className="flex items-center gap-1">
                               Status & Expiry
                               <ArrowUpDown size={12} className={`opacity-0 group-hover:opacity-100 transition-opacity ${sortField === 'status' ? 'opacity-100 text-amber-500' : ''}`} />
                            </div>
                         </th>
                         <th className="p-4 w-10"></th>
                      </tr>
                   </thead>
                   <tbody className="divide-y divide-stone-100">
                      {filteredPositions.length === 0 ? (
                         <tr>
                            <td colSpan={8} className="p-12 text-center text-stone-400">
                               <div className="flex flex-col items-center gap-3">
                                  <Search size={32} className="opacity-20"/>
                                  <p>No inventory found matching your filters.</p>
                                  <button 
                                     onClick={() => {setSearchText(''); setCategoryFilter('all'); setFilterBelowROP(false); setFilterPerishables(false);}}
                                     className="mt-2 px-6 py-2 bg-stone-900 text-white rounded-lg text-sm font-bold hover:bg-stone-800 transition-colors shadow-sm"
                                  >
                                     Clear Filters & Search
                                  </button>
                               </div>
                            </td>
                         </tr>
                      ) : (
                         filteredPositions.map((pos) => (
                            <tr key={pos.id} className={`group transition-colors ${selectedIds.has(pos.id) ? 'bg-amber-50/40' : 'hover:bg-stone-50'}`}>
                               <td className="p-4 align-top">
                                  <input 
                                     type="checkbox" 
                                     checked={selectedIds.has(pos.id)}
                                     onChange={() => handleSelectRow(pos.id)}
                                     className="rounded border-stone-300 text-amber-600 focus:ring-amber-500 cursor-pointer mt-1"
                                  />
                               </td>
                               <td className="p-4 align-top">
                                  <Link to={`/inventory/${pos.locationId}/${pos.skuId}`} className="block">
                                    <div className="flex items-start gap-3 group-hover:opacity-80 transition-opacity">
                                       <div className="w-10 h-10 rounded-lg bg-stone-100 flex items-center justify-center shrink-0">
                                          <ProductIcon category={pos.item.category} className="w-8 h-8" />
                                       </div>
                                       <div>
                                          <div className="font-bold text-stone-900 text-sm group-hover:text-amber-600 transition-colors">{pos.item.name}</div>
                                          <div className="text-[10px] text-stone-500 uppercase tracking-wide flex items-center gap-1 mt-0.5">
                                             {pos.item.category} 
                                             {currentLocationId === 'all' && <span className="text-stone-300">• {pos.locationName}</span>}
                                          </div>
                                       </div>
                                    </div>
                                  </Link>
                               </td>
                               <td className="p-4 text-right align-top">
                                  <div className="font-mono font-bold text-stone-900">{pos.onHand}</div>
                                  <div className="text-xs text-stone-400">{pos.item.unit}</div>
                               </td>
                               <td className="p-4 text-right hidden sm:table-cell align-top">
                                  {pos.onOrder > 0 ? (
                                     <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                                        <Truck size={10} /> +{pos.onOrder}
                                     </span>
                                  ) : (
                                     <span className="text-stone-300">-</span>
                                  )}
                               </td>
                               <td className="p-4 text-right align-top">
                                  <div className={`font-mono font-medium ${pos.daysCover < 3 ? 'text-rose-600' : 'text-stone-700'}`}>
                                     {pos.daysCover}d
                                  </div>
                                  <div className="text-[10px] text-stone-400">@{pos.dailyUsage}/day</div>
                               </td>
                               <td className="p-4 text-right hidden md:table-cell align-top">
                                   <div className="text-xs text-stone-600">
                                     <span className="text-stone-400">ROP:</span> <span className="font-mono font-medium">{Math.ceil(pos.reorderPoint)}</span>
                                   </div>
                                   <div className="text-xs text-stone-600">
                                     <span className="text-stone-400">Safe:</span> <span className="font-mono font-medium">{pos.safetyStock}</span>
                                   </div>
                               </td>
                               <td className="p-4 align-top">
                                  <div className="flex flex-col gap-2 items-start">
                                     {/* Status Badge */}
                                     <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border ${
                                        pos.status.badgeColor === 'emerald' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' :
                                        pos.status.badgeColor === 'amber' ? 'bg-amber-100 text-amber-800 border-amber-200' :
                                        pos.status.badgeColor === 'rose' ? 'bg-rose-100 text-rose-800 border-rose-200' :
                                        'bg-blue-100 text-blue-800 border-blue-200'
                                     }`}>
                                        {pos.status.badgeColor === 'emerald' ? <CheckCircle2 size={12}/> : 
                                         pos.status.badgeColor === 'blue' ? <TrendingDown size={12}/> :
                                         <AlertTriangle size={12}/>}
                                        {pos.status.explanation}
                                     </span>

                                     {/* Enhanced FEFO Visualization */}
                                     {pos.item.isPerishable && pos.expiryLots.length > 0 && (
                                        <div className="w-full max-w-[160px]">
                                           <div className="flex w-full h-3 rounded-md overflow-hidden bg-stone-100 ring-1 ring-stone-200 shadow-sm">
                                              {pos.expiryLots.sort((a,b) => a.daysUntilExpiry - b.daysUntilExpiry).map((lot, idx) => (
                                                 <div 
                                                    key={idx}
                                                    className={`h-full transition-all hover:opacity-80 relative group/lot cursor-help border-r border-white/20 last:border-0 ${
                                                       lot.riskLevel === 'critical' ? 'bg-rose-500' : 
                                                       lot.riskLevel === 'warning' ? 'bg-amber-400' : 'bg-emerald-400'
                                                    }`}
                                                    style={{ width: `${(lot.quantity / pos.onHand) * 100}%` }}
                                                 >
                                                    {/* Tooltip */}
                                                    <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover/lot:block bg-stone-900 text-white text-[10px] px-2 py-1.5 rounded-lg whitespace-nowrap z-20 shadow-xl border border-stone-700">
                                                       <span className="font-bold">{lot.quantity} units</span> expire in {lot.daysUntilExpiry}d
                                                    </div>
                                                 </div>
                                              ))}
                                           </div>
                                        </div>
                                     )}
                                  </div>
                               </td>
                               <td className="p-4 text-center relative align-top">
                                  <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity mt-1">
                                     <Link 
                                        to={`/inventory/${pos.locationId}/${pos.skuId}`}
                                        title="View Analysis"
                                        className="p-1.5 text-stone-400 hover:text-stone-800 hover:bg-stone-100 rounded-lg transition-colors"
                                     >
                                        <Eye size={16} />
                                     </Link>
                                     <button 
                                        title="Restock"
                                        onClick={() => navigate(`/ordering?locId=${pos.locationId}&itemId=${pos.skuId}`)}
                                        className="p-1.5 text-stone-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
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
               <div className="bg-stone-50 border-t border-stone-200 p-3 text-center">
                  <span className="text-xs text-stone-400">Showing {filteredPositions.length} positions sorted by {sortField}</span>
               </div>
            )}
         </div>
      ) : (
         // GRID VIEW (PANTRY)
         <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
            {filteredPositions.map(pos => (
                <ShelfItem key={pos.id} pos={pos} />
            ))}
            {filteredPositions.length === 0 && (
                <div className="col-span-full py-12 text-center text-stone-400">No items found.</div>
            )}
         </div>
      )}
    </div>
  );
};

export default Inventory;