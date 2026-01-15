

import React, { useState, useMemo, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { 
  ArrowLeft, 
  Settings, 
  Info, 
  TrendingUp, 
  TrendingDown, 
  AlertTriangle, 
  Package, 
  Truck, 
  Clock, 
  DollarSign, 
  Calculator,
  Calendar,
  Layers,
  ShieldCheck,
  X,
  ArrowRightLeft,
  Scale,
  Edit2,
  Check,
  RotateCcw,
  ChevronDown,
  ChevronUp,
  ShoppingCart,
  Loader2
} from 'lucide-react';
import { 
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, ReferenceLine, 
  BarChart, Bar, Cell, Legend
} from 'recharts';
import { useApp } from '../App';
import { SUPPLIERS, SUPPLIER_ITEMS } from '../constants';
import { getZScore, calculateSafetyStock, calculateROP, calcLandedCostPerUnit, generateMockForecast } from '../services/skuMath';
import { ReorderPolicy, CostBreakdown, Supplier, SupplierItem, LandedCostBreakdown } from '../types';
import ProductIcon from './ProductIcon';

const SkuDetail: React.FC = () => {
  const { locationId, itemId } = useParams<{ locationId: string; itemId: string }>();
  const { items, locations, inventory, placeOrder } = useApp();

  // --- Data Resolution ---
  const item = items.find(i => i.id === itemId);
  const location = locations.find(l => l.id === locationId);
  const invRecord = inventory.find(r => r.locationId === locationId && r.itemId === itemId);

  // --- Simulation State ("What-If" Model) ---
  // We initialize these with "defaults" but allow user override via sliders
  const [serviceLevel, setServiceLevel] = useState<number>(0.95);
  const [avgLeadTime, setAvgLeadTime] = useState<number>(3); // Default mocked
  const [avgDailyUsage, setAvgDailyUsage] = useState<number>(25); // Default mocked
  const [demandStdDev, setDemandStdDev] = useState<number>(5);
  const [leadTimeStdDev, setLeadTimeStdDev] = useState<number>(1);
  const [activeTab, setActiveTab] = useState<'math' | 'vendors' | 'expiry'>('math');
  
  // New State for Policy Edit Mode
  const [isEditingPolicy, setIsEditingPolicy] = useState(false);
  
  // New State for Scenario Comparison Modal
  const [comparingSupplierId, setComparingSupplierId] = useState<string | null>(null);
  
  // New State for Expanded Vendor Row
  const [expandedVendorId, setExpandedVendorId] = useState<string | null>(null);

  // New State for Quick Ordering
  const [orderQuantities, setOrderQuantities] = useState<Record<string, number>>({});
  const [orderingStatus, setOrderingStatus] = useState<Record<string, 'idle' | 'ordering' | 'success'>>({});

  // --- Derived Calculations ---
  const zScore = useMemo(() => getZScore(serviceLevel), [serviceLevel]);
  
  const safetyStock = useMemo(() => 
    calculateSafetyStock(demandStdDev, avgLeadTime, leadTimeStdDev, avgDailyUsage, zScore), 
  [demandStdDev, avgLeadTime, leadTimeStdDev, avgDailyUsage, zScore]);

  const rop = useMemo(() => 
    calculateROP(avgDailyUsage, avgLeadTime, safetyStock), 
  [avgDailyUsage, avgLeadTime, safetyStock]);

  const leadTimeDemand = avgDailyUsage * avgLeadTime;
  const currentStock = invRecord?.quantity || 0;
  
  // Calculate On Order (Mocked logic from inventoryService but local here)
  const onOrder = (item?.id.charCodeAt(0) || 0) % 2 === 0 ? Math.floor(rop * 0.8) : 0;
  const daysCover = avgDailyUsage > 0 ? (currentStock / avgDailyUsage).toFixed(1) : '∞';

  // Vendor TCO Analysis using Landed Cost logic
  const vendorAnalysis = useMemo(() => {
    if (!item) return [];
    const relevantSupplierItems = SUPPLIER_ITEMS.filter(si => si.itemId === item.id);
    
    const analysis = relevantSupplierItems.map(si => {
      const supplier = SUPPLIERS.find(s => s.id === si.supplierId);
      if (!supplier) return null;
      // Default to MOQ for comparison
      return calcLandedCostPerUnit(item, supplier, si, si.minOrderQty);
    }).filter((x): x is LandedCostBreakdown => x !== null);

    // Mark best value
    const minTotal = Math.min(...analysis.map(a => a.totalPerUnit));
    analysis.forEach(a => a.isBestValue = Math.abs(a.totalPerUnit - minTotal) < 0.01);
    
    return analysis.sort((a, b) => a.totalPerUnit - b.totalPerUnit);
  }, [item]);

  // Forecast Data
  const forecastData = useMemo(() => 
    generateMockForecast(14, avgDailyUsage, demandStdDev, 1.1), 
  [avgDailyUsage, demandStdDev]);

  if (!item || !location) {
    return <div className="p-8 text-center">Item or Location not found</div>;
  }

  const handleQuickOrder = async (v: CostBreakdown, qty: number) => {
    const supplier = SUPPLIERS.find(s => s.id === v.supplierId);
    if (!supplier || !locationId || !item) return;

    setOrderingStatus(prev => ({ ...prev, [v.supplierId]: 'ordering' }));
    
    // Simulate API delay for UX
    await new Promise(resolve => setTimeout(resolve, 800));
    
    placeOrder(locationId, item, qty, supplier);
    
    setOrderingStatus(prev => ({ ...prev, [v.supplierId]: 'success' }));
    
    // Reset after success message
    setTimeout(() => {
        setOrderingStatus(prev => ({ ...prev, [v.supplierId]: 'idle' }));
        // Optional: Reset quantity to default? keeping it might be better for repetitive tasks
    }, 2500);
  };

  // --- Helper for Comparison Rendering ---
  const renderComparisonModal = () => {
     if (!comparingSupplierId) return null;
     const supplier = SUPPLIERS.find(s => s.id === comparingSupplierId);
     const sItem = SUPPLIER_ITEMS.find(si => si.supplierId === comparingSupplierId && si.itemId === itemId);
     if (!supplier || !sItem) return null;

     // Calculate Scenario Values
     const scenarioLeadTime = sItem.deliveryDays;
     // Note: In a real app, vendor reliability might also affect leadTimeStdDev. We keep it simple here.
     const scenarioSS = calculateSafetyStock(demandStdDev, scenarioLeadTime, leadTimeStdDev, avgDailyUsage, zScore);
     const scenarioROP = calculateROP(avgDailyUsage, scenarioLeadTime, scenarioSS);
     
     // Deltas
     const ssDelta = scenarioSS - safetyStock;
     const ropDelta = scenarioROP - rop;
     const leadTimeDelta = scenarioLeadTime - avgLeadTime;
     
     // Cost Impact (Monthly Est)
     // Holding Cost Impact due to SS change
     const monthlyHoldingImpact = ssDelta * item.storageCostPerUnit;
     
     return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/60 backdrop-blur-sm animate-in fade-in duration-200">
           <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
              {/* Modal Header */}
              <div className="p-5 border-b border-stone-100 flex justify-between items-center bg-stone-50">
                 <div>
                    <h3 className="font-bold text-stone-900 flex items-center gap-2 text-lg">
                       <Scale className="text-amber-600" size={20}/> Scenario Analysis
                    </h3>
                    <p className="text-xs text-stone-500 mt-1">Comparing current policy vs. exclusive sourcing from <span className="font-bold text-stone-700">{supplier.name}</span></p>
                 </div>
                 <button onClick={() => setComparingSupplierId(null)} className="p-2 hover:bg-stone-200 rounded-full text-stone-500 transition-colors">
                    <X size={20} />
                 </button>
              </div>
              
              <div className="p-6 overflow-y-auto">
                 <div className="grid grid-cols-1 md:grid-cols-2 gap-8 relative">
                    {/* Vertical Divider (Desktop) */}
                    <div className="hidden md:block absolute left-1/2 top-0 bottom-0 w-px bg-stone-200 -translate-x-1/2"></div>

                    {/* Left: Current State */}
                    <div className="space-y-4">
                       <div className="flex items-center gap-2 mb-4">
                          <span className="bg-stone-200 text-stone-600 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Baseline</span>
                          <span className="font-bold text-stone-900">Current Policy</span>
                       </div>
                       
                       <div className="p-4 bg-stone-50 rounded-xl border border-stone-200 space-y-3">
                          <div className="flex justify-between text-sm">
                             <span className="text-stone-500">Avg Lead Time</span>
                             <span className="font-mono font-bold">{avgLeadTime} days</span>
                          </div>
                          <div className="flex justify-between text-sm">
                             <span className="text-stone-500">Reorder Point</span>
                             <span className="font-mono font-bold">{rop} units</span>
                          </div>
                          <div className="flex justify-between text-sm">
                             <span className="text-stone-500">Safety Stock</span>
                             <span className="font-mono font-bold text-amber-600">{safetyStock} units</span>
                          </div>
                       </div>
                       
                       <div className="text-xs text-stone-400 italic">
                          Based on your current manual inputs in the simulation sidebar.
                       </div>
                    </div>

                    {/* Right: Future State */}
                    <div className="space-y-4">
                       <div className="flex items-center gap-2 mb-4">
                          <span className="bg-amber-100 text-amber-700 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Scenario</span>
                          <span className="font-bold text-stone-900">With {supplier.name}</span>
                       </div>

                       <div className="p-4 bg-amber-50/50 rounded-xl border border-amber-200 space-y-3">
                          <div className="flex justify-between text-sm">
                             <span className="text-stone-600">Vendor Lead Time</span>
                             <div className="flex items-center gap-2">
                                <span className="font-mono font-bold">{scenarioLeadTime} days</span>
                                {leadTimeDelta !== 0 && (
                                   <span className={`text-[10px] px-1.5 py-0.5 rounded font-bold ${leadTimeDelta > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                      {leadTimeDelta > 0 ? '+' : ''}{leadTimeDelta}d
                                   </span>
                                )}
                             </div>
                          </div>
                          <div className="flex justify-between text-sm">
                             <span className="text-stone-600">New ROP</span>
                             <div className="flex items-center gap-2">
                                <span className="font-mono font-bold">{scenarioROP} units</span>
                                {ropDelta !== 0 && (
                                   <span className={`text-[10px] px-1.5 py-0.5 rounded font-bold ${ropDelta > 0 ? 'bg-stone-200 text-stone-700' : 'bg-stone-200 text-stone-700'}`}>
                                      {ropDelta > 0 ? '+' : ''}{ropDelta}
                                   </span>
                                )}
                             </div>
                          </div>
                          <div className="flex justify-between text-sm">
                             <span className="text-stone-600">Req. Safety Stock</span>
                             <div className="flex items-center gap-2">
                                <span className="font-mono font-bold text-amber-700">{scenarioSS} units</span>
                                {ssDelta !== 0 && (
                                   <span className={`text-[10px] px-1.5 py-0.5 rounded font-bold ${ssDelta > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                      {ssDelta > 0 ? '+' : ''}{ssDelta}
                                   </span>
                                )}
                             </div>
                          </div>
                       </div>
                    </div>
                 </div>

                 {/* Impact Summary */}
                 <div className="mt-8 bg-stone-900 text-white rounded-xl p-6">
                    <h4 className="font-bold text-sm uppercase tracking-wider text-stone-400 mb-4">Projected Business Impact</h4>
                    <div className="grid grid-cols-2 gap-6">
                       <div>
                          <p className="text-xs text-stone-400 mb-1">Holding Cost Change</p>
                          <div className="flex items-baseline gap-2">
                             <span className={`text-2xl font-bold ${monthlyHoldingImpact > 0 ? 'text-rose-400' : 'text-emerald-400'}`}>
                                {monthlyHoldingImpact > 0 ? '+' : ''}${monthlyHoldingImpact.toFixed(2)}
                             </span>
                             <span className="text-stone-500 text-xs">/ month</span>
                          </div>
                          <p className="text-[10px] text-stone-500 mt-1">
                             {monthlyHoldingImpact > 0 
                                ? `Slower delivery requires keeping ${ssDelta} more units of safety stock to maintain ${(serviceLevel*100).toFixed(1)}% service level.`
                                : `Faster delivery allows reducing safety stock by ${Math.abs(ssDelta)} units while maintaining service level.`
                             }
                          </p>
                       </div>
                       <div>
                           <p className="text-xs text-stone-400 mb-1">Unit Price Impact</p>
                           <div className="flex items-baseline gap-2">
                              <span className="text-2xl font-bold text-white">${sItem.pricePerUnit.toFixed(2)}</span>
                              <span className="text-stone-500 text-xs">/ unit</span>
                           </div>
                           <p className="text-[10px] text-stone-500 mt-1">
                              Vendor Base Price. Does not include bulk volume discounts or shipping fees.
                           </p>
                       </div>
                    </div>
                 </div>
              </div>
              
              <div className="p-4 bg-stone-50 border-t border-stone-100 flex justify-end gap-3">
                 <button 
                    onClick={() => setComparingSupplierId(null)}
                    className="px-4 py-2 bg-white border border-stone-200 text-stone-700 font-bold text-sm rounded-lg hover:bg-stone-50 transition-colors"
                 >
                    Close Analysis
                 </button>
                 <Link 
                    to={`/ordering?locId=${locationId}&itemId=${itemId}`}
                    className="px-4 py-2 bg-amber-600 text-white font-bold text-sm rounded-lg hover:bg-amber-700 shadow-md shadow-amber-600/20 transition-all"
                 >
                    Proceed with {supplier.name}
                 </Link>
              </div>
           </div>
        </div>
     )
  };

  return (
    <div className="space-y-6 pb-20 animate-in fade-in slide-in-from-bottom-4 duration-500 relative">
      
      {/* --- Breadcrumb & Header --- */}
      <div className="flex flex-col gap-4">
        <div className="flex items-center gap-2 text-sm text-stone-500">
          <Link to="/inventory" className="hover:text-amber-600 transition-colors flex items-center gap-1">
            <ArrowLeft size={14} /> Back to Inventory
          </Link>
          <span>/</span>
          <span>{location.name}</span>
          <span>/</span>
          <span className="text-stone-900 font-semibold">{item.name}</span>
        </div>

        <div className="flex justify-between items-start">
           <div className="flex items-center gap-4">
              <div className="w-16 h-16 rounded-xl bg-stone-100 flex items-center justify-center border border-stone-200 shadow-sm shrink-0">
                <ProductIcon category={item.category} className="w-12 h-12" />
              </div>
              <div>
                <h1 className="text-3xl font-bold text-stone-900">{item.name}</h1>
                <div className="flex items-center gap-2 mt-1">
                   <span className="bg-stone-100 text-stone-600 text-xs px-2 py-0.5 rounded-md font-medium border border-stone-200">{item.category}</span>
                   <span className="text-stone-400 text-xs">•</span>
                   <span className="text-stone-500 text-xs">{item.unit}</span>
                   <span className="text-stone-400 text-xs">•</span>
                   <span className="text-stone-500 text-xs flex items-center gap-1">
                     <DollarSign size={10} /> {item.storageCostPerUnit}/unit storage
                   </span>
                </div>
              </div>
           </div>
           <div className="text-right">
              <div className="text-sm text-stone-500">Managing Policy For</div>
              <div className="font-bold text-stone-900 flex items-center gap-2 justify-end">
                <Package size={16} className="text-amber-600"/> {location.name}
              </div>
           </div>
        </div>
      </div>

      {/* --- Current Position KPIs --- */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white p-4 rounded-xl border border-stone-200 shadow-sm">
           <div className="text-stone-500 text-xs uppercase font-bold tracking-wider mb-1">On Hand</div>
           <div className="flex items-baseline gap-2">
             <span className="text-3xl font-bold text-stone-900">{currentStock}</span>
             <span className="text-stone-400 text-sm">{item.unit}</span>
           </div>
           <div className="w-full bg-stone-100 h-1.5 rounded-full mt-3 overflow-hidden">
              <div className="bg-emerald-500 h-full rounded-full" style={{ width: `${Math.min(100, (currentStock / item.bulkThreshold) * 100)}%` }}></div>
           </div>
           <div className="text-[10px] text-stone-400 mt-1 text-right">{Math.round((currentStock/item.bulkThreshold)*100)}% of Max Capacity</div>
        </div>
        
        <div className="bg-white p-4 rounded-xl border border-stone-200 shadow-sm">
           <div className="text-stone-500 text-xs uppercase font-bold tracking-wider mb-1">On Order</div>
           <div className="flex items-baseline gap-2">
             <span className="text-3xl font-bold text-blue-600">{onOrder}</span>
             <span className="text-blue-400 text-sm">incoming</span>
           </div>
           <div className="mt-3 text-xs text-stone-500 flex items-center gap-1">
             <Truck size={12} /> Expected in ~{Math.ceil(avgLeadTime)} days
           </div>
        </div>

        <div className="bg-white p-4 rounded-xl border border-stone-200 shadow-sm">
           <div className="text-stone-500 text-xs uppercase font-bold tracking-wider mb-1">Days Cover</div>
           <div className="flex items-baseline gap-2">
             <span className={`text-3xl font-bold ${Number(daysCover) < 3 ? 'text-rose-600' : 'text-stone-900'}`}>{daysCover}</span>
             <span className="text-stone-400 text-sm">days</span>
           </div>
           <div className="mt-3 text-xs text-stone-500 flex items-center gap-1">
             <TrendingUp size={12} /> Based on {avgDailyUsage} avg/day
           </div>
        </div>

        <div className="bg-stone-900 p-4 rounded-xl border border-stone-800 shadow-sm text-white relative overflow-hidden">
           <div className="absolute top-0 right-0 p-4 opacity-10"><Calculator size={48} /></div>
           <div className="text-stone-400 text-xs uppercase font-bold tracking-wider mb-1">Reorder Point</div>
           <div className="flex items-baseline gap-2 relative z-10">
             <span className="text-3xl font-bold text-amber-500">{rop}</span>
             <span className="text-stone-500 text-sm">trigger</span>
           </div>
           <div className="mt-3 text-xs text-stone-400 flex items-center gap-1 relative z-10">
             Status: {currentStock <= rop ? <span className="text-rose-400 font-bold flex items-center gap-1"><AlertTriangle size={10}/> ORDER NOW</span> : <span className="text-emerald-400">OK</span>}
           </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* --- Left Column: The Truth Engine (Math & Sliders) --- */}
        <div className="lg:col-span-1 space-y-6">
          
          {/* Formula Card */}
          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
             <div className="p-4 bg-stone-50 border-b border-stone-200 flex justify-between items-center">
                <h3 className="font-bold text-stone-900 flex items-center gap-2">
                   <Layers size={18} className="text-amber-600"/> 
                   Reorder Policy
                </h3>
                {!isEditingPolicy && (
                   <button 
                      onClick={() => setIsEditingPolicy(true)}
                      className="text-xs flex items-center gap-1.5 px-2.5 py-1.5 bg-white border border-stone-200 rounded-md shadow-sm hover:border-amber-500 hover:text-amber-600 transition-colors font-medium text-stone-600"
                   >
                      <Edit2 size={12} /> Edit Inputs
                   </button>
                )}
             </div>
             
             <div className="p-6 space-y-6">
                
                {/* Visual Formula Decomposition */}
                <div className="flex items-center justify-between text-center font-mono text-sm">
                   <div className="flex flex-col gap-1">
                      <span className="text-stone-400 text-[10px] uppercase">Lead Time Demand</span>
                      <span className="font-bold text-stone-700 bg-stone-100 px-2 py-1 rounded">{Math.round(leadTimeDemand)}</span>
                   </div>
                   <div className="text-stone-300 pb-5">+</div>
                   <div className="flex flex-col gap-1">
                      <span className="text-amber-500 text-[10px] uppercase font-bold">Safety Stock</span>
                      <span className="font-bold text-amber-600 bg-amber-50 border border-amber-100 px-2 py-1 rounded">{safetyStock}</span>
                   </div>
                   <div className="text-stone-300 pb-5">=</div>
                   <div className="flex flex-col gap-1">
                      <span className="text-stone-900 text-[10px] uppercase font-bold">ROP</span>
                      <span className="font-bold text-white bg-stone-900 px-3 py-1 rounded shadow-md">{rop}</span>
                   </div>
                </div>

                <hr className="border-stone-100" />

                {isEditingPolicy ? (
                   <div className="space-y-5 animate-in fade-in slide-in-from-top-2 duration-300">
                      <div className="flex justify-between items-center mb-2">
                         <h4 className="text-xs font-bold text-stone-900 uppercase tracking-wide">Adjust Parameters</h4>
                         <div className="flex gap-2">
                            <button 
                              onClick={() => { setServiceLevel(0.95); setAvgLeadTime(3); setAvgDailyUsage(25); }}
                              className="p-1.5 hover:bg-stone-100 rounded text-stone-400 hover:text-stone-600 transition-colors"
                              title="Reset to Defaults"
                            >
                              <RotateCcw size={14} />
                            </button>
                            <button 
                              onClick={() => setIsEditingPolicy(false)}
                              className="text-xs bg-stone-900 text-white px-3 py-1 rounded-md font-bold flex items-center gap-1 shadow-md hover:bg-stone-800 transition-colors"
                            >
                               <Check size={12} /> Apply
                            </button>
                         </div>
                      </div>

                      {/* Slider: Service Level */}
                      <div className="space-y-2">
                         <div className="flex justify-between text-xs">
                            <span className="text-stone-600 font-medium">Target Service Level</span>
                            <span className="font-bold text-stone-900">{(serviceLevel * 100).toFixed(1)}%</span>
                         </div>
                         <input 
                           type="range" min="0.80" max="0.999" step="0.001" 
                           value={serviceLevel}
                           onChange={(e) => setServiceLevel(parseFloat(e.target.value))}
                           className="w-full h-2 bg-stone-200 rounded-lg appearance-none cursor-pointer accent-amber-600"
                         />
                      </div>

                      {/* Slider: Avg Daily Usage */}
                      <div className="space-y-2">
                         <div className="flex justify-between text-xs">
                            <span className="text-stone-600 font-medium">Avg Daily Demand</span>
                            <span className="font-bold text-stone-900">{avgDailyUsage} units</span>
                         </div>
                         <input 
                           type="range" min="5" max="100" step="1" 
                           value={avgDailyUsage}
                           onChange={(e) => setAvgDailyUsage(parseInt(e.target.value))}
                           className="w-full h-2 bg-stone-200 rounded-lg appearance-none cursor-pointer accent-stone-600"
                         />
                      </div>

                      {/* Slider: Lead Time */}
                      <div className="space-y-2">
                         <div className="flex justify-between text-xs">
                            <span className="text-stone-600 font-medium">Vendor Lead Time</span>
                            <span className="font-bold text-stone-900">{avgLeadTime} days</span>
                         </div>
                         <input 
                           type="range" min="1" max="14" step="0.5" 
                           value={avgLeadTime}
                           onChange={(e) => setAvgLeadTime(parseFloat(e.target.value))}
                           className="w-full h-2 bg-stone-200 rounded-lg appearance-none cursor-pointer accent-stone-600"
                         />
                      </div>
                   </div>
                ) : (
                   <div className="grid grid-cols-3 gap-3">
                       <div className="p-3 bg-stone-50 rounded-lg border border-stone-100 text-center hover:border-stone-200 transition-colors cursor-pointer group" onClick={() => setIsEditingPolicy(true)}>
                           <div className="text-[10px] text-stone-400 uppercase font-bold mb-1 group-hover:text-amber-600">Service Level</div>
                           <div className="font-bold text-stone-800 text-lg">{(serviceLevel * 100).toFixed(1)}%</div>
                       </div>
                        <div className="p-3 bg-stone-50 rounded-lg border border-stone-100 text-center hover:border-stone-200 transition-colors cursor-pointer group" onClick={() => setIsEditingPolicy(true)}>
                           <div className="text-[10px] text-stone-400 uppercase font-bold mb-1 group-hover:text-amber-600">Avg Demand</div>
                           <div className="font-bold text-stone-800 text-lg">{avgDailyUsage} <span className="text-xs font-medium text-stone-400">/day</span></div>
                       </div>
                        <div className="p-3 bg-stone-50 rounded-lg border border-stone-100 text-center hover:border-stone-200 transition-colors cursor-pointer group" onClick={() => setIsEditingPolicy(true)}>
                           <div className="text-[10px] text-stone-400 uppercase font-bold mb-1 group-hover:text-amber-600">Lead Time</div>
                           <div className="font-bold text-stone-800 text-lg">{avgLeadTime} <span className="text-xs font-medium text-stone-400">days</span></div>
                       </div>
                   </div>
                )}

                <div className="bg-amber-50 border border-amber-100 rounded-lg p-3 mt-4">
                   <div className="flex items-start gap-2">
                      <TrendingUp size={16} className="text-amber-600 mt-0.5" />
                      <div>
                         <p className="text-xs text-amber-900 font-bold">Impact Analysis</p>
                         <p className="text-[11px] text-amber-800 mt-1 leading-snug">
                            Increasing service level to 99.9% would require holding <strong>{Math.ceil(calculateSafetyStock(demandStdDev, avgLeadTime, leadTimeStdDev, avgDailyUsage, 3.09) - safetyStock)}</strong> additional units of safety stock.
                         </p>
                      </div>
                   </div>
                </div>

             </div>
          </div>
        </div>

        {/* --- Right Column: Visualization & Context --- */}
        <div className="lg:col-span-2 space-y-6">
           
           {/* Tab Navigation */}
           <div className="flex gap-4 border-b border-stone-200">
              <button 
                onClick={() => setActiveTab('math')}
                className={`pb-3 text-sm font-medium transition-colors relative ${activeTab === 'math' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
              >
                 Demand Forecast
                 {activeTab === 'math' && <div className="absolute bottom-0 left-0 w-full h-0.5 bg-stone-900 rounded-t-full"></div>}
              </button>
              <button 
                onClick={() => setActiveTab('vendors')}
                className={`pb-3 text-sm font-medium transition-colors relative ${activeTab === 'vendors' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
              >
                 Vendor TCO Analysis
                 {activeTab === 'vendors' && <div className="absolute bottom-0 left-0 w-full h-0.5 bg-stone-900 rounded-t-full"></div>}
              </button>
              {item.isPerishable && (
                 <button 
                   onClick={() => setActiveTab('expiry')}
                   className={`pb-3 text-sm font-medium transition-colors relative ${activeTab === 'expiry' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
                 >
                    Expiry / FEFO
                    {activeTab === 'expiry' && <div className="absolute bottom-0 left-0 w-full h-0.5 bg-stone-900 rounded-t-full"></div>}
                 </button>
              )}
           </div>

           {/* Content Area */}
           <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm min-h-[400px]">
              
              {activeTab === 'math' && (
                <div className="space-y-4 h-full flex flex-col">
                   <div className="flex justify-between items-center mb-2">
                      <h4 className="font-bold text-stone-900">14-Day Demand Forecast</h4>
                      <div className="flex items-center gap-4 text-xs">
                         <span className="flex items-center gap-1 text-stone-500"><div className="w-2 h-2 rounded-full bg-amber-500"></div> Forecast</span>
                         <span className="flex items-center gap-1 text-stone-500"><div className="w-2 h-2 rounded-full bg-stone-200"></div> Confidence Interval</span>
                      </div>
                   </div>
                   <div className="flex-1 min-h-[300px]">
                      <ResponsiveContainer width="100%" height="100%">
                         <AreaChart data={forecastData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                            <defs>
                               <linearGradient id="colorPredicted" x1="0" y1="0" x2="0" y2="1">
                                  <stop offset="5%" stopColor="#f59e0b" stopOpacity={0.8}/>
                                  <stop offset="95%" stopColor="#f59e0b" stopOpacity={0}/>
                               </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e7e5e4" />
                            <XAxis dataKey="date" tickLine={false} axisLine={false} fontSize={12} stroke="#78716c" dy={10} />
                            <YAxis tickLine={false} axisLine={false} fontSize={12} stroke="#78716c" />
                            <Tooltip 
                               contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' }}
                               cursor={{ stroke: '#a8a29e', strokeWidth: 1, strokeDasharray: '4 4' }}
                            />
                            <ReferenceLine y={rop} label="ROP" stroke="red" strokeDasharray="3 3" />
                            {/* Confidence Interval using stacked areas logic or just simple overlay */}
                            <Area type="monotone" dataKey="upperBound" stroke="none" fill="#e7e5e4" fillOpacity={0.5} />
                            <Area type="monotone" dataKey="predicted" stroke="#d97706" strokeWidth={3} fill="url(#colorPredicted)" />
                         </AreaChart>
                      </ResponsiveContainer>
                   </div>
                   <div className="p-3 bg-stone-50 rounded-lg text-xs text-stone-500 flex gap-2">
                      <Info size={16} className="text-stone-400 flex-shrink-0" />
                      <p>Forecast generated using seasonal ARIMA model. The red line indicates your current Reorder Point. If the forecast curve crosses it consistently, consider increasing Safety Stock.</p>
                   </div>
                </div>
              )}

              {activeTab === 'vendors' && (
                 <div className="space-y-4 animate-in fade-in">
                    <h4 className="font-bold text-stone-900 mb-4">True Cost of Ownership Analysis</h4>
                    <div className="overflow-x-auto">
                       <table className="w-full text-left border-collapse">
                          <thead>
                             <tr className="text-xs text-stone-400 uppercase border-b border-stone-200">
                                <th className="pb-3 font-semibold">Vendor</th>
                                <th className="pb-3 font-semibold text-right">Base Price</th>
                                <th className="pb-3 font-semibold text-right">Duties & Ship</th>
                                <th className="pb-3 font-semibold text-right">Risk & Hold</th>
                                <th className="pb-3 font-semibold text-right">Total / Unit</th>
                                <th className="pb-3"></th>
                             </tr>
                          </thead>
                          <tbody className="divide-y divide-stone-100">
                             {vendorAnalysis.map(v => {
                                const sItem = SUPPLIER_ITEMS.find(si => si.supplierId === v.supplierId && si.itemId === itemId);
                                const moq = sItem?.minOrderQty || 1;
                                const currentQty = orderQuantities[v.supplierId] ?? moq;
                                
                                return (
                                <React.Fragment key={v.supplierId}>
                                   <tr 
                                      onClick={() => setExpandedVendorId(expandedVendorId === v.supplierId ? null : v.supplierId)}
                                      className={`group cursor-pointer transition-colors ${v.isBestValue ? 'bg-emerald-50/30' : 'hover:bg-stone-50'}`}
                                   >
                                      <td className="py-4 pl-2">
                                         <div className="flex items-center gap-3">
                                            <button className="p-1 rounded hover:bg-stone-200 text-stone-400">
                                               {expandedVendorId === v.supplierId ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                            </button>
                                            <div>
                                               <div className="font-bold text-stone-900">{v.supplierName}</div>
                                               {v.isBestValue && <span className="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold">BEST VALUE</span>}
                                            </div>
                                         </div>
                                      </td>
                                      <td className="py-4 text-right text-stone-600">${v.unitPrice.toFixed(2)}</td>
                                      <td className="py-4 text-right text-stone-500 text-xs">+${(v.deliveryFeePerUnit + v.dutiesPerUnit).toFixed(2)}</td>
                                      <td className="py-4 text-right text-stone-500 text-xs">+${(v.stockoutRiskCost + v.holdingCost).toFixed(2)}</td>
                                      <td className="py-4 text-right">
                                         <div className="font-bold text-stone-900">${v.totalPerUnit.toFixed(2)}</div>
                                      </td>
                                      <td className="py-4 text-right">
                                         <div className="flex gap-2 justify-end items-center opacity-0 group-hover:opacity-100 transition-opacity" onClick={(e) => e.stopPropagation()}>
                                            
                                            {/* Comparison Button */}
                                            <button 
                                               onClick={() => setComparingSupplierId(v.supplierId)}
                                               className="p-2 text-stone-400 hover:text-stone-600 hover:bg-stone-100 rounded-lg transition-colors"
                                               title="Simulate Scenario"
                                            >
                                               <ArrowRightLeft size={16}/>
                                            </button>
                                            
                                            {/* Vertical Divider */}
                                            <div className="w-px h-6 bg-stone-200 mx-1"></div>

                                            {/* Quick Order Input Group */}
                                            <div className="flex items-center gap-1 bg-white border border-stone-200 rounded-lg p-0.5 shadow-sm group-hover:border-amber-300 transition-colors">
                                                <input 
                                                    type="number"
                                                    min={moq}
                                                    value={currentQty}
                                                    onChange={(e) => setOrderQuantities(prev => ({...prev, [v.supplierId]: parseInt(e.target.value)}))}
                                                    onClick={(e) => e.stopPropagation()}
                                                    className="w-16 px-2 py-1 text-xs font-bold text-right outline-none bg-transparent appearance-none"
                                                />
                                                <button
                                                    onClick={(e) => { e.stopPropagation(); handleQuickOrder(v, currentQty); }}
                                                    disabled={orderingStatus[v.supplierId] === 'ordering' || orderingStatus[v.supplierId] === 'success' || currentQty < moq}
                                                    className={`h-7 px-3 rounded-md flex items-center justify-center transition-all ${
                                                        orderingStatus[v.supplierId] === 'success' ? 'bg-emerald-500 text-white' :
                                                        orderingStatus[v.supplierId] === 'ordering' ? 'bg-stone-100 text-stone-400' :
                                                        'bg-amber-100 text-amber-700 hover:bg-amber-500 hover:text-white'
                                                    } ${currentQty < moq ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                    title={currentQty < moq ? `Minimum Order Qty: ${moq}` : 'Place Order'}
                                                >
                                                    {orderingStatus[v.supplierId] === 'ordering' ? <Loader2 size={12} className="animate-spin"/> :
                                                     orderingStatus[v.supplierId] === 'success' ? <Check size={14} /> :
                                                     <ShoppingCart size={14} />}
                                                </button>
                                            </div>
                                         </div>
                                      </td>
                                   </tr>
                                   {expandedVendorId === v.supplierId && (
                                      <tr>
                                         <td colSpan={6} className="p-0 border-none">
                                            <div className="bg-stone-50 border-y border-stone-200 p-4 shadow-inner animate-in slide-in-from-top-2 duration-200">
                                               <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                                   <div className="bg-white p-3 rounded-lg border border-stone-200 shadow-sm">
                                                       <div className="text-[10px] text-stone-400 uppercase font-bold mb-1">Unit Price</div>
                                                       <div className="font-bold text-stone-900 text-sm">${v.unitPrice.toFixed(2)}</div>
                                                       <div className="w-full bg-stone-100 h-1.5 mt-2 rounded-full overflow-hidden">
                                                           <div className="bg-stone-500 h-full" style={{ width: '100%' }}></div>
                                                       </div>
                                                   </div>
                                                   <div className="bg-white p-3 rounded-lg border border-stone-200 shadow-sm">
                                                       <div className="text-[10px] text-stone-400 uppercase font-bold mb-1">Logistics & Duties</div>
                                                       <div className="font-bold text-stone-900 text-sm">${(v.deliveryFeePerUnit + v.dutiesPerUnit).toFixed(2)}</div>
                                                        <div className="w-full bg-stone-100 h-1.5 mt-2 rounded-full overflow-hidden">
                                                           <div className="bg-blue-400 h-full" style={{ width: `${((v.deliveryFeePerUnit + v.dutiesPerUnit)/v.totalPerUnit)*100}%` }}></div>
                                                       </div>
                                                   </div>
                                                   <div className="bg-white p-3 rounded-lg border border-stone-200 shadow-sm">
                                                       <div className="text-[10px] text-stone-400 uppercase font-bold mb-1">Holding Cost</div>
                                                       <div className="font-bold text-stone-900 text-sm">${v.holdingCost.toFixed(2)}</div>
                                                        <div className="w-full bg-stone-100 h-1.5 mt-2 rounded-full overflow-hidden">
                                                           <div className="bg-amber-400 h-full" style={{ width: `${(v.holdingCost/v.totalPerUnit)*100}%` }}></div>
                                                       </div>
                                                   </div>
                                                   <div className="bg-white p-3 rounded-lg border border-stone-200 shadow-sm">
                                                       <div className="text-[10px] text-stone-400 uppercase font-bold mb-1">Risk Premium</div>
                                                       <div className="font-bold text-stone-900 text-sm">${v.stockoutRiskCost.toFixed(2)}</div>
                                                        <div className="w-full bg-stone-100 h-1.5 mt-2 rounded-full overflow-hidden">
                                                           <div className="bg-rose-400 h-full" style={{ width: `${(v.stockoutRiskCost/v.totalPerUnit)*100}%` }}></div>
                                                       </div>
                                                   </div>
                                               </div>

                                               {/* Visual Chart Section */}
                                               <div className="mb-6 px-4">
                                                   <div className="h-16 w-full">
                                                       <ResponsiveContainer width="100%" height="100%">
                                                           <BarChart layout="vertical" data={[{
                                                               name: 'Cost',
                                                               'Base Price': v.unitPrice,
                                                               'Duties & Ship': v.deliveryFeePerUnit + v.dutiesPerUnit,
                                                               'Holding': v.holdingCost,
                                                               'Risk': v.stockoutRiskCost,
                                                           }]}>
                                                               <XAxis type="number" hide />
                                                               <YAxis type="category" dataKey="name" hide />
                                                               <Tooltip 
                                                                   cursor={{ fill: 'transparent' }}
                                                                   formatter={(value: number) => [`$${value.toFixed(2)}`, '']}
                                                                   contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '12px' }}
                                                               />
                                                               <Legend iconSize={10} wrapperStyle={{ fontSize: '11px', paddingTop: '6px' }} />
                                                               <Bar dataKey="Base Price" stackId="a" fill="#78716c" radius={[4, 0, 0, 4]} />
                                                               <Bar dataKey="Duties & Ship" stackId="a" fill="#60a5fa" />
                                                               <Bar dataKey="Holding" stackId="a" fill="#fbbf24" />
                                                               <Bar dataKey="Risk" stackId="a" fill="#fb7185" radius={[0, 4, 4, 0]} />
                                                           </BarChart>
                                                       </ResponsiveContainer>
                                                   </div>
                                               </div>

                                               <div className="text-center text-xs text-stone-400">
                                                   Detailed Total: <span className="font-mono text-stone-700">${v.totalPerUnit.toFixed(2)}</span> per unit
                                               </div>
                                            </div>
                                         </td>
                                      </tr>
                                   )}
                                </React.Fragment>
                             )})}
                          </tbody>
                       </table>
                    </div>
                    <div className="mt-4 p-4 bg-stone-50 border border-stone-100 rounded-xl">
                       <h5 className="text-xs font-bold text-stone-900 uppercase mb-2">Cost Breakdown Logic</h5>
                       <ul className="text-xs text-stone-500 space-y-1 list-disc list-inside">
                          <li><strong>Base Price:</strong> Vendor list price based on MOQ.</li>
                          <li><strong>Duties & Ship:</strong> Flat shipping and estimated import duties if international.</li>
                          <li><strong>Holding:</strong> Storage cost amortized over lead time duration.</li>
                          <li><strong>Risk Premium:</strong> Calculated based on vendor reliability score.</li>
                       </ul>
                    </div>
                 </div>
              )}

              {activeTab === 'expiry' && (
                 <div className="space-y-6 animate-in fade-in">
                    <div className="flex items-center gap-3 p-4 bg-rose-50 text-rose-800 rounded-xl border border-rose-100">
                       <AlertTriangle size={24} />
                       <div>
                          <h4 className="font-bold text-sm">Perishability Constraints Active</h4>
                          <p className="text-xs mt-1">First-Expired-First-Out (FEFO) logic recommended. Max sensible order quantity limited by shelf life.</p>
                       </div>
                    </div>

                    <div>
                       <h5 className="font-bold text-stone-900 text-sm mb-4">Batch Expiry Timeline</h5>
                       {/* Mock Visual Timeline */}
                       <div className="relative pt-6 pb-2">
                          <div className="absolute top-8 left-0 w-full h-1 bg-stone-200 rounded-full"></div>
                          <div className="grid grid-cols-4 gap-4 relative">
                             {/* Batch 1 */}
                             <div className="text-center">
                                <div className="mx-auto w-4 h-4 bg-rose-500 rounded-full border-4 border-white shadow-sm relative z-10"></div>
                                <div className="mt-2">
                                   <p className="text-xs font-bold text-rose-600">Batch A</p>
                                   <p className="text-[10px] text-stone-500">Exp: 2 Days</p>
                                   <p className="text-xs font-bold text-stone-900 mt-1">15 units</p>
                                </div>
                             </div>
                             {/* Batch 2 */}
                             <div className="text-center">
                                <div className="mx-auto w-4 h-4 bg-amber-500 rounded-full border-4 border-white shadow-sm relative z-10"></div>
                                <div className="mt-2">
                                   <p className="text-xs font-bold text-amber-600">Batch B</p>
                                   <p className="text-[10px] text-stone-500">Exp: 7 Days</p>
                                   <p className="text-xs font-bold text-stone-900 mt-1">45 units</p>
                                </div>
                             </div>
                             {/* Batch 3 */}
                             <div className="text-center opacity-50">
                                <div className="mx-auto w-4 h-4 bg-emerald-500 rounded-full border-4 border-white shadow-sm relative z-10"></div>
                                <div className="mt-2">
                                   <p className="text-xs font-bold text-emerald-600">Batch C</p>
                                   <p className="text-[10px] text-stone-500">Exp: 14 Days</p>
                                   <p className="text-xs font-bold text-stone-900 mt-1">--</p>
                                </div>
                             </div>
                          </div>
                       </div>
                    </div>
                 </div>
              )}

           </div>
        </div>

      </div>
      
      {/* Comparison Modal */}
      {renderComparisonModal()}
      
    </div>
  );
};

export default SkuDetail;