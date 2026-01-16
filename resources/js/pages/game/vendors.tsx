import { Head, Link } from '@inertiajs/react';
import { Star, TrendingUp, Users } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import GameLayout from '@/layouts/game-layout';
import { VendorModel, type BreadcrumbItem } from '@/types';

interface VendorsProps {
    vendors: Array<
        VendorModel & {
            orders_count?: number;
            orders_avg_total_cost?: number;
        }
    >;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Suppliers', href: '/game/vendors' },
];

function getReliabilityColor(score: number) {
    if (score >= 90) return 'text-emerald-600';
    if (score >= 70) return 'text-amber-600';
    return 'text-rose-600';
}

function getReliabilityBadge(score: number) {
    if (score >= 90) return <Badge className="bg-emerald-500">Excellent</Badge>;
    if (score >= 70) return <Badge className="bg-amber-500">Good</Badge>;
    return <Badge variant="destructive">Needs Improvement</Badge>;
}

export default function Vendors({ vendors }: VendorsProps) {
    const avgReliability =
        vendors.length > 0
            ? vendors.reduce((sum, v) => sum + v.reliability_score, 0) / vendors.length
            : 0;

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="Suppliers" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                        Supplier Network
                    </h1>
                    <p className="text-stone-500 dark:text-stone-400">
                        Manage relationships with your suppliers
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Active Suppliers
                            </CardTitle>
                            <Users className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{vendors.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Avg Reliability
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{avgReliability.toFixed(0)}%</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Top Rated
                            </CardTitle>
                            <Star className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {vendors.filter((v) => v.reliability_score >= 90).length}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Vendor Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {vendors.map((vendor) => (
                        <Card
                            key={vendor.id}
                            className="transition-all hover:-translate-y-1 hover:shadow-lg"
                        >
                            <CardHeader className="pb-2">
                                <div className="flex items-start justify-between">
                                    <CardTitle className="text-lg">{vendor.name}</CardTitle>
                                    {getReliabilityBadge(vendor.reliability_score)}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Reliability Score */}
                                <div>
                                    <div className="mb-1 flex items-center justify-between text-sm">
                                        <span className="text-stone-500">Reliability</span>
                                        <span
                                            className={`font-bold ${getReliabilityColor(vendor.reliability_score)}`}
                                        >
                                            {vendor.reliability_score}%
                                        </span>
                                    </div>
                                    <Progress value={vendor.reliability_score} className="h-2" />
                                </div>

                                {/* Stats */}
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-stone-500">Total Orders</p>
                                        <p className="font-bold">{vendor.orders_count ?? 0}</p>
                                    </div>
                                    <div>
                                        <p className="text-stone-500">Avg Order</p>
                                        <p className="font-bold">
                                            $
                                            {vendor.orders_avg_total_cost?.toLocaleString() ?? '0'}
                                        </p>
                                    </div>
                                </div>

                                {/* Action */}
                                <Link href={`/game/vendors/${vendor.id}`}>
                                    <Button variant="outline" className="w-full">
                                        View Details
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {vendors.length === 0 && (
                    <div className="rounded-xl border-2 border-dashed border-stone-200 p-12 text-center dark:border-stone-700">
                        <Users className="mx-auto mb-4 h-12 w-12 text-stone-400" />
                        <h3 className="text-lg font-medium text-stone-900 dark:text-white">
                            No suppliers yet
                        </h3>
                        <p className="mt-1 text-stone-500">
                            Suppliers will appear once you start the simulation
                        </p>
                    </div>
                )}
            </div>
        </GameLayout>
    );
}
