
import React, { useState, useMemo, useEffect } from 'react';
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, AreaChart, Area, Cell, PieChart, Pie 
} from 'recharts';
import { 
  Trash2, 
  TrendingDown, 
  TrendingUp, 
  Filter, 
  Calendar, 
  Download, 
  AlertOctagon, 
  FileText, 
  Settings, 
  ArrowRight, 
  Search, 
  DollarSign, 
  PieChart as PieIcon, 
  List,
  ChevronDown,
  ChevronUp,
  User,
  Clock
} from 'lucide-react';
import { useApp } from '../App';
import { generateMockWasteData, generateMockPolicyChanges } from '../services/wasteService';
import { WasteEvent, PolicyChangeLog } from '../types';

const WasteReports: React.FC = () => {
  const { items, locations, currentLocationId } = useApp();
  
  // State
  const [dateRange, setDateRange] = useState({ start: '2023-09-01', end: '2023-10-31' });
  const [wasteEvents, setWasteEvents] = useState<WasteEvent[]>([]);
  const [policyLogs, setPolicyLogs] = useState<PolicyChangeLog[]>([]);
  const [activeTab, setActiveTab] = useState<'overview' | 'policies'>('overview');
  const [reasonFilter, setReasonFilter] = useState<string>('all');
  const [searchTerm, setSearchTerm] = useState('');
  
  // Track expanded policy item
  const [expandedPolicyId, setExpandedPolicyId] = useState<string | null>(null);

  // Load Data
  useEffect(() => {
    const start = new Date(dateRange.start);
    const end = new Date(dateRange.end);
    setWasteEvents(generateMockWasteData(items, locations, start, end));
    setPolicyLogs(generateMockPolicyChanges(items, locations, start, end));
  }, [dateRange, items, locations]);

  // --- Filtering Logic ---
  const filteredWaste = useMemo(() => {
    return wasteEvents.filter(e => {
      // Location Filter
      if (currentLocationId !== 'all' && e.locationId !== currentLocationId) return false;
      // Reason Filter
      if (reasonFilter !== 'all' && e.reason !== reasonFilter) return false;
      // Search
      if (searchTerm) {
        const item = items.find(i => i.id === e.skuId);
        if (item && !item.name.toLowerCase().includes(searchTerm.toLowerCase())) return false;
      }
      return true;
    });
  }, [wasteEvents, currentLocationId, reasonFilter, searchTerm, items]);

  const filteredPolicies = useMemo(() => {
    return policyLogs.filter(l => {
      if (currentLocationId !== 'all' && l.locationId !== currentLocationId) return false;
       if (searchTerm) {
        const term = searchTerm.toLowerCase();
        const item = items.find(i => i.id === l.skuId);
        const matchesItem = item ? item.name.toLowerCase().includes(term) : false;
        const matchesUser = l.user.toLowerCase().includes(term);
        return matchesItem || matchesUser;
      }
      return true;
    });
  }, [policyLogs, currentLocationId, searchTerm, items]);

  // --- CSV Export Helper ---
  const downloadCSV = (data: any[], filename: string) => {
    if (!data || data.length === 0) {
      alert("No data to export");
      return;
    }

    // Extract headers from the first object
    const headers = Object.keys(data[0]);
    
    // Convert data to CSV format
    const csvContent = [
      headers.join(','),
      ...data.map(row => 
        headers.map(fieldName => {
          const value = row[fieldName];
          // Escape quotes and wrap in quotes if string contains comma
          return typeof value === 'string' && value.includes(',') 
            ? `"${value.replace(/"/g, '""')}"` 
            : value;
        }).join(',')
      )
    ].join('\n');

    // Create download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // --- Aggregations ---
  
  const totalWasteCost = useMemo(() => filteredWaste.reduce((acc, e) => acc + (e.qty * e.unitCost), 0), [filteredWaste]);
  
  // Monthly Trend Data
  const trendData = useMemo(() => {
    const grouped: Record<string, number> = {};
    filteredWaste.forEach(e => {
       // Group by Week or Month? Let's do Month-Day sort of aggregation for the chart
       // Simplify to just Date string for the Area chart
       const d = new Date(e.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
       grouped[d] = (grouped[d] || 0) + (e.qty * e.unitCost);
    });
    return Object.entries(grouped).map(([date, value]) => ({ date, value }));
  }, [filteredWaste]);

  // Reason Breakdown
  const reasonData = useMemo(() => {
    const grouped: Record<string, number> = {};
    filteredWaste.forEach(e => {
       grouped[e.reason] = (grouped[e.reason] || 0) + (e.qty * e.unitCost);
    });
    return Object.entries(grouped)
      .map(([name, value]) => ({ name: name.replace('_', ' '), value }))
      .sort((a,b) => b.value - a.value);
  }, [filteredWaste]);

  // Location Breakdown
  const locationData = useMemo(() => {
    const grouped: Record<string, number> = {};
    filteredWaste.forEach(e => {
       const loc = locations.find(l => l.id === e.locationId);
       const name = loc ? loc.name : 'Unknown';
       grouped[name] = (grouped[name] || 0) + (e.qty * e.unitCost);
    });
    return Object.entries(grouped)
      .map(([name, value]) => ({ name, value }))
      .sort((a,b) => b.value - a.value);
  }, [filteredWaste, locations]);

  const topWastedItems = useMemo(() => {
     const grouped: Record<string, { name: string; cost: number; qty: number }> = {};
     filteredWaste.forEach(e => {
        const item = items.find(i => i.id === e.skuId);
        if (!item) return;
        if (!grouped[e.skuId]) grouped[e.skuId] = { name: item.name, cost: 0, qty: 0 };
        grouped[e.skuId].cost += (e.qty * e.unitCost);
        grouped[e.skuId].qty += e.qty;
     });
     return Object.values(grouped).sort((a,b) => b.cost - a.cost).slice(0, 5);
  }, [filteredWaste, items]);

  const COLORS = ['#f59e0b', '#ef4444', '#3b82f6', '#10b981', '#6b7280'];

  return (
    <div className="space-y-6 pb-20 animate-in fade-in duration-500">
      
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
           <h2 className="text-2xl font-bold text-stone-900 flex items-center gap-2">
              <Trash2 className="text-stone-400" /> Waste & Performance
           </h2>
           <p className="text-stone-500">Track spoilage costs and analyze policy effectiveness.</p>
        </div>
        
        <div className="flex items-center gap-2 bg-white p-1 rounded-lg border border-stone-200 shadow-sm">
           <div className="relative border-r border-stone-100 pr-2 mr-2">
              <Calendar size={14} className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400"/>
              <input 
                type="date" 
                value={dateRange.start}
                onChange={e => setDateRange(prev => ({ ...prev, start: e.target.value }))}
                className="pl-7 py-1 text-xs font-medium outline-none text-stone-600 bg-transparent w-28"
              />
           </div>
           <div className="relative">
              <input 
                type="date" 
                value={dateRange.end}
                onChange={e => setDateRange(prev => ({ ...prev, end: e.target.value }))}
                className="py-1 text-xs font-medium outline-none text-stone-600 bg-transparent w-28"
              />
           </div>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex gap-4 border-b border-stone-200">
         <button 
           onClick={() => { setActiveTab('overview'); setSearchTerm(''); }}
           className={`pb-3 text-sm font-bold transition-colors relative flex items-center gap-2 ${activeTab === 'overview' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
         >
            <PieIcon size={16} /> Waste Analysis
            {activeTab === 'overview' && <div className="absolute bottom-0 left-0 w-full h-0.5 bg-amber-500 rounded-t-full"></div>}
         </button>
         <button 
           onClick={() => { setActiveTab('policies'); setSearchTerm(''); }}
           className={`pb-3 text-sm font-bold transition-colors relative flex items-center gap-2 ${activeTab === 'policies' ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600'}`}
         >
            <Settings size={16} /> Policy Impact Report
            {activeTab === 'policies' && <div className="absolute bottom-0 left-0 w-full h-0.5 bg-amber-500 rounded-t-full"></div>}
         </button>
      </div>

      {/* --- TAB CONTENT --- */}
      
      {activeTab === 'overview' && (
         <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2">
            
            {/* KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
               <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-sm">
                  <div className="text-stone-500 text-xs font-bold uppercase tracking-wider mb-2">Total Waste Cost</div>
                  <div className="text-3xl font-bold text-stone-900 flex items-baseline gap-2">
                     ${totalWasteCost.toLocaleString()}
                     <span className="text-sm font-medium text-rose-500 flex items-center">
                        <TrendingUp size={14} /> +4.2%
                     </span>
                  </div>
                  <div className="text-xs text-stone-400 mt-1">vs previous period</div>
               </div>
               
               <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-sm">
                  <div className="text-stone-500 text-xs font-bold uppercase tracking-wider mb-2">Top Driver</div>
                  <div className="text-2xl font-bold text-stone-900">
                     {reasonData[0]?.name || '--'}
                  </div>
                  <div className="text-xs text-stone-400 mt-1">
                     Accounted for {((reasonData[0]?.value || 0) / totalWasteCost * 100).toFixed(0)}% of loss
                  </div>
               </div>

               <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-sm flex flex-col justify-center items-center text-center">
                  <div className="p-3 bg-emerald-50 text-emerald-600 rounded-full mb-2">
                     <FileText size={24} />
                  </div>
                  <button 
                     onClick={() => downloadCSV(filteredWaste, `waste_report_${dateRange.start}_${dateRange.end}.csv`)}
                     className="text-xs font-bold text-stone-600 hover:text-amber-600 flex items-center gap-1 transition-colors"
                  >
                     Download Full Report <Download size={12} />
                  </button>
               </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
               
               {/* Left: Charts */}
               <div className="lg:col-span-2 space-y-6">
                  {/* Cost Trend */}
                  <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm h-80">
                     <h3 className="font-bold text-stone-900 mb-4">Daily Waste Cost Trend</h3>
                     <ResponsiveContainer width="100%" height="90%">
                        <AreaChart data={trendData}>
                           <defs>
                              <linearGradient id="colorWaste" x1="0" y1="0" x2="0" y2="1">
                                 <stop offset="5%" stopColor="#ef4444" stopOpacity={0.2}/>
                                 <stop offset="95%" stopColor="#ef4444" stopOpacity={0}/>
                              </linearGradient>
                           </defs>
                           <XAxis dataKey="date" stroke="#a8a29e" fontSize={10} tickLine={false} axisLine={false} minTickGap={30}/>
                           <YAxis stroke="#a8a29e" fontSize={10} tickLine={false} axisLine={false} tickFormatter={(v) => `$${v}`}/>
                           <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f5f5f4" />
                           <Tooltip 
                              contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '12px' }}
                              formatter={(value: number) => [`$${value.toFixed(2)}`, 'Cost']}
                           />
                           <Area type="monotone" dataKey="value" stroke="#ef4444" strokeWidth={2} fill="url(#colorWaste)" />
                        </AreaChart>
                     </ResponsiveContainer>
                  </div>

                  {/* Detailed Table */}
                  <div className="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
                     <div className="p-4 border-b border-stone-100 flex flex-col sm:flex-row justify-between gap-4 bg-stone-50">
                        <h3 className="font-bold text-stone-900 flex items-center gap-2">
                           <List size={18} /> Waste Events
                        </h3>
                        <div className="flex gap-2">
                           <div className="relative">
                              <Search size={14} className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400"/>
                              <input 
                                 type="text" 
                                 placeholder="Search SKU..."
                                 value={searchTerm}
                                 onChange={(e) => setSearchTerm(e.target.value)}
                                 className="pl-7 pr-3 py-1.5 rounded-lg border border-stone-200 text-xs w-40 outline-none focus:border-amber-400"
                              />
                           </div>
                           <select 
                              value={reasonFilter}
                              onChange={(e) => setReasonFilter(e.target.value)}
                              className="px-2 py-1.5 rounded-lg border border-stone-200 text-xs outline-none bg-white cursor-pointer"
                           >
                              <option value="all">All Reasons</option>
                              <option value="EXPIRY">Expiry</option>
                              <option value="OVER_ORDER">Over Order</option>
                              <option value="FORECAST_MISS">Forecast Miss</option>
                              <option value="SUPPLIER_DELAY">Supplier Delay</option>
                           </select>
                        </div>
                     </div>
                     <div className="max-h-96 overflow-y-auto">
                        <table className="w-full text-left text-sm">
                           <thead className="bg-stone-50 text-stone-500 uppercase text-[10px] sticky top-0 z-10">
                              <tr>
                                 <th className="px-4 py-3 font-semibold">Date</th>
                                 <th className="px-4 py-3 font-semibold">Item / Location</th>
                                 <th className="px-4 py-3 font-semibold">Reason</th>
                                 <th className="px-4 py-3 font-semibold text-right">Qty</th>
                                 <th className="px-4 py-3 font-semibold text-right">Cost</th>
                              </tr>
                           </thead>
                           <tbody className="divide-y divide-stone-50 text-xs">
                              {filteredWaste.slice(0, 50).map(e => {
                                 const item = items.find(i => i.id === e.skuId);
                                 const loc = locations.find(l => l.id === e.locationId);
                                 return (
                                    <tr key={e.id} className="hover:bg-stone-50">
                                       <td className="px-4 py-3 text-stone-500 whitespace-nowrap">{e.date}</td>
                                       <td className="px-4 py-3">
                                          <div className="font-bold text-stone-900">{item?.name}</div>
                                          <div className="text-[10px] text-stone-400">{loc?.name}</div>
                                       </td>
                                       <td className="px-4 py-3">
                                          <span className={`px-2 py-1 rounded-full text-[10px] font-bold ${
                                             e.reason === 'EXPIRY' ? 'bg-amber-100 text-amber-700' :
                                             e.reason === 'SUPPLIER_DELAY' ? 'bg-blue-100 text-blue-700' :
                                             'bg-stone-100 text-stone-600'
                                          }`}>
                                             {e.reason.replace('_', ' ')}
                                          </span>
                                       </td>
                                       <td className="px-4 py-3 text-right font-mono">{e.qty}</td>
                                       <td className="px-4 py-3 text-right font-mono font-medium text-stone-900">
                                          ${(e.qty * e.unitCost).toFixed(2)}
                                       </td>
                                    </tr>
                                 );
                              })}
                           </tbody>
                        </table>
                     </div>
                  </div>
               </div>

               {/* Right: Breakdown Stats */}
               <div className="space-y-6">
                  
                  {/* Category Breakdown */}
                  <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm min-h-[300px]">
                     <h3 className="font-bold text-stone-900 mb-4">Cost by Category</h3>
                     <div className="h-48">
                        <ResponsiveContainer width="100%" height="100%">
                           <PieChart>
                              <Pie
                                 data={reasonData}
                                 innerRadius={40}
                                 outerRadius={70}
                                 paddingAngle={5}
                                 dataKey="value"
                              >
                                 {reasonData.map((entry, index) => (
                                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                 ))}
                              </Pie>
                              <Tooltip 
                                 contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '12px' }}
                                 formatter={(v: number) => `$${v.toFixed(0)}`}
                              />
                           </PieChart>
                        </ResponsiveContainer>
                     </div>
                     <div className="space-y-2 mt-2">
                        {reasonData.map((entry, idx) => (
                           <div key={entry.name} className="flex justify-between items-center text-xs">
                              <div className="flex items-center gap-2">
                                 <div className="w-2 h-2 rounded-full" style={{ backgroundColor: COLORS[idx % COLORS.length] }}></div>
                                 <span className="text-stone-600">{entry.name}</span>
                              </div>
                              <span className="font-bold text-stone-900">${entry.value.toFixed(0)}</span>
                           </div>
                        ))}
                     </div>
                  </div>

                  {/* Location Breakdown (New) */}
                  <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm">
                     <h3 className="font-bold text-stone-900 mb-4">Cost by Location</h3>
                     <div className="h-48">
                        <ResponsiveContainer width="100%" height="100%">
                           <BarChart data={locationData} layout="vertical" margin={{ left: 0, right: 30 }}>
                              <XAxis type="number" hide />
                              <YAxis dataKey="name" type="category" width={90} tick={{fontSize: 10, fill: '#78716c'}} interval={0} />
                              <Tooltip 
                                 cursor={{fill: 'transparent'}}
                                 contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '12px' }}
                                 formatter={(v: number) => [`$${v.toFixed(0)}`, 'Cost']}
                              />
                              <Bar dataKey="value" radius={[0, 4, 4, 0]} barSize={20}>
                                {locationData.map((entry, index) => (
                                  <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                ))}
                              </Bar>
                           </BarChart>
                        </ResponsiveContainer>
                     </div>
                  </div>

                  {/* Top Offenders */}
                  <div className="bg-stone-900 text-white p-6 rounded-2xl border border-stone-800 shadow-lg">
                     <h3 className="font-bold text-sm uppercase tracking-wider mb-4 flex items-center gap-2">
                        <AlertOctagon size={16} className="text-rose-500" /> Top Offenders
                     </h3>
                     <div className="space-y-4">
                        {topWastedItems.map((item, idx) => (
                           <div key={idx} className="flex justify-between items-center">
                              <div className="flex items-center gap-3">
                                 <span className="text-stone-500 font-mono text-xs">0{idx+1}</span>
                                 <div>
                                    <div className="font-bold text-sm">{item.name}</div>
                                    <div className="text-[10px] text-stone-400">{item.qty} units lost</div>
                                 </div>
                              </div>
                              <div className="text-right">
                                 <div className="font-bold text-rose-400">${item.cost.toFixed(0)}</div>
                              </div>
                           </div>
                        ))}
                     </div>
                  </div>

               </div>
            </div>
         </div>
      )}

      {activeTab === 'policies' && (
         <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in slide-in-from-right-2">
            
            {/* Left: Change Log */}
            <div className="lg:col-span-2 bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
               <div className="p-5 border-b border-stone-100 bg-stone-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                  <div className="flex items-center gap-4 w-full sm:w-auto">
                     <h3 className="font-bold text-stone-900 whitespace-nowrap">Policy Change History</h3>
                     <div className="relative flex-1 sm:flex-none">
                        <Search size={14} className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400"/>
                        <input 
                           type="text" 
                           placeholder="Search SKU or User..."
                           value={searchTerm}
                           onChange={(e) => setSearchTerm(e.target.value)}
                           className="w-full sm:w-48 pl-7 pr-3 py-1.5 rounded-lg border border-stone-200 text-xs outline-none focus:border-amber-400 bg-white"
                        />
                     </div>
                  </div>
                  <button 
                     onClick={() => downloadCSV(filteredPolicies, `policy_log_${dateRange.start}_${dateRange.end}.csv`)}
                     className="text-xs font-bold text-stone-500 hover:text-amber-600 flex items-center gap-1 bg-white border border-stone-200 px-2 py-1 rounded shadow-sm transition-colors whitespace-nowrap"
                  >
                     <Download size={12} /> Export CSV
                  </button>
               </div>
               <div className="divide-y divide-stone-100">
                  {filteredPolicies.map(log => {
                     const item = items.find(i => i.id === log.skuId);
                     const loc = locations.find(l => l.id === log.locationId);
                     const isExpanded = expandedPolicyId === log.id;
                     
                     return (
                        <div 
                           key={log.id} 
                           onClick={() => setExpandedPolicyId(isExpanded ? null : log.id)}
                           className={`p-5 transition-all cursor-pointer border-b border-stone-100 last:border-0 ${
                              isExpanded ? 'bg-stone-50' : 'hover:bg-stone-50 bg-white'
                           }`}
                        >
                           <div className="flex justify-between items-start">
                              <div className="flex-1">
                                 <div className="flex items-center gap-2 mb-1.5">
                                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wide ${
                                       log.changeType === 'REORDER_POINT' ? 'bg-blue-100 text-blue-700' :
                                       log.changeType === 'SAFETY_STOCK' ? 'bg-amber-100 text-amber-700' :
                                       'bg-stone-200 text-stone-600'
                                    }`}>
                                       {log.changeType.replace('_', ' ')}
                                    </span>
                                    <span className="text-xs text-stone-400 flex items-center gap-1">
                                       <Clock size={10} /> {log.date}
                                    </span>
                                 </div>
                                 
                                 <h4 className="text-sm font-bold text-stone-900">
                                    Updated {item ? item.name : 'System'} {loc ? `at ${loc.name}` : ''}
                                 </h4>
                                 
                                 {!isExpanded && (
                                    <div className="mt-2 text-xs text-stone-500 truncate flex items-center gap-1">
                                       <User size={10} /> {log.user}
                                    </div>
                                 )}
                              </div>
                              
                              <div className="text-stone-400 ml-4 mt-1">
                                 {isExpanded ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                              </div>
                           </div>
                           
                           {isExpanded && (
                              <div className="mt-4 animate-in fade-in slide-in-from-top-1 duration-200 cursor-default" onClick={(e) => e.stopPropagation()}>
                                 <div className="flex items-center gap-3 text-sm text-stone-600 bg-white p-3 rounded-lg border border-stone-200 w-full mb-3 shadow-sm">
                                    <div className="flex-1 text-center border-r border-stone-100 pr-2">
                                       <div className="text-[10px] text-stone-400 uppercase font-bold">Previous</div>
                                       <div className="font-mono line-through text-stone-400 mt-0.5">{log.oldValue}</div>
                                    </div>
                                    <ArrowRight size={14} className="text-stone-300 flex-shrink-0"/>
                                    <div className="flex-1 text-center border-l border-stone-100 pl-2">
                                       <div className="text-[10px] text-stone-400 uppercase font-bold">New Value</div>
                                       <div className="font-mono font-bold text-stone-900 mt-0.5">{log.newValue}</div>
                                    </div>
                                 </div>
                                 
                                 <div className="space-y-3">
                                    <div>
                                       <span className="text-[10px] font-bold text-stone-400 uppercase">Reason</span>
                                       <p className="text-xs text-stone-600 italic bg-stone-100/50 p-2 rounded mt-1 border border-stone-100">
                                          "{log.reason}"
                                       </p>
                                    </div>
                                    
                                    <div className="flex justify-between items-end pt-2">
                                       <div className="flex items-center gap-2 text-xs text-stone-500">
                                          <div className="w-6 h-6 rounded-full bg-stone-200 flex items-center justify-center text-stone-500">
                                             <User size={12} />
                                          </div>
                                          <div className="flex flex-col">
                                             <span className="font-bold text-stone-700">{log.user}</span>
                                             <span className="text-[10px]">Authorized Change</span>
                                          </div>
                                       </div>
                                       
                                       {log.impactMetric && (
                                          <span className="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded border border-emerald-100 flex items-center gap-1">
                                             <DollarSign size={10} /> {log.impactMetric}
                                          </span>
                                       )}
                                    </div>
                                 </div>
                              </div>
                           )}
                        </div>
                     );
                  })}
                  {filteredPolicies.length === 0 && (
                     <div className="p-8 text-center text-stone-400 text-sm">No policy changes recorded in this period.</div>
                  )}
               </div>
            </div>

            {/* Right: Correlation Insight (Static/Simulated) */}
            <div className="space-y-6">
               <div className="bg-gradient-to-br from-indigo-900 to-stone-900 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden">
                  <div className="relative z-10">
                     <h3 className="font-bold text-lg mb-2 flex items-center gap-2">
                        <TrendingUp className="text-emerald-400" /> Policy Impact Analysis
                     </h3>
                     <p className="text-sm text-indigo-100 mb-6 leading-relaxed">
                        Correlating recent Safety Stock adjustments with spoilage rates indicates a positive trend.
                     </p>
                     
                     <div className="space-y-4">
                        <div className="bg-white/10 rounded-xl p-3 border border-white/10">
                           <div className="text-xs text-indigo-200 uppercase font-bold mb-1">Waste Reduction</div>
                           <div className="text-2xl font-bold text-emerald-400">-12%</div>
                           <div className="text-[10px] text-indigo-300 mt-1">Since adjusting ROP for 'Milk'</div>
                        </div>
                        <div className="bg-white/10 rounded-xl p-3 border border-white/10">
                           <div className="text-xs text-indigo-200 uppercase font-bold mb-1">Stockout Events</div>
                           <div className="text-2xl font-bold text-white">0</div>
                           <div className="text-[10px] text-indigo-300 mt-1">Maintained 100% service level</div>
                        </div>
                     </div>
                  </div>
                  {/* Decorative BG */}
                  <div className="absolute -bottom-10 -right-10 w-40 h-40 bg-indigo-500 rounded-full blur-3xl opacity-20"></div>
               </div>

               <div className="bg-amber-50 rounded-xl p-5 border border-amber-100">
                  <h4 className="text-sm font-bold text-amber-900 mb-2 flex items-center gap-2">
                     <AlertOctagon size={16} /> Recommendation
                  </h4>
                  <p className="text-xs text-amber-800 leading-relaxed">
                     High spoilage recorded for <strong>Pastry</strong> category at <strong>Uptown Kiosk</strong> on weekends. 
                     Consider reducing Standing Order quantity by 15% on Saturdays.
                  </p>
                  <button className="mt-3 w-full py-2 bg-white border border-amber-200 text-amber-700 text-xs font-bold rounded-lg hover:bg-amber-100 transition-colors">
                     Apply Adjustment
                  </button>
               </div>
            </div>

         </div>
      )}
    </div>
  );
};

export default WasteReports;
