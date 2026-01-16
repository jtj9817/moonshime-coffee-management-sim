import { Head, Link, useForm } from '@inertiajs/react';
import { Coffee, Package, TrendingUp, Zap } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

interface WelcomeProps {
    isAuthenticated: boolean;
}

export default function Welcome({ isAuthenticated }: WelcomeProps) {
    const { post, processing } = useForm({});

    const handleEnter = () => {
        post('/acknowledge');
    };

    return (
        <>
            <Head title="Welcome to Moonshine Coffee">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 p-6 dark:from-stone-950 dark:via-stone-900 dark:to-amber-950">
                {/* Navigation */}
                {!isAuthenticated && (
                    <header className="absolute top-0 right-0 p-6">
                        <nav className="flex items-center gap-4">
                            <Link
                                href={login()}
                                className="rounded-lg px-4 py-2 text-sm font-medium text-stone-600 transition-colors hover:bg-white/50 hover:text-stone-900 dark:text-stone-300 dark:hover:bg-stone-800 dark:hover:text-white"
                            >
                                Log in
                            </Link>
                            <Link
                                href={register()}
                                className="rounded-lg border border-amber-600 bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 dark:border-amber-500 dark:bg-amber-500 dark:hover:bg-amber-600"
                            >
                                Register
                            </Link>
                        </nav>
                    </header>
                )}

                {/* Main Content */}
                <main className="flex max-w-4xl flex-col items-center text-center">
                    {/* Logo */}
                    <div className="mb-8 flex h-24 w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-2xl shadow-amber-500/30">
                        <Coffee className="h-12 w-12 text-white" />
                    </div>

                    {/* Title */}
                    <h1 className="mb-4 bg-gradient-to-r from-amber-600 via-orange-600 to-amber-700 bg-clip-text text-5xl font-bold tracking-tight text-transparent dark:from-amber-400 dark:via-orange-400 dark:to-amber-500">
                        Moonshine Coffee
                    </h1>
                    <p className="mb-2 text-xl font-medium text-stone-600 dark:text-stone-300">
                        Supply Chain Management Simulation
                    </p>
                    <p className="mb-12 max-w-2xl text-stone-500 dark:text-stone-400">
                        Master the art of coffee shop inventory management. Balance supply and demand,
                        negotiate with vendors, survive market spikes, and grow your coffee empire.
                    </p>

                    {/* Feature Cards */}
                    <div className="mb-12 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="rounded-xl border border-stone-200 bg-white/80 p-6 backdrop-blur-sm transition-transform hover:-translate-y-1 dark:border-stone-700 dark:bg-stone-800/80">
                            <Package className="mb-3 h-8 w-8 text-amber-600 dark:text-amber-400" />
                            <h3 className="mb-1 font-semibold text-stone-900 dark:text-white">
                                Manage Inventory
                            </h3>
                            <p className="text-sm text-stone-500 dark:text-stone-400">
                                Track stock across 3 locations. Optimize reorder points and safety stock.
                            </p>
                        </div>

                        <div className="rounded-xl border border-stone-200 bg-white/80 p-6 backdrop-blur-sm transition-transform hover:-translate-y-1 dark:border-stone-700 dark:bg-stone-800/80">
                            <TrendingUp className="mb-3 h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                            <h3 className="mb-1 font-semibold text-stone-900 dark:text-white">
                                Grow Revenue
                            </h3>
                            <p className="text-sm text-stone-500 dark:text-stone-400">
                                Negotiate with suppliers, reduce waste, and maximize profitability.
                            </p>
                        </div>

                        <div className="rounded-xl border border-stone-200 bg-white/80 p-6 backdrop-blur-sm transition-transform hover:-translate-y-1 dark:border-stone-700 dark:bg-stone-800/80">
                            <Zap className="mb-3 h-8 w-8 text-rose-600 dark:text-rose-400" />
                            <h3 className="mb-1 font-semibold text-stone-900 dark:text-white">
                                Survive Spikes
                            </h3>
                            <p className="text-sm text-stone-500 dark:text-stone-400">
                                React to demand surges, supply disruptions, and market chaos.
                            </p>
                        </div>
                    </div>

                    {/* Enter Button */}
                    <Button
                        onClick={handleEnter}
                        disabled={processing}
                        size="lg"
                        className="h-14 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-12 text-lg font-semibold text-white shadow-lg shadow-amber-500/30 transition-all hover:from-amber-600 hover:to-orange-700 hover:shadow-xl hover:shadow-amber-500/40 disabled:opacity-50"
                    >
                        {processing ? 'Loading...' : 'Enter Simulation'}
                    </Button>

                    {!isAuthenticated && (
                        <p className="mt-4 text-sm text-stone-500 dark:text-stone-400">
                            You'll need to log in or register to play
                        </p>
                    )}
                </main>

                {/* Footer */}
                <footer className="absolute bottom-6 text-center text-xs text-stone-400 dark:text-stone-500">
                    <p>A supply chain management learning experience</p>
                </footer>
            </div>
        </>
    );
}
