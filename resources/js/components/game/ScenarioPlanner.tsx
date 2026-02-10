import { AlertTriangle, Calculator, CheckCircle2, Clock3 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { calculateScenarioPlan } from '@/services/scenarioPlanner';

interface ScenarioPlannerProps {
    compact?: boolean;
    title?: string;
    initialValues?: Partial<{
        currentStock: number;
        dailyDemand: number;
        leadTimeDays: number;
        reorderPoint: number;
        targetCoverageDays: number;
    }>;
}

export default function ScenarioPlanner({
    compact = false,
    title = compact ? 'Mini Scenario Planner' : 'What-If Scenario Planner',
    initialValues,
}: ScenarioPlannerProps) {
    const [currentStock, setCurrentStock] = useState(
        initialValues?.currentStock ?? 40,
    );
    const [dailyDemand, setDailyDemand] = useState(
        initialValues?.dailyDemand ?? 8,
    );
    const [leadTimeDays, setLeadTimeDays] = useState(
        initialValues?.leadTimeDays ?? 3,
    );
    const [reorderPoint, setReorderPoint] = useState(
        initialValues?.reorderPoint ?? 12,
    );
    const [targetCoverageDays, setTargetCoverageDays] = useState(
        initialValues?.targetCoverageDays ?? 10,
    );

    const plan = useMemo(
        () =>
            calculateScenarioPlan({
                currentStock,
                dailyDemand,
                leadTimeDays,
                reorderPoint,
                targetCoverageDays,
            }),
        [
            currentStock,
            dailyDemand,
            leadTimeDays,
            reorderPoint,
            targetCoverageDays,
        ],
    );

    return (
        <Card>
            <CardHeader className={compact ? 'pb-3' : undefined}>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Calculator className="h-4 w-4 text-amber-600" />
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent className={compact ? 'space-y-3' : 'space-y-4'}>
                <div
                    className={`grid gap-3 ${compact ? 'grid-cols-2' : 'grid-cols-1 md:grid-cols-5'}`}
                >
                    <div className="space-y-1">
                        <Label className="text-xs">Current Stock</Label>
                        <Input
                            type="number"
                            min={0}
                            value={currentStock}
                            onChange={(e) =>
                                setCurrentStock(
                                    Number.parseInt(e.target.value, 10) || 0,
                                )
                            }
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Daily Demand</Label>
                        <Input
                            type="number"
                            min={1}
                            value={dailyDemand}
                            onChange={(e) =>
                                setDailyDemand(
                                    Number.parseInt(e.target.value, 10) || 1,
                                )
                            }
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Lead Time (days)</Label>
                        <Input
                            type="number"
                            min={1}
                            value={leadTimeDays}
                            onChange={(e) =>
                                setLeadTimeDays(
                                    Number.parseInt(e.target.value, 10) || 1,
                                )
                            }
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Reorder Point</Label>
                        <Input
                            type="number"
                            min={0}
                            value={reorderPoint}
                            onChange={(e) =>
                                setReorderPoint(
                                    Number.parseInt(e.target.value, 10) || 0,
                                )
                            }
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Coverage (days)</Label>
                        <Input
                            type="number"
                            min={1}
                            value={targetCoverageDays}
                            onChange={(e) =>
                                setTargetCoverageDays(
                                    Number.parseInt(e.target.value, 10) || 1,
                                )
                            }
                        />
                    </div>
                </div>

                <div
                    className={`grid gap-3 ${compact ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-3'}`}
                >
                    <div className="rounded-lg border border-stone-200 p-3 dark:border-stone-700">
                        <p className="text-xs text-stone-500">
                            Stockout Horizon
                        </p>
                        <p className="mt-1 flex items-center gap-2 text-sm font-semibold">
                            <Clock3 className="h-4 w-4 text-blue-500" />
                            {plan.timeToStockoutDays === null
                                ? 'No stockout in horizon'
                                : `Day ${plan.timeToStockoutDays}`}
                        </p>
                    </div>

                    <div className="rounded-lg border border-stone-200 p-3 dark:border-stone-700">
                        <p className="text-xs text-stone-500">
                            Recommended Reorder
                        </p>
                        <p className="mt-1 flex items-center gap-2 text-sm font-semibold">
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                            {plan.recommendedOrderQuantity.toLocaleString()}{' '}
                            units
                        </p>
                    </div>

                    <div className="rounded-lg border border-stone-200 p-3 dark:border-stone-700">
                        <p className="text-xs text-stone-500">Action</p>
                        <div className="mt-1 flex items-center gap-2">
                            {plan.shouldReorderNow ? (
                                <Badge className="bg-rose-600 text-white">
                                    <AlertTriangle className="mr-1 h-3 w-3" />
                                    Reorder now
                                </Badge>
                            ) : (
                                <Badge className="bg-emerald-600 text-white">
                                    Stable for now
                                </Badge>
                            )}
                            {plan.reorderByDay !== null && (
                                <span className="text-xs text-stone-500">
                                    Order by D+{plan.reorderByDay}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
