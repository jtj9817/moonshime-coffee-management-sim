
import React, { useState, useEffect, useMemo } from 'react';
import { useApp } from '../App';
import { calculatePolicyImpact } from '../services/policyService';
import { PolicyProfile, PolicyImpactAnalysis } from '../types';
import { 
  Sliders, ShieldCheck, DollarSign, Activity, AlertTriangle, Save, RotateCcw, 
  TrendingUp, TrendingDown, Layers, HelpCircle
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

const PolicyDeck: React.FC = () => {
  const { policies, updatePolicies, inventory, items, locations, gameState } = useApp();
  
  // Local state for "Draft" policies (sliders move this, not global state immediately)
  const [draftPolicy, setDraftPolicy] = useState<PolicyProfile>({ ...policies });
  const [impact, setImpact] = useState<PolicyImpactAnalysis | null>(null);
  const [isDirty, setIsDirty] = useState(false);

  // Sync draft if global policies change externally (unlikely but good practice)
  useEffect(() => {
    if (!isDirty) setDraftPolicy(policies);
  }, [policies, isDirty]);

  // Recalculate impact whenever draft changes
  useEffect(() => {
    const analysis = calculatePolicyImpact(policies, draftPolicy, inventory, items, locations);
    setImpact(analysis);
  }, [draftPolicy, policies, inventory, items, locations]);

  const handleChange = (field: keyof PolicyProfile, value: number) => {
    setDraftPolicy(prev => ({ ...prev, [field]: value }));
    setIsDirty(true);
  };

  const handleSave = () => {
    updatePolicies(draftPolicy);
    setIsDirty(false);
  };

  const handleReset = () => {
    setDraftPolicy(policies);
    setIsDirty(false);
  };

  // Mock data for Service Level vs Capital Curve
  const curveData = useMemo(() => {
     // Generate a curve to show non-linear cost growth
     const data = [];
     for (let sl = 80; sl <= 99; sl += 1) {
        const p = { ...draftPolicy, globalServiceLevel: sl / 100 };
        const res = calculatePolicyImpact(policies, p, inventory, items, locations);
        data.push({ sl, cost: res.capitalRequired });
     }
     return data;
  }, [draftPolicy, policies, inventory, items, locations]);

  return (
    <div className="space-y-6 pb-20 animate-in fade-in duration-500">
       
       <div className="flex flex-col md:flex-row justify-between items-end gap-4">
          <div>
             <h2 className="text-2xl font-bold text-stone-900 flex items-center gap-2">
                <Layers className="text-stone-400" /> Strategy Deck
             </h2>
             <p className="text-stone-500">Configure global supply chain parameters and risk tolerance.</p>
          </div>
          <div className="flex gap-3">
             <button 
                onClick={handleReset}
                disabled={!isDirty}
                className="px-4 py-2 text-stone-500 font-bold text-sm hover:bg-stone-100 rounded-lg transition-colors disabled:opacity-30"
             >
                <RotateCcw size={16} /> Reset
             </button>
             <button 
                onClick={handleSave}
                disabled={!isDirty}
                className="px-6 py-2 bg-stone-900 text-white font-bold text-sm rounded-lg hover:bg-stone-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-stone-900/10 flex items-center gap-2 transition-all"
             >
                <Save size={16} /> Apply Policy
             </button>
          </div>
       </div>

       <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Controls */}
          <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 space-y-8">
             <div>
                <h3 className="font-bold text-stone-900 flex items-center gap-2 mb-4">
                   <Sliders size={18} className="text-amber-500" /> Configuration
                </h3>
                
                {/* Service Level Slider */}
                <div className="space-y-3 mb-6">
                   <div className="flex justify-between items-center">
                      <label className="text-xs font-bold text-stone-500 uppercase flex items-center gap-1">
                         Target Service Level
                         <HelpCircle size={12} className="text-stone-300" title="Probability of NOT creating a stockout during lead time."/>
                      </label>
                      <span className="font-mono font-bold text-stone-900">{(draftPolicy.globalServiceLevel * 100).toFixed(1)}%</span>
                   </div>
                   <input 
                      type="range" min="0.80" max="0.999" step="0.001"
                      value={draftPolicy.globalServiceLevel}
                      onChange={(e) => handleChange('globalServiceLevel', parseFloat(e.target.value))}
                      className="w-full h-2 bg-stone-100 rounded-lg appearance-none cursor-pointer accent-amber-600"
                   />
                   <div className="flex justify-between text-[10px] text-stone-400 font-mono">
                      <span>80% (Lean)</span>
                      <span>99.9% (Safe)</span>
                   </div>
                </div>

                {/* Safety Stock Buffer */}
                <div className="space-y-3 mb-6">
                   <div className="flex justify-between items-center">
                      <label className="text-xs font-bold text-stone-500 uppercase">Safety Stock Buffer</label>
                      <span className="font-mono font-bold text-stone-900">+{Math.round(draftPolicy.safetyStockBufferPct * 100)}%</span>
                   </div>
                   <input 
                      type="range" min="0" max="0.5" step="0.01"
                      value={draftPolicy.safetyStockBufferPct}
                      onChange={(e) => handleChange('safetyStockBufferPct', parseFloat(e.target.value))}
                      className="w-full h-2 bg-stone-100 rounded-lg appearance-none cursor-pointer accent-stone-600"
                   />
                </div>

                {/* Holding Cost Rate */}
                <div className="space-y-3 mb-6">
                   <div className="flex justify-between items-center">
                      <label className="text-xs font-bold text-stone-500 uppercase">Annual Holding Cost</label>
                      <span className="font-mono font-bold text-stone-900">{Math.round(draftPolicy.holdingCostRate * 100)}%</span>
                   </div>
                   <input 
                      type="range" min="0.1" max="0.5" step="0.01"
                      value={draftPolicy.holdingCostRate}
                      onChange={(e) => handleChange('holdingCostRate', parseFloat(e.target.value))}
                      className="w-full h-2 bg-stone-100 rounded-lg appearance-none cursor-pointer accent-stone-600"
                   />
                </div>
                
                {/* Auto Transfer Threshold */}
                <div className="space-y-3">
                   <div className="flex justify-between items-center">
                      <label className="text-xs font-bold text-stone-500 uppercase">Transfer Trigger (vs ROP)</label>
                      <span className="font-mono font-bold text-stone-900">{Math.round(draftPolicy.autoTransferThreshold * 100)}%</span>
                   </div>
                   <input 
                      type="range" min="0.1" max="0.9" step="0.05"
                      value={draftPolicy.autoTransferThreshold}
                      onChange={(e) => handleChange('autoTransferThreshold', parseFloat(e.target.value))}
                      className="w-full h-2 bg-stone-100 rounded-lg appearance-none cursor-pointer accent-blue-600"
                   />
                </div>
             </div>

             <div className="bg-stone-50 rounded-xl p-4 border border-stone-100 text-xs text-stone-500 leading-relaxed">
                <p><strong>Note:</strong> Increasing Service Level exponentially increases the capital required for Safety Stock. Find the balance between reliability and cash flow.</p>
             </div>
          </div>

          {/* Visualization & Impact */}
          <div className="lg:col-span-2 space-y-6">
             
             {/* Impact Cards */}
             <div className="grid grid-cols-2 gap-4">
                <div className={`p-5 rounded-2xl border transition-colors ${
                   (impact?.deltaCapital || 0) > 0 ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-200'
                }`}>
                   <div className="flex justify-between items-start mb-2">
                      <div className="text-xs font-bold uppercase tracking-wider opacity-60">Capital Requirement</div>
                      <DollarSign size={16} className="opacity-50" />
                   </div>
                   <div className="text-2xl font-bold mb-1">
                      ${impact?.capitalRequired.toLocaleString(undefined, { maximumFractionDigits: 0 })}
                   </div>
                   <div className="text-xs font-medium flex items-center gap-1">
                      {(impact?.deltaCapital || 0) > 0 ? <TrendingUp size={12}/> : <TrendingDown size={12}/>}
                      {impact?.deltaCapital === 0 ? 'No Change' : (
                         <span>
                            {impact?.deltaCapital! > 0 ? '+' : ''}
                            ${impact?.deltaCapital.toLocaleString(undefined, { maximumFractionDigits: 0 })} vs current
                         </span>
                      )}
                   </div>
                </div>

                <div className={`p-5 rounded-2xl border transition-colors ${
                   (impact?.projectedStockoutRisk || 0) > 5 ? 'bg-white border-stone-200' : 'bg-emerald-50 border-emerald-200'
                }`}>
                   <div className="flex justify-between items-start mb-2">
                      <div className="text-xs font-bold uppercase tracking-wider opacity-60">Stockout Probability</div>
                      <ShieldCheck size={16} className="opacity-50" />
                   </div>
                   <div className="text-2xl font-bold mb-1">
                      {impact?.projectedStockoutRisk.toFixed(2)}%
                   </div>
                   <div className="text-xs text-stone-400">
                      Theoretical risk per cycle
                   </div>
                </div>
             </div>

             {/* Curve Chart */}
             <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 h-80">
                <h4 className="font-bold text-stone-900 mb-4">Service Level vs. Capital Investment Curve</h4>
                <ResponsiveContainer width="100%" height="90%">
                   <AreaChart data={curveData}>
                      <defs>
                         <linearGradient id="colorCost" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#d97706" stopOpacity={0.1}/>
                            <stop offset="95%" stopColor="#d97706" stopOpacity={0}/>
                         </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f5f5f4" />
                      <XAxis 
                        dataKey="sl" 
                        type="number" 
                        domain={[80, 99]} 
                        tickFormatter={(v) => `${v}%`} 
                        stroke="#a8a29e" fontSize={10} tickLine={false} axisLine={false}
                      />
                      <YAxis 
                         stroke="#a8a29e" fontSize={10} tickLine={false} axisLine={false} 
                         tickFormatter={(v) => `$${v/1000}k`}
                      />
                      <Tooltip 
                         contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '12px' }}
                         formatter={(v: number) => [`$${v.toLocaleString(undefined, {maximumFractionDigits:0})}`, 'Capital Needed']}
                         labelFormatter={(v) => `Service Level: ${v}%`}
                      />
                      <Area 
                         type="monotone" 
                         dataKey="cost" 
                         stroke="#d97706" 
                         strokeWidth={3} 
                         fill="url(#colorCost)" 
                         animationDuration={500}
                      />
                      {/* Active Dot */}
                      {/* We can't easily put a dot here via props without complex customized shape, but Recharts handles tooltips well */}
                   </AreaChart>
                </ResponsiveContainer>
             </div>

          </div>

       </div>
    </div>
  );
};

export default PolicyDeck;
