import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Layers, Settings, Zap } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import GameLayout from '@/layouts/game-layout';
import { type BreadcrumbItem } from '@/types';

interface PolicyOption {
    name: string;
    label: string;
    description: string;
}

interface StrategyProps {
    currentPolicy: {
        name: string;
        settings: Record<string, unknown>;
    };
    policyOptions: PolicyOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Strategy Deck', href: '/game/strategy' },
];

function PolicyCard({
    policy,
    isSelected,
    onSelect,
}: {
    policy: PolicyOption;
    isSelected: boolean;
    onSelect: () => void;
}) {
    return (
        <Card
            className={`cursor-pointer transition-all hover:-translate-y-1 hover:shadow-lg ${
                isSelected
                    ? 'border-2 border-amber-500 ring-2 ring-amber-500/20'
                    : 'border-stone-200 dark:border-stone-700'
            }`}
            onClick={onSelect}
        >
            <CardHeader>
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        {policy.name === 'just_in_time' ? (
                            <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-950">
                                <Zap className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                        ) : (
                            <div className="rounded-lg bg-emerald-100 p-2 dark:bg-emerald-950">
                                <Settings className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                        )}
                        <CardTitle className="text-lg">{policy.label}</CardTitle>
                    </div>
                    {isSelected && (
                        <CheckCircle2 className="h-5 w-5 text-amber-500" />
                    )}
                </div>
                <CardDescription>{policy.description}</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="space-y-2 text-sm">
                    {policy.name === 'just_in_time' ? (
                        <>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">Inventory Levels</span>
                                <span className="font-medium text-blue-600">Minimal</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">Order Frequency</span>
                                <span className="font-medium text-blue-600">High</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">Risk Level</span>
                                <span className="font-medium text-amber-600">Medium</span>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">Inventory Levels</span>
                                <span className="font-medium text-emerald-600">Buffer Stock</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">Order Frequency</span>
                                <span className="font-medium text-emerald-600">Moderate</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">Risk Level</span>
                                <span className="font-medium text-emerald-600">Low</span>
                            </div>
                        </>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Strategy({ currentPolicy, policyOptions }: StrategyProps) {
    const { data, setData, put, processing } = useForm({
        policy: currentPolicy.name,
    });

    const handlePolicyChange = (policyName: string) => {
        setData('policy', policyName);
    };

    const handleSave = () => {
        put('/game/policy');
    };

    const hasChanges = data.policy !== currentPolicy.name;

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="Strategy Deck" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                            Strategy Deck
                        </h1>
                        <p className="text-stone-500 dark:text-stone-400">
                            Configure your inventory management strategy
                        </p>
                    </div>
                    <Button
                        onClick={handleSave}
                        disabled={!hasChanges || processing}
                        className="gap-2 bg-amber-600 hover:bg-amber-700"
                    >
                        <Layers className="h-4 w-4" />
                        {processing ? 'Saving...' : 'Apply Strategy'}
                    </Button>
                </div>

                {/* Current Strategy */}
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                    <div className="flex items-center gap-2">
                        <CheckCircle2 className="h-5 w-5 text-amber-600" />
                        <span className="font-medium text-amber-800 dark:text-amber-300">
                            Current Strategy:{' '}
                            {policyOptions.find((p) => p.name === currentPolicy.name)?.label ??
                                'Unknown'}
                        </span>
                    </div>
                </div>

                {/* Policy Options */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {policyOptions.map((policy) => (
                        <PolicyCard
                            key={policy.name}
                            policy={policy}
                            isSelected={data.policy === policy.name}
                            onSelect={() => handlePolicyChange(policy.name)}
                        />
                    ))}
                </div>

                {/* Info Box */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">About Inventory Strategies</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-stone-600 dark:text-stone-400">
                        <p className="mb-3">
                            Your inventory strategy determines how the system calculates reorder
                            points and safety stock levels. Choose based on your risk tolerance and
                            cash flow preferences.
                        </p>
                        <ul className="list-inside list-disc space-y-1">
                            <li>
                                <strong>Just-In-Time:</strong> Lower holding costs but higher risk
                                during demand spikes
                            </li>
                            <li>
                                <strong>Safety Stock:</strong> Higher holding costs but better
                                protection against stockouts
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </GameLayout>
    );
}
