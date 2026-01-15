import React, { useState, useMemo } from 'react';
import { useApp } from '../App';
import { generateTransferSuggestions } from '../services/transferService';
import { 
  ArrowRightLeft, 
  Search, 
  MapPin, 
  Truck, 
  CheckCircle2, 
  Clock, 
  MoreHorizontal, 
  Plus,
  ArrowRight,
  TrendingDown,
  AlertCircle,
  X,
  Package
} from 'lucide-react';
import { Transfer, TransferSuggestion } from '../types';
import ProductIcon from './ProductIcon';

const Transfers: React.FC = () => {
  const { inventory, items, locations, transfers, createTransfer, updateTransferStatus } = useApp();
  const [activeTab, setActiveTab] = useState<'active' | 'suggestions' | 'history'>('active');
  const [isModalOpen, setIsModalOpen] = useState(false);
  
  // Modal State
  const [sourceId, setSourceId] = useState('');
  const [targetId, setTargetId] = useState('');
  const [selectedSku, setSelectedSku] = useState('');
  const [qty, setQty] = useState(0);

  // Derived Data
  const suggestions = useMemo(() => 
    generateTransferSuggestions(inventory, items, locations), 
  [inventory, items, locations]);

  const activeTransfers = transfers.filter(t => t.status !== 'COMPLETED' && t.status !== 'CANCELLED');
  const historyTransfers = transfers.filter(t => t.status === 'COMPLETED' || t.status === 'CANCELLED');

  // Matrix View Helper
  const getStockMatrix = (skuId: string) => {
    return locations.map(loc => {
       const rec = inventory.find(r => r.locationId === loc.id && r.itemId === skuId);
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
    <div className="space-y-6 pb-20 animate-in fade-in duration-500">
      
      {/* Header */}
      <div className="flex justify-between items-end">
         <div>
            <h2 className="text-2xl font-bold text-stone-900">Stock Transfers</h2>
            <p className="text-stone-500">Balance inventory levels between locations.</p>
         </div>
         <button 
           onClick={() => handleOpenModal()}
           className="bg-stone-900 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-stone-800 flex items-center gap-2 shadow-sm"
         >
            <Plus size={16} /> New Transfer
         </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-stone-100 p-1 rounded-xl w-fit">
         <button 
            onClick={() => setActiveTab('active')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${activeTab === 'active' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'}`}
         >
            In Transit ({activeTransfers.length})
         </button>
         <button 
            onClick={() => setActiveTab('suggestions')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 ${activeTab === 'suggestions' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'}`}
         >
            Suggestions 
            {suggestions.length > 0 && <span className="bg-amber-100 text-amber-700 text-xs px-1.5 py-0.5 rounded-full">{suggestions.length}</span>}
         </button>
         <button 
            onClick={() => setActiveTab('history')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${activeTab === 'history' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'}`}
         >
            History
         </button>
      </div>

      {/* Content */}
      <div className="min-h-[400px]">
         
         {activeTab === 'active' && (
            <div className="space-y-4">
               {activeTransfers.length === 0 ? (
                  <div className="text-center py-16 text-stone-400 bg-white rounded-2xl border border-stone-200 border-dashed">
                     <Truck size={48} className="mx-auto mb-4 opacity-20" />
                     <p>No active transfers.</p>
                  </div>
               ) : (
                  activeTransfers.map(t => {
                     const source = locations.find(l => l.id === t.sourceLocationId);
                     const target = locations.find(l => l.id === t.targetLocationId);
                     const itemCount = t.items.reduce((acc, i) => acc + i.qty, 0);

                     return (
                        <div key={t.id} className="bg-white border border-stone-200 rounded-xl p-6 shadow-sm hover:border-amber-200 transition-colors">
                           <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                              
                              <div className="flex items-center gap-4">
                                 <div className="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                                    <Truck size={24} />
                                 </div>
                                 <div>
                                    <div className="flex items-center gap-2 text-sm font-bold text-stone-900">
                                       <span>{source?.name}</span>
                                       <ArrowRight size={14} className="text-stone-400" />
                                       <span>{target?.name}</span>
                                    </div>
                                    <div className="text-xs text-stone-500 mt-1 flex items-center gap-2">
                                       <span className="font-mono bg-stone-100 px-1 rounded">#{t.id}</span>
                                       <span>•</span>
                                       <span>{itemCount} units</span>
                                       <span>•</span>
                                       <span className="text-blue-600 font-medium">ETA: {new Date(t.estimatedArrival).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                    </div>
                                 </div>
                              </div>

                              <div className="flex items-center gap-3 w-full md:w-auto">
                                 <button className="px-3 py-2 border border-stone-200 rounded-lg text-xs font-medium hover:bg-stone-50 text-stone-600">
                                    View Manifest
                                 </button>
                                 <button 
                                    onClick={() => updateTransferStatus(t.id, 'COMPLETED')}
                                    className="px-3 py-2 bg-stone-900 text-white rounded-lg text-xs font-bold hover:bg-stone-800 flex items-center gap-2"
                                 >
                                    <CheckCircle2 size={14} /> Receive Stock
                                 </button>
                              </div>
                           </div>
                           
                           {/* Item Preview */}
                           <div className="mt-4 pt-4 border-t border-stone-100 grid grid-cols-1 sm:grid-cols-2 gap-2">
                              {t.items.map((line, idx) => {
                                 const item = items.find(i => i.id === line.skuId);
                                 return (
                                    <div key={idx} className="flex items-center gap-2 text-sm">
                                       <div className="w-8 h-8 rounded bg-stone-100 flex items-center justify-center">
                                         {item && <ProductIcon category={item.category} className="w-6 h-6" />}
                                       </div>
                                       <div>
                                          <div className="font-medium text-stone-900">{item?.name}</div>
                                          <div className="text-xs text-stone-500">{line.qty} {item?.unit}</div>
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
             <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {suggestions.length === 0 ? (
                   <div className="col-span-full text-center py-16 text-stone-400 bg-white rounded-2xl border border-stone-200 border-dashed">
                      <CheckCircle2 size={48} className="mx-auto mb-4 opacity-20" />
                      <p>Inventory is balanced. No transfers recommended.</p>
                   </div>
                ) : (
                   suggestions.map(s => {
                      const item = items.find(i => i.id === s.skuId);
                      const source = locations.find(l => l.id === s.sourceLocationId);
                      const target = locations.find(l => l.id === s.targetLocationId);

                      return (
                         <div key={s.id} className="bg-white border border-stone-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
                            <div className="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-emerald-50 to-transparent -mr-10 -mt-10 rounded-full"></div>
                            
                            <div className="flex justify-between items-start mb-4 relative z-10">
                               <div className="flex items-center gap-3">
                                  <div className="p-2 bg-emerald-100 text-emerald-700 rounded-lg">
                                     <ArrowRightLeft size={20} />
                                  </div>
                                  <div>
                                     <h3 className="font-bold text-stone-900 text-sm">{s.reason}</h3>
                                     <p className="text-xs text-emerald-600 font-medium">Save ${s.savings} • {s.timeSavedDays} days faster</p>
                                     <p className="text-[10px] text-stone-400 mt-0.5">
                                        Est. Cost: ${s.transferCost.toFixed(2)} (${s.transferCostBreakdown.fixed} Fix + ${s.transferCostBreakdown.handling.toFixed(2)} Var)
                                     </p>
                                  </div>
                               </div>
                            </div>

                            <div className="bg-stone-50 rounded-lg p-3 border border-stone-100 mb-4 text-sm relative z-10">
                               <div className="flex items-center justify-between mb-2">
                                  <span className="text-stone-500 text-xs uppercase font-bold">Transfer</span>
                                  <span className="font-bold text-stone-900">{s.qty} x {item?.name}</span>
                                </div>
                               <div className="flex items-center gap-2 text-xs text-stone-600">
                                  <span className="font-semibold">{source?.name}</span>
                                  <ArrowRight size={12} className="text-stone-400"/>
                                  <span className="font-semibold">{target?.name}</span>
                               </div>
                            </div>

                            <button 
                               onClick={() => handleOpenModal(s)}
                               className="w-full py-2 bg-stone-900 text-white rounded-lg text-xs font-bold hover:bg-stone-800 transition-colors"
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
             <div className="bg-white rounded-xl border border-stone-200 overflow-hidden">
                <table className="w-full text-left text-sm">
                   <thead className="bg-stone-50 border-b border-stone-200 text-stone-500 uppercase text-xs">
                      <tr>
                         <th className="px-6 py-3 font-semibold">ID</th>
                         <th className="px-6 py-3 font-semibold">Route</th>
                         <th className="px-6 py-3 font-semibold">Date</th>
                         <th className="px-6 py-3 font-semibold">Status</th>
                      </tr>
                   </thead>
                   <tbody className="divide-y divide-stone-100">
                      {historyTransfers.map(t => {
                         const source = locations.find(l => l.id === t.sourceLocationId);
                         const target = locations.find(l => l.id === t.targetLocationId);
                         return (
                            <tr key={t.id} className="hover:bg-stone-50">
                               <td className="px-6 py-4 font-mono text-stone-400">{t.id}</td>
                               <td className="px-6 py-4 font-medium text-stone-900">{source?.name} → {target?.name}</td>
                               <td className="px-6 py-4 text-stone-500">{new Date(t.createdDate).toLocaleDateString()}</td>
                               <td className="px-6 py-4">
                                  <span className={`px-2 py-1 rounded text-xs font-bold ${
                                     t.status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600'
                                  }`}>
                                     {t.status}
                                  </span>
                               </td>
                            </tr>
                         );
                      })}
                      {historyTransfers.length === 0 && (
                         <tr><td colSpan={4} className="p-6 text-center text-stone-400">No transfer history.</td></tr>
                      )}
                   </tbody>
                </table>
             </div>
         )}
      </div>

      {/* Creation Modal */}
      {isModalOpen && (
         <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
               <div className="p-5 border-b border-stone-100 flex justify-between items-center bg-stone-50">
                  <h3 className="font-bold text-stone-900">Create Stock Transfer</h3>
                  <button onClick={() => setIsModalOpen(false)} className="p-2 hover:bg-stone-200 rounded-full text-stone-500">
                     <X size={20} />
                  </button>
               </div>
               
               <div className="p-6 space-y-6">
                  <div className="grid grid-cols-2 gap-4">
                     <div>
                        <label className="text-xs font-bold text-stone-500 uppercase mb-1 block">From (Source)</label>
                        <select 
                           value={sourceId}
                           onChange={(e) => setSourceId(e.target.value)}
                           className="w-full p-2 border border-stone-200 rounded-lg text-sm font-medium"
                        >
                           {locations.map(l => <option key={l.id} value={l.id} disabled={l.id === targetId}>{l.name}</option>)}
                        </select>
                     </div>
                     <div>
                        <label className="text-xs font-bold text-stone-500 uppercase mb-1 block">To (Destination)</label>
                        <select 
                           value={targetId}
                           onChange={(e) => setTargetId(e.target.value)}
                           className="w-full p-2 border border-stone-200 rounded-lg text-sm font-medium"
                        >
                           {locations.map(l => <option key={l.id} value={l.id} disabled={l.id === sourceId}>{l.name}</option>)}
                        </select>
                     </div>
                  </div>

                  <div className="grid grid-cols-3 gap-4">
                     <div className="col-span-2">
                        <label className="text-xs font-bold text-stone-500 uppercase mb-1 block">Item</label>
                        <select 
                           value={selectedSku}
                           onChange={(e) => setSelectedSku(e.target.value)}
                           className="w-full p-2 border border-stone-200 rounded-lg text-sm font-medium"
                        >
                           {items.map(i => <option key={i.id} value={i.id}>{i.name}</option>)}
                        </select>
                     </div>
                     <div>
                        <label className="text-xs font-bold text-stone-500 uppercase mb-1 block">Quantity</label>
                        <input 
                           type="number"
                           min="1"
                           value={qty}
                           onChange={(e) => setQty(parseInt(e.target.value))}
                           className="w-full p-2 border border-stone-200 rounded-lg text-sm font-medium"
                        />
                     </div>
                  </div>

                  {/* Stock Availability Matrix */}
                  <div className="bg-stone-50 rounded-xl p-4 border border-stone-100">
                     <h4 className="text-xs font-bold text-stone-500 uppercase mb-3 flex items-center gap-2">
                        <Package size={12} /> Stock Availability Check
                     </h4>
                     <div className="space-y-2">
                        {getStockMatrix(selectedSku).map(({ location, qty: stock }) => {
                           const isSource = location.id === sourceId;
                           const isTarget = location.id === targetId;
                           let statusColor = 'bg-stone-200';
                           if (isSource) statusColor = stock >= qty ? 'bg-emerald-500' : 'bg-rose-500';
                           if (isTarget) statusColor = 'bg-blue-500';

                           return (
                              <div key={location.id} className="flex items-center justify-between text-sm">
                                 <span className={`flex items-center gap-2 ${isSource || isTarget ? 'font-bold text-stone-900' : 'text-stone-500'}`}>
                                    <div className={`w-2 h-2 rounded-full ${statusColor}`}></div>
                                    {location.name} {isSource && '(Source)'} {isTarget && '(Target)'}
                                 </span>
                                 <span className="font-mono">{stock} units</span>
                              </div>
                           );
                        })}
                     </div>
                     {getStockMatrix(selectedSku).find(m => m.location.id === sourceId)?.qty! < qty && (
                        <div className="mt-3 text-xs text-rose-600 font-bold flex items-center gap-1">
                           <AlertCircle size={12} /> Source location has insufficient stock.
                        </div>
                     )}
                  </div>
               </div>

               <div className="p-4 bg-stone-50 border-t border-stone-100 flex justify-end gap-3">
                  <button 
                     onClick={() => setIsModalOpen(false)}
                     className="px-4 py-2 text-stone-600 font-bold text-sm hover:bg-stone-200 rounded-lg transition-colors"
                  >
                     Cancel
                  </button>
                  <button 
                     onClick={handleSubmitTransfer}
                     disabled={qty <= 0 || getStockMatrix(selectedSku).find(m => m.location.id === sourceId)?.qty! < qty}
                     className="px-4 py-2 bg-stone-900 text-white font-bold text-sm rounded-lg hover:bg-stone-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
