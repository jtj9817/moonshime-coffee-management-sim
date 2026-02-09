import { Link } from '@inertiajs/react';
import { Info, ShoppingCart, Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export function WelcomeBanner() {
    return (
        <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900/30 dark:bg-amber-900/10">
            <CardContent className="p-6">
                <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div className="flex gap-4">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                            <Sparkles className="h-6 w-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold text-amber-900 dark:text-amber-100">
                                Welcome, Manager! ☕
                            </h2>
                            <p className="max-w-2xl text-sm leading-relaxed text-amber-800/80 dark:text-amber-200/60">
                                Your journey to coffee management excellence
                                begins today. Keep your inventory stocked,
                                routes optimized, and customers happy.
                                <span className="mt-1 block font-medium italic">
                                    Hint: Your shelves are empty! Start by
                                    placing your first order.
                                </span>
                            </p>
                        </div>
                    </div>

                    <div className="flex shrink-0 flex-wrap gap-3">
                        <Button
                            variant="outline"
                            className="gap-2 border-amber-200 hover:bg-amber-100 dark:border-amber-800 dark:hover:bg-amber-900/30"
                            asChild
                        >
                            <Link href="/game/strategy">
                                <Info className="h-4 w-4" />
                                Strategy Guide
                            </Link>
                        </Button>
                        <Button
                            className="gap-2 bg-amber-600 text-white shadow-md shadow-amber-600/20 hover:bg-amber-700"
                            asChild
                        >
                            <Link href="/game/ordering">
                                <ShoppingCart className="h-4 w-4" />
                                Place First Order
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
