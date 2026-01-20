

import { 
  ShoppingBag, Truck, CheckCircle2, AlertCircle, Search, Filter, Plus, Trash2, 
  ChevronDown, ChevronUp, Package, DollarSign, AlertTriangle, Send, Info, Clock, 
  TrendingUp, Zap, Lightbulb
} from 'lucide-react';
import React, { useState, useMemo, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';

import { useApp } from '../App';
import { ITEMS, SUPPLIERS, SUPPLIER_ITEMS } from '../constants';
import { suggestConsolidationAdds } from '../services/cockpitService';
import { calculateInventoryPositions } from '../services/inventoryService';
import { calcMaxPerishableOrder } from '../services/orderCalculations';
import { evaluateBulkTierBreakeven } from '../services/skuMath';
import { chooseBestVendorGivenUrgency } from '../services/vendorService';
import { SupplierItem, Supplier, Item, DraftOrder, DraftLineItem, OrderWarning, ConsolidationSuggestion } from '../types';

import ProductIcon from './ProductIcon';

const Ordering: React.FC = () => {
  const { locations, currentLocationId, setCurrentLocationId, drafts, addToDraft, removeFromDraft, submitDraft, inventory, items, gameState } = useApp();
  const [searchParams] = useSearchParams();
  
  // Local State
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [expandedItemId, setExpandedItemId] = useState<string | null>(null);
  const [addQty, setAddQty] = useState<number>(0);
  const [targetLocationId, setTargetLocationId] = useState(currentLocationId === 'all' ? locations[0].id : currentLocationId);

  // Animation State for Stamp
  const [showStamp, setShowStamp] = useState(false);

  // Derive Inventory Stats for context in catalog
  const inventoryPositions = useMemo(() => 
    calculateInventoryPositions(inventory, items, locations), 
  [inventory, items, locations]);

  // Handle URL params
  useEffect(() => {
    const locParam = searchParams.get('locId');
    const itemParam = searchParams.get('itemId');
    if (locParam && locParam !== 'all') {
      setTargetLocationId(locParam);
      // Ensure global header matches
      if (currentLocationId !== locParam) setCurrentLocationId(locParam);
    }
    if (itemParam) {
      setExpandedItemId(itemParam);
    }
  }, [searchParams]);

  // Sync target location when global location changes
  useEffect(() => {
    if (currentLocationId !== 'all') {
      setTargetLocationId(currentLocationId);
    }
  }, [currentLocationId]);

  // --- Logic Helpers ---

  const getFilteredItems = () => {
    return items.filter(item => {
      const matchesSearch = item.name.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesCategory = selectedCategory === 'all' || item.category === selectedCategory;
      return matchesSearch && matchesCategory;
    });
  };

  const getVendorOptions = (itemId: string) => {
    return SUPPLIER_ITEMS.filter(si => si.itemId === itemId).map(si => {
      const supplier = SUPPLIERS.find(s => s.id === si.supplierId);
      return { ...si, supplier };
    }).sort((a, b) => a.pricePerUnit - b.pricePerUnit); // Cheapest first
  };

  const getRecommendedVendor = (itemId: string, urgencyLevel: 'critical' | 'high' | 'standard' | 'low', neededQty: number) => {
    const result = chooseBestVendorGivenUrgency(itemId, neededQty, urgencyLevel);
    return result.selected;
  };

  const handleAdd = (si: SupplierItem, supplier: Supplier) => {
    if (addQty < si.minOrderQty) return;
    addToDraft(supplier.id, targetLocationId, items.find(i => i.id === si.itemId)!, addQty, si.pricePerUnit);
    setAddQty(0);
    setExpandedItemId(null);
  };

  const handleSubmitWithFX = (vendorId: string) => {
      submitDraft(vendorId);
      setShowStamp(true);
      setTimeout(() => setShowStamp(false), 2000);
  };

  // --- Draft Analysis Logic ---

  const analyzeDraft = (draft: DraftOrder): { 
    subtotal: number; 
    shippingCost: number; 
    total: number; 
    warnings: OrderWarning[];
    progressToFreeShipping: number;
    consolidationSuggestions: ConsolidationSuggestion[];
  } => {
    const supplier = SUPPLIERS.find(s => s.id === draft.vendorId);
    if (!supplier) return { subtotal: 0, shippingCost: 0, total: 0, warnings: [], progressToFreeShipping: 0, consolidationSuggestions: [] };

    let subtotal = 0;
    const warnings: OrderWarning[] = [];

    draft.items.forEach(line => {
      subtotal += line.qty * line.unitPrice;
      const item = items.find(i => i.id === line.itemId);
      const sItem = SUPPLIER_ITEMS.find(si => si.supplierId === draft.vendorId && si.itemId === line.itemId);
      const pos = inventoryPositions.find(p => p.skuId === item?.id && p.locationId === line.locationId);
      
      // Warning 1: Smart Perishability Limit
      if (item?.isPerishable && pos) {
         const limit = calcMaxPerishableOrder(item, pos.dailyUsage, pos.onHand, sItem?.deliveryDays || 1);
         if (line.qty > limit.maxOrderQty) {
             warnings.push({
                 kind: 'EXPIRY',
                 message: `High Waste Risk: ${limit.rationale}`,
                 impact: { waste: (line.qty - limit.maxOrderQty) * line.unitPrice }
             });
         }
      }

      // Warning 2: Min Order per Item
      if (sItem && line.qty < sItem.minOrderQty) {
          warnings.push({
              kind: 'MIN_ORDER',
              message: `${item?.name}: Quantity ${line.qty} is below MOQ of ${sItem.minOrderQty}.`
          });
      }

      // Warning 3: Smart Tier Pricing Analysis
      if (sItem?.priceTiers && item) {
         const sortedTiers = [...sItem.priceTiers].sort((a,b) => a.minQty - b.minQty);
         const currentTier = sortedTiers.find(t => line.qty >= t.minQty) || { minQty: 0, unitPrice: sItem.pricePerUnit };
         const nextTier = sortedTiers.find(t => t.minQty > line.qty);
         
         if (nextTier) {
             const analysis = evaluateBulkTierBreakeven(currentTier, nextTier, item, line.qty);
             if (analysis.recommendation === 'UPGRADE_TIER') {
                  const addQty = nextTier.minQty - line.qty;
                  warnings.push({
                      kind: 'TIER_BAD_DEAL',
                      message: `Buy ${addQty} more to save $${analysis.netBenefit.toFixed(2)} overall (Breakeven Analysis).`,
                      impact: { cost: analysis.savingsAtTargetTier }
                  });
             }
         }
      }

      // Warning 4: Stockout Risk during Lead Time
      if (pos && sItem) {
          const daysToArrival = sItem.deliveryDays;
          // If stock covers less than arrival time + 1 day buffer
          if (pos.daysCover < (daysToArrival + 1)) {
              warnings.push({
                  kind: 'STOCKOUT_RISK',
                  message: `${item?.name} stock (${pos.onHand}) may deplete before ${daysToArrival}-day delivery arrives.`,
              });
          }
      }
    });

    const meetsFreeShipping = subtotal >= supplier.freeShippingThreshold;
    const shippingCost = meetsFreeShipping ? 0 : supplier.flatShippingRate;
    const progressToFreeShipping = Math.min(100, (subtotal / supplier.freeShippingThreshold) * 100);

    // Warning 5: Consolidation Suggestions (New Logic)
    let consolidationSuggestions: ConsolidationSuggestion[] = [];
    if (!meetsFreeShipping && progressToFreeShipping > 50) {
       const result = suggestConsolidationAdds(draft, items, inventoryPositions);
       consolidationSuggestions = result.suggestions;
       
       if (result.suggestions.length === 0) {
            // Fallback generic message if no smart suggestions found
            warnings.push({
                kind: 'CONSOLIDATION_OPP',
                message: `Add $${(supplier.freeShippingThreshold - subtotal).toFixed(2)} more to save $${shippingCost} shipping.`,
                impact: { cost: shippingCost }
            });
       }
    }

    // Sort warnings by severity/importance
    const priority: Record<string, number> = {
        'STOCKOUT_RISK': 0,
        'EXPIRY': 1,
        'MIN_ORDER': 2,
        'TIER_BAD_DEAL': 3,
        'CONSOLIDATION_OPP': 4
    };
    warnings.sort((a,b) => priority[a.kind] - priority[b.kind]);

    return {
      subtotal,
      shippingCost,
      total: subtotal + shippingCost,
      warnings,
      progressToFreeShipping,
      consolidationSuggestions
    };
  };

  const categories = Array.from(new Set(items.map(i => i.category)));

  return (
    <div className="flex flex-col xl:flex-row h-[calc(100vh-140px)] gap-6 animate-in fade-in duration-500 relative">
      
      {/* --- STAMP ANIMATION OVERLAY --- */}
      {showStamp && (
          <div className="absolute inset-0 z-50 flex items-center justify-center pointer-events-none">
              <div className="border-8 border-emerald-600 text-emerald-600 font-black text-8xl p-8 uppercase rotate-[-12deg] opacity-0 animate-stamp shadow-2xl bg-white/10 backdrop-blur-sm rounded-xl">
                  APPROVED
              </div>
          </div>
      )}
      <style>{`
        @keyframes stamp {
            0% { opacity: 0; transform: scale(2) rotate(-12deg); }
            10% { opacity: 1; transform: scale(1) rotate(-12deg); }
            80% { opacity: 1; transform: scale(1) rotate(-12deg); }
            100% { opacity: 0; transform: scale(1.5) rotate(-12deg); }
        }
        .animate-stamp { animation: stamp 1.5s ease-out forwards; }
      `}</style>

      {/* --- LEFT: Catalog & Sourcing --- */}
      <div className="xl:w-7/12 flex flex-col gap-4 min-w-0">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-xl border border-stone-200 shadow-sm flex-shrink-0">
            <div>
               <h2 className="text-xl font-bold text-stone-900">Sourcing Catalog</h2>
               <p className="text-sm text-stone-500">Available Budget: <span className="text-emerald-600 font-bold">${gameState.cash.toLocaleString()}</span></p>
            </div>
            
            {/* Target Location Selector */}
            <div className="flex items-center gap-2 bg-stone-50 p-2 rounded-lg border border-stone-200">
               <span className="text-xs font-bold text-stone-400 uppercase tracking-wide">Ordering For:</span>
               <select 
                  value={targetLocationId}
                  onChange={(e) => setTargetLocationId(e.target.value)}
                  className="bg-transparent text-sm font-bold text-stone-900 outline-none cursor-pointer hover:text-amber-600"
               >
                  {locations.map(l => (
                    <option key={l.id} value={l.id}>{l.name}</option>
                  ))}
               </select>
            </div>
        </div>

        {/* Filters */}
        <div className="flex gap-2 flex-shrink-0">
           <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-stone-400" size={16} />
              <input 
                 type="text" 
                 placeholder="Search items..." 
                 value={searchTerm}
                 onChange={(e) => setSearchTerm(e.target.value)}
                 className="w-full pl-9 pr-4 py-2 bg-white border border-stone-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
              />
           </div>
           <select 
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="bg-white border border-stone-200 rounded-lg text-sm px-3 py-2 outline-none"
           >
              <option value="all">All Categories</option>
              {categories.map(c => <option key={c} value={c}>{c}</option>)}
           </select>
        </div>

        {/* Item List */}
        <div className="flex-1 overflow-y-auto space-y-3 pr-2 scrollbar-thin">
           {getFilteredItems().map(item => {
              const vendorOptions = getVendorOptions(item.id);
              const isExpanded = expandedItemId === item.id;
              
              // Get current stock context
              const pos = inventoryPositions.find(p => p.skuId === item.id && p.locationId === targetLocationId);
              const needsRestock = pos ? pos.onHand <= pos.reorderPoint : false;

              return (
                 <div key={item.id} className={`bg-white border rounded-xl transition-all ${isExpanded ? 'border-amber-400 shadow-md ring-1 ring-amber-400/20' : 'border-stone-200 hover:border-amber-200'}`}>
                    <div 
                      className="p-4 flex items-center gap-4 cursor-pointer"
                      onClick={() => {
                        setExpandedItemId(isExpanded ? null : item.id);
                        setAddQty(0); // Reset qty on toggle
                      }}
                    >
                       <div className="w-12 h-12 rounded-lg bg-stone-100 flex items-center justify-center shrink-0">
                         <ProductIcon category={item.category} className="w-9 h-9" />
                       </div>
                       <div className="flex-1">
                          <div className="flex items-center gap-2">
                             <h3 className="font-bold text-stone-900">{item.name}</h3>
                             {needsRestock && (
                                <span className="bg-rose-100 text-rose-700 text-[10px] font-bold px-1.5 py-0.5 rounded flex items-center gap-1">
                                   <AlertTriangle size={10} /> Low Stock
                                </span>
                             )}
                          </div>
                          <div className="flex gap-4 mt-1 text-xs text-stone-500">
                             <span>{item.category}</span>
                             <span>•</span>
                             <span>{item.unit}</span>
                             {pos && (
                                <>
                                   <span>•</span>
                                   <span className={`${needsRestock ? 'text-rose-600 font-bold' : 'text-stone-500'}`}>
                                      Stock: {pos.onHand}
                                   </span>
                                </>
                             )}
                          </div>
                       </div>
                       <div className="text-stone-400">
                          {isExpanded ? <ChevronUp size={20} /> : <ChevronDown size={20} />}
                       </div>
                    </div>

                     {/* Sourcing Panel */}
                     {isExpanded && (
                        <div className="border-t border-stone-100 bg-stone-50/50 p-4 animate-in slide-in-from-top-2">
                           <div className="flex items-center justify-between mb-3">
                              <div className="text-xs font-bold text-stone-400 uppercase tracking-wider">Available Suppliers</div>
                              <div className="flex items-center gap-2">
                                <span className="text-[10px] text-stone-400">Urgency:</span>
                                <select 
                                  className="text-[10px] bg-white border border-stone-200 rounded px-1 py-0.5 outline-none"
                                  onChange={(e) => {}}
                                >
                                  <option value="standard">Standard</option>
                                  <option value="high">High</option>
                                  <option value="critical">Critical</option>
                                  <option value="low">Low</option>
                                </select>
                              </div>
                           </div>
                           <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                              {vendorOptions.map((opt) => (
                                 <div key={opt.supplierId} className={`bg-white border rounded-lg p-3 transition-all ${
                                    opt.supplierId === getRecommendedVendor(item.id, 'standard', addQty || opt.minOrderQty)?.vendor?.id 
                                      ? 'border-emerald-400 ring-1 ring-emerald-400/20 shadow-md' 
                                      : 'border-stone-200 hover:shadow-sm'
                                 }`}>
                                    <div className="flex justify-between items-start mb-2">
                                       <div className="flex items-center gap-2">
                                          <div className="font-bold text-stone-800 text-sm">{opt.supplier?.name}</div>
                                          {opt.supplierId === getRecommendedVendor(item.id, 'standard', addQty || opt.minOrderQty)?.vendor?.id && (
                                             <span className="text-[9px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold flex items-center gap-0.5">
                                                <Zap size={8} className="fill-emerald-500" /> BEST
                                             </span>
                                          )}
                                       </div>
                                       <div className="text-xs bg-stone-100 px-1.5 py-0.5 rounded text-stone-600 flex items-center gap-1">
                                          <Truck size={10} /> {opt.deliveryDays}d
                                       </div>
                                    </div>
                                   
                                   <div className="flex justify-between items-end">
                                      <div>
                                         <div className="text-lg font-bold text-stone-900">${opt.pricePerUnit}</div>
                                         <div className="text-[10px] text-stone-400">Min Order: {opt.minOrderQty} {item.unit}</div>
                                      </div>
                                      
                                      <div className="flex items-center gap-2">
                                          <input 
                                             id="text-field-container"
                                             type="number" 
                                             min={opt.minOrderQty}
                                             placeholder={opt.minOrderQty.toString()}
                                             className="w-16 px-2 py-1.5 text-sm border border-stone-200 rounded-md outline-none focus:border-amber-500 bg-stone-50 text-stone-900"
                                             onChange={(e) => setAddQty(parseInt(e.target.value))}
                                             onClick={(e) => e.stopPropagation()}
                                          />
                                          <button 
                                             onClick={(e) => { e.stopPropagation(); handleAdd(opt, opt.supplier!); }}
                                             disabled={addQty < opt.minOrderQty}
                                             className={`p-1.5 rounded-md text-white transition-colors ${addQty >= opt.minOrderQty ? 'bg-amber-600 hover:bg-amber-700' : 'bg-stone-300 cursor-not-allowed'}`}
                                          >
                                             <Plus size={18} />
                                          </button>
                                      </div>
                                   </div>
                                </div>
                             ))}
                          </div>
                       </div>
                    )}
                 </div>
              );
           })}
           {getFilteredItems().length === 0 && (
             <div className="text-center py-12 text-stone-400">No items match your search.</div>
           )}
        </div>
      </div>

      {/* --- RIGHT: Draft Workspaces --- */}
      <div className="xl:w-5/12 flex flex-col gap-4 min-w-0 border-l border-stone-200 pl-6 xl:pl-0 xl:border-l-0">
         <div className="bg-stone-900 text-white p-4 rounded-xl shadow-lg flex-shrink-0">
            <h2 className="text-xl font-bold flex items-center gap-2">
               <ShoppingBag className="text-amber-500" /> Draft Orders
            </h2>
            <p className="text-sm text-stone-400">
               {drafts.length} Active Vendor Cart{drafts.length !== 1 ? 's' : ''}
            </p>
         </div>
         
         <div className="flex-1 overflow-y-auto space-y-4 pr-2 scrollbar-thin">
            {drafts.length === 0 ? (
               <div className="h-64 flex flex-col items-center justify-center text-stone-400 border-2 border-dashed border-stone-200 rounded-xl">
                  <Package size={48} className="mb-4 opacity-20" />
                  <p>Your draft carts are empty.</p>
                  <p className="text-sm mt-2">Select items from the catalog to build an order.</p>
               </div>
            ) : (
               drafts.map(draft => {
                  const supplier = SUPPLIERS.find(s => s.id === draft.vendorId);
                  const { subtotal, shippingCost, total, warnings, progressToFreeShipping, consolidationSuggestions } = analyzeDraft(draft);
                  const canAfford = total <= gameState.cash;

                  return (
                     <div key={draft.vendorId} className="bg-white border border-stone-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
                        
                        {/* Vendor Header */}
                        <div className="p-4 bg-stone-50 border-b border-stone-200 flex justify-between items-center">
                           <h3 className="font-bold text-stone-900">{supplier?.name}</h3>
                           <span className={`text-[10px] font-bold px-2 py-0.5 rounded ${supplier?.deliverySpeed === 'Fast' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-600'}`}>
                              {supplier?.deliverySpeed} Delivery
                           </span>
                        </div>

                        {/* Line Items */}
                        <div className="p-4 space-y-3 flex-1">
                           {draft.items.map(line => {
                              const item = items.find(i => i.id === line.itemId);
                              const locName = locations.find(l => l.id === line.locationId)?.name;
                              return (
                                 <div key={line.id} className="flex justify-between items-start group">
                                    <div className="flex gap-2">
                                       <div className="w-1 h-full bg-stone-200 rounded-full"></div>
                                       <div>
                                          <div className="font-bold text-sm text-stone-900">{item?.name}</div>
                                          <div className="text-xs text-stone-500">{line.qty} {item?.unit} @ ${line.unitPrice}</div>
                                          <div className="text-[10px] text-stone-400 mt-0.5 flex items-center gap-1">
                                             <Package size={8} /> {locName}
                                          </div>
                                       </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                       <span className="font-medium text-sm text-stone-700">${(line.qty * line.unitPrice).toFixed(2)}</span>
                                       <button 
                                          onClick={() => removeFromDraft(draft.vendorId, line.id)}
                                          className="text-stone-300 hover:text-rose-500 transition-colors opacity-0 group-hover:opacity-100"
                                       >
                                          <Trash2 size={14} />
                                       </button>
                                    </div>
                                 </div>
                              );
                           })}
                        </div>

                        {/* Consolidation Suggestions */}
                        {consolidationSuggestions.length > 0 && (
                            <div className="px-4 pb-2">
                                <div className="bg-amber-50 rounded-lg p-3 border border-amber-100">
                                    <div className="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase mb-2">
                                        <Lightbulb size={12} className="fill-amber-500 text-amber-500"/> Smart Additions
                                    </div>
                                    <div className="space-y-2">
                                        {consolidationSuggestions.map(s => (
                                            <div key={s.itemId} className="flex justify-between items-center bg-white p-2 rounded border border-amber-100 shadow-sm">
                                                <div>
                                                    <div className="text-xs font-bold text-stone-800">{s.itemName}</div>
                                                    <div className="text-[10px] text-stone-500">{s.reason}</div>
                                                </div>
                                                <button 
                                                    onClick={() => addToDraft(draft.vendorId, targetLocationId, items.find(i=>i.id===s.itemId)!, s.suggestedQty, 0)} // Price handled by addToDraft lookup usually, or pass correctly
                                                    className="px-2 py-1 bg-amber-100 text-amber-800 text-[10px] font-bold rounded hover:bg-amber-200 transition-colors"
                                                >
                                                    + {s.suggestedQty}
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Warnings List */}
                        {warnings.length > 0 && (
                           <div className="px-4 pb-4 space-y-2">
                              {warnings.map((w, idx) => (
                                 <div key={idx} className={`p-3 rounded-lg text-xs flex gap-3 border transition-all ${
                                    w.kind === 'CONSOLIDATION_OPP' ? 'bg-amber-50 text-amber-800 border-amber-100' :
                                    w.kind === 'EXPIRY' ? 'bg-rose-50 text-rose-800 border-rose-100' :
                                    w.kind === 'STOCKOUT_RISK' ? 'bg-rose-50 text-rose-800 border-rose-100' :
                                    w.kind === 'TIER_BAD_DEAL' ? 'bg-indigo-50 text-indigo-800 border-indigo-100' :
                                    'bg-blue-50 text-blue-800 border-blue-100'
                                 }`}>
                                    <div className="flex-shrink-0 mt-0.5">
                                       {w.kind === 'CONSOLIDATION_OPP' && <DollarSign size={14} />}
                                       {w.kind === 'EXPIRY' && <AlertTriangle size={14} />}
                                       {w.kind === 'STOCKOUT_RISK' && <Clock size={14} />}
                                       {w.kind === 'TIER_BAD_DEAL' && <TrendingUp size={14} />}
                                       {w.kind === 'MIN_ORDER' && <Info size={14} />}
                                    </div>
                                    <div>
                                       <span className="font-bold block mb-0.5 opacity-90">
                                           {w.kind === 'CONSOLIDATION_OPP' ? 'Shipping Savings' : 
                                            w.kind === 'TIER_BAD_DEAL' ? 'Bulk Discount Opportunity' :
                                            w.kind === 'EXPIRY' ? 'Spoilage Risk' :
                                            w.kind === 'STOCKOUT_RISK' ? 'Stockout Risk' : 'Order Requirement'}
                                       </span>
                                       <span className="leading-snug block opacity-80">{w.message}</span>
                                    </div>
                                 </div>
                              ))}
                           </div>
                        )}

                        {/* Summary & Checkout */}
                        <div className="p-4 border-t border-stone-200 bg-stone-50/30">
                           {/* Free Shipping Progress */}
                           <div className="mb-4">
                              <div className="flex justify-between text-[10px] text-stone-500 mb-1">
                                 <span>Free Shipping Progress</span>
                                 <span>${subtotal.toFixed(0)} / ${supplier?.freeShippingThreshold}</span>
                              </div>
                              <div className="w-full h-1.5 bg-stone-100 rounded-full overflow-hidden">
                                 <div 
                                    className={`h-full rounded-full transition-all duration-500 ${progressToFreeShipping >= 100 ? 'bg-emerald-500' : 'bg-amber-500'}`} 
                                    style={{ width: `${progressToFreeShipping}%` }}
                                 ></div>
                              </div>
                           </div>

                           <div className="flex justify-between text-sm mb-1">
                              <span className="text-stone-500">Subtotal</span>
                              <span className="font-medium">${subtotal.toFixed(2)}</span>
                           </div>
                           <div className="flex justify-between text-sm mb-3">
                              <span className="text-stone-500">Shipping</span>
                              <span className="font-medium">
                                 {shippingCost === 0 ? <span className="text-emerald-600 font-bold">FREE</span> : `$${shippingCost.toFixed(2)}`}
                              </span>
                           </div>
                           
                           <button 
                              onClick={() => handleSubmitWithFX(draft.vendorId)}
                              disabled={!canAfford}
                              className={`w-full py-2.5 text-white rounded-lg font-bold text-sm shadow-lg active:scale-95 transition-all flex justify-center items-center gap-2 ${canAfford ? 'bg-stone-900 hover:bg-stone-800 shadow-stone-900/10' : 'bg-stone-400 cursor-not-allowed'}`}
                           >
                              {canAfford ? (
                                  <>
                                      <span>Authorize Payment</span>
                                      <span className="opacity-80 font-normal">| ${total.toFixed(2)}</span>
                                      <Send size={14} />
                                  </>
                              ) : (
                                  <span>Insufficient Funds</span>
                              )}
                           </button>
                        </div>
                     </div>
                  );
               })
            )}
         </div>
      </div>
    </div>
  );
};

export default Ordering;