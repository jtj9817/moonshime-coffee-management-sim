import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 p-6 md:p-10 dark:from-stone-950 dark:via-stone-900 dark:to-amber-950">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/30">
                                <AppLogoIcon className="size-9 fill-current text-white" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="bg-gradient-to-r from-amber-600 via-orange-600 to-amber-700 bg-clip-text text-2xl font-bold text-transparent dark:from-amber-400 dark:via-orange-400 dark:to-amber-500">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-stone-600 dark:text-stone-400">
                                {description}
                            </p>
                        </div>
                    </div>
                    <div className="rounded-xl border border-stone-200 bg-white/80 p-6 backdrop-blur-sm dark:border-stone-700 dark:bg-stone-800/80">
                        {children}
                    </div>
                </div>
            </div>
            <footer className="absolute bottom-6 text-center text-xs text-stone-400 dark:text-stone-500">
                <p>A supply chain management learning experience</p>
            </footer>
        </div>
    );
}
