import React, { useState, useMemo } from 'react';
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, BarChart, Bar, Legend } from 'recharts';
import { Calendar } from 'lucide-react';

const Analytics: React.FC = () => {
  const [dateRange, setDateRange] = useState({ start: '2023-10-23', end: '2023-10-29' });

  const storageData = [
    { name: 'HQ', used: 3500, max: 5000 },
    { name: 'Uptown', used: 420, max: 500 },
    { name: 'Lakeside', used: 800, max: 1200 },
  ];

  const demandData = useMemo(() => {
    const data = [];
    const start = new Date(dateRange.start);
    const end = new Date(dateRange.end);
    
    if (isNaN(start.getTime()) || isNaN(end.getTime()) || start > end) return [];

    const current = new Date(start);
    let iterations = 0;
    
    while (current <= end && iterations < 60) {
         const dayName = current.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
         const seed = current.getTime();
         const randomBase = Math.sin(seed) * 10000; 
         const noise = (randomBase - Math.floor(randomBase)); 
         const baseDemand = 300 + (noise * 500); 
         const dayOfWeek = current.getDay();
         const weekendMultiplier = (dayOfWeek === 0 || dayOfWeek === 6) ? 1.4 : 1.0;
         const finalDemand = Math.floor(baseDemand * weekendMultiplier);
         const finalForecast = Math.floor(finalDemand * (0.9 + (noise * 0.2))); 

         data.push({
             name: dayName,
             demand: finalDemand,
             forecast: finalForecast
         });
         current.setDate(current.getDate() + 1);
         iterations++;
    }
    return data;
  }, [dateRange]);

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex flex-col sm:flex-row justify-between items-end mb-2 gap-4">
         <div>
            <h2 className="text-2xl font-bold text-stone-900">Analytics</h2>
            <p className="text-stone-500">Performance metrics and forecasting</p>
         </div>
          <div className="flex items-center gap-2 px-3 py-2 bg-white border border-stone-200 rounded-lg text-sm shadow-sm">
            <Calendar size={16} className="text-stone-400 flex-shrink-0" />
            <input 
              type="date" 
              className="bg-transparent focus:outline-none text-stone-600 font-medium"
              value={dateRange.start}
              onChange={(e) => setDateRange(prev => ({...prev, start: e.target.value}))}
            />
            <span className="text-stone-400 mx-1">to</span>
            <input 
              type="date" 
              className="bg-transparent focus:outline-none text-stone-600 font-medium"
              value={dateRange.end}
              onChange={(e) => setDateRange(prev => ({...prev, end: e.target.value}))}
            />
          </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        {/* Demand Chart */}
        <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm h-96">
          <div className="mb-6">
             <h3 className="font-bold text-stone-900">Demand vs Forecast</h3>
             <p className="text-xs text-stone-500 mt-1">AI prediction model accuracy: 94%</p>
          </div>
          
          <ResponsiveContainer width="100%" height="80%">
            {demandData.length > 0 ? (
              <AreaChart data={demandData}>
                <defs>
                  <linearGradient id="colorDemand" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#d97706" stopOpacity={0.8}/>
                    <stop offset="95%" stopColor="#d97706" stopOpacity={0}/>
                  </linearGradient>
                  <linearGradient id="colorForecast" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#78716c" stopOpacity={0.8}/>
                    <stop offset="95%" stopColor="#78716c" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <XAxis dataKey="name" stroke="#a8a29e" fontSize={10} tickLine={false} axisLine={false} tickMargin={10} minTickGap={30} />
                <YAxis stroke="#a8a29e" fontSize={12} tickLine={false} axisLine={false} />
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e7e5e4" />
                <Tooltip 
                  contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' }}
                  labelStyle={{ color: '#78716c', marginBottom: '0.5rem', fontSize: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}
                />
                <Area type="monotone" dataKey="demand" stroke="#d97706" fillOpacity={1} fill="url(#colorDemand)" strokeWidth={3} name="Actual Demand" />
                <Area type="monotone" dataKey="forecast" stroke="#78716c" fillOpacity={1} fill="url(#colorForecast)" strokeDasharray="5 5" name="AI Forecast" />
              </AreaChart>
            ) : (
              <div className="flex items-center justify-center h-full text-stone-400 text-sm">
                No data for selected range
              </div>
            )}
          </ResponsiveContainer>
        </div>

        {/* Storage Chart */}
        <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-sm h-96">
          <div className="mb-6">
             <h3 className="font-bold text-stone-900">Storage Utilization</h3>
             <p className="text-xs text-stone-500 mt-1">Capacity usage per location</p>
          </div>
           <ResponsiveContainer width="100%" height="80%">
            <BarChart data={storageData} layout="vertical" margin={{ left: 20 }}>
              <XAxis type="number" hide />
              <YAxis dataKey="name" type="category" stroke="#57534e" fontSize={14} fontWeight={500} tickLine={false} axisLine={false} width={80}/>
              <Tooltip cursor={{fill: 'transparent'}} contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }} />
              <Legend verticalAlign="bottom" height={36} iconType="circle"/>
              <Bar dataKey="used" fill="#44403c" radius={[0, 4, 4, 0]} barSize={24} name="Used Capacity" stackId="a" />
              <Bar dataKey="max" fill="#e7e5e4" radius={[0, 4, 4, 0]} barSize={24} name="Total Capacity" stackId="b" />
            </BarChart>
          </ResponsiveContainer>
        </div>

      </div>

      <div className="bg-amber-50 p-6 rounded-2xl border border-amber-100 flex gap-4 items-start">
        <div className="p-2 bg-amber-100 rounded-lg text-amber-700 mt-1">
             <Calendar size={20} />
        </div>
        <div>
            <h4 className="font-bold text-amber-900 mb-1">Weekly Insight</h4>
            <p className="text-amber-800 text-sm leading-relaxed">
            Demand at the <span className="font-bold border-b border-amber-300">Uptown Kiosk</span> is outpacing storage capacity by 15% on weekends. Consider increasing delivery frequency to daily for milk and cups to maintain lower stock levels, or renting additional off-site storage for dry goods.
            </p>
        </div>
      </div>
    </div>
  );
};

export default Analytics;