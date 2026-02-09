import { TrendingUp } from 'lucide-react';
import {
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ForecastRow } from '@/types';

interface DemandForecastChartProps {
    forecast: ForecastRow[];
    currentDay: number;
}

const RISK_COLORS: Record<string, string> = {
    low: '#10b981',
    medium: '#f59e0b',
    stockout: '#ef4444',
};

export default function DemandForecastChart({
    forecast,
    currentDay,
}: DemandForecastChartProps) {
    if (!forecast || forecast.length === 0) {
        return null;
    }

    const chartData = forecast.map((row) => ({
        name: `Day ${currentDay + row.day_offset}`,
        stock: row.predicted_stock,
        demand: row.predicted_demand,
        deliveries: row.incoming_deliveries,
        risk: row.risk_level,
        fill: RISK_COLORS[row.risk_level] || RISK_COLORS.low,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <TrendingUp className="h-5 w-5 text-blue-500" />
                    7-Day Demand Forecast
                </CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                    <ComposedChart
                        data={chartData}
                        margin={{ top: 5, right: 20, bottom: 5, left: 0 }}
                    >
                        <CartesianGrid
                            strokeDasharray="3 3"
                            className="opacity-30"
                        />
                        <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                        <YAxis tick={{ fontSize: 12 }} />
                        <Tooltip
                            content={({ active, payload, label }) => {
                                if (!active || !payload?.length) return null;
                                const data = payload[0]?.payload as
                                    | (typeof chartData)[0]
                                    | undefined;
                                return (
                                    <div className="rounded-lg border border-stone-200 bg-white p-3 shadow-lg dark:border-stone-700 dark:bg-stone-800">
                                        <p className="font-medium">{label}</p>
                                        <p className="text-sm text-blue-600">
                                            Stock: {data?.stock ?? 0} units
                                        </p>
                                        <p className="text-sm text-amber-600">
                                            Demand: {data?.demand ?? 0} units
                                        </p>
                                        {(data?.deliveries ?? 0) > 0 && (
                                            <p className="text-sm text-emerald-600">
                                                Incoming: +{data?.deliveries}{' '}
                                                units
                                            </p>
                                        )}
                                        <p
                                            className="mt-1 text-xs font-medium uppercase"
                                            style={{
                                                color: RISK_COLORS[
                                                    data?.risk ?? 'low'
                                                ],
                                            }}
                                        >
                                            {data?.risk} risk
                                        </p>
                                    </div>
                                );
                            }}
                        />
                        <Legend />
                        <ReferenceLine
                            y={0}
                            stroke="#ef4444"
                            strokeDasharray="5 5"
                            label={{
                                value: 'Stockout',
                                position: 'right',
                                fill: '#ef4444',
                                fontSize: 11,
                            }}
                        />
                        <Bar
                            dataKey="deliveries"
                            name="Incoming Deliveries"
                            fill="#10b981"
                            opacity={0.7}
                            barSize={20}
                        />
                        <Line
                            type="monotone"
                            dataKey="stock"
                            name="Projected Stock"
                            stroke="#3b82f6"
                            strokeWidth={2}
                            dot={{ r: 4 }}
                        />
                        <Line
                            type="monotone"
                            dataKey="demand"
                            name="Predicted Demand"
                            stroke="#f59e0b"
                            strokeWidth={2}
                            strokeDasharray="5 5"
                            dot={{ r: 3 }}
                        />
                    </ComposedChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
