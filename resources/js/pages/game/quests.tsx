import { Head } from '@inertiajs/react';
import { CheckCircle2, Gift, Star, Target, Trophy } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import GameLayout from '@/layouts/game-layout';
import { formatCurrency } from '@/lib/formatCurrency';
import { type BreadcrumbItem, type QuestModel } from '@/types';

interface QuestsPageProps {
    quests: QuestModel[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Quests', href: '/game/quests' },
];

function QuestProgressBar({
    current,
    target,
}: {
    current: number;
    target: number;
}) {
    const percentage = Math.min(100, Math.round((current / target) * 100));

    return (
        <div className="w-full">
            <div className="mb-1 flex justify-between text-xs text-stone-500">
                <span>
                    {current} / {target}
                </span>
                <span>{percentage}%</span>
            </div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                <div
                    className="h-full rounded-full bg-amber-500 transition-all duration-500"
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}

function QuestCard({ quest }: { quest: QuestModel }) {
    const isCompleted = quest.isCompleted;

    return (
        <Card
            className={`transition-all ${
                isCompleted
                    ? 'border-emerald-500/30 bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-950/20 dark:to-stone-900'
                    : 'hover:border-amber-500/30'
            }`}
        >
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <div
                            className={`rounded-lg p-2 ${
                                isCompleted
                                    ? 'bg-emerald-100 dark:bg-emerald-900/50'
                                    : 'bg-amber-100 dark:bg-amber-900/50'
                            }`}
                        >
                            {isCompleted ? (
                                <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                            ) : (
                                <Target className="h-5 w-5 text-amber-500" />
                            )}
                        </div>
                        <div>
                            <CardTitle className="text-lg">
                                {quest.title}
                            </CardTitle>
                            {isCompleted && (
                                <Badge className="mt-1 bg-emerald-500 hover:bg-emerald-600">
                                    Completed
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>
                {quest.description && (
                    <CardDescription className="mt-2 text-stone-600 dark:text-stone-400">
                        {quest.description}
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                {!isCompleted && (
                    <QuestProgressBar
                        current={quest.currentValue}
                        target={quest.targetValue}
                    />
                )}

                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-1.5 text-sm">
                        <Gift className="h-4 w-4 text-amber-500" />
                        <span className="font-medium text-stone-700 dark:text-stone-300">
                            Rewards:
                        </span>
                    </div>
                    {quest.reward.xp > 0 && (
                        <div className="flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-700 dark:bg-purple-900/50 dark:text-purple-300">
                            <Star className="h-3 w-3" />
                            {quest.reward.xp} XP
                        </div>
                    )}
                    {quest.reward.cash !== undefined &&
                        quest.reward.cash > 0 && (
                            <div className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                                ${formatCurrency(quest.reward.cash * 100)}
                            </div>
                        )}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Quests({ quests }: QuestsPageProps) {
    const activeQuests = quests.filter((q) => !q.isCompleted);
    const completedQuests = quests.filter((q) => q.isCompleted);

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="Quests" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                            Quests
                        </h1>
                        <p className="text-stone-500 dark:text-stone-400">
                            Complete objectives to earn rewards and advance your
                            coffee empire
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="text-sm">
                            <Trophy className="mr-1 h-3.5 w-3.5" />
                            {completedQuests.length} / {quests.length} Complete
                        </Badge>
                    </div>
                </div>

                {activeQuests.length > 0 && (
                    <div>
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-stone-900 dark:text-white">
                            <Target className="h-5 w-5 text-amber-500" />
                            Active Quests ({activeQuests.length})
                        </h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {activeQuests.map((quest) => (
                                <QuestCard key={quest.id} quest={quest} />
                            ))}
                        </div>
                    </div>
                )}

                {completedQuests.length > 0 && (
                    <div>
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-stone-900 dark:text-white">
                            <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                            Completed ({completedQuests.length})
                        </h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {completedQuests.map((quest) => (
                                <QuestCard key={quest.id} quest={quest} />
                            ))}
                        </div>
                    </div>
                )}

                {quests.length === 0 && (
                    <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-stone-300 py-16 dark:border-stone-600">
                        <Trophy className="mb-3 h-12 w-12 text-stone-400" />
                        <h3 className="text-lg font-semibold text-stone-600 dark:text-stone-400">
                            No Quests Available
                        </h3>
                        <p className="text-sm text-stone-500">
                            Check back after advancing the day for new
                            objectives.
                        </p>
                    </div>
                )}
            </div>
        </GameLayout>
    );
}
