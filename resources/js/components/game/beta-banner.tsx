import { AlertTriangle, X } from 'lucide-react';
import { useState } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const COOKIE_NAME = 'beta-banner-dismissed';
const COOKIE_EXPIRY_DAYS = 30;

function getCookie(name: string): string | null {
    const match = document.cookie.match(
        new RegExp('(^| )' + name + '=([^;]+)'),
    );
    return match ? match[2] : null;
}

function setCookie(name: string, value: string, days: number): void {
    const expires = new Date();
    expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
}

export function BetaBanner() {
    const [isVisible, setIsVisible] = useState(() => {
        // Initialize state from cookie to avoid setState in effect
        const dismissed = getCookie(COOKIE_NAME);
        return !dismissed;
    });

    const handleDismiss = () => {
        setCookie(COOKIE_NAME, 'true', COOKIE_EXPIRY_DAYS);
        setIsVisible(false);
    };

    if (!isVisible) return null;

    return (
        <div className="fixed top-0 right-0 left-0 z-50 px-4 py-3">
            <Alert className="border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/50">
                <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                <div className="flex-1">
                    <AlertTitle className="text-amber-900 dark:text-amber-100">
                        Beta Version
                    </AlertTitle>
                    <AlertDescription className="text-amber-800 dark:text-amber-200/80">
                        This website is in active development. Features are
                        unrefined and breaking changes may occur.
                    </AlertDescription>
                </div>
                <button
                    onClick={handleDismiss}
                    className="ml-4 rounded p-1 text-amber-600 hover:bg-amber-100 dark:text-amber-400 dark:hover:bg-amber-900/50"
                    aria-label="Dismiss beta banner"
                >
                    <X className="h-4 w-4" />
                </button>
            </Alert>
        </div>
    );
}
