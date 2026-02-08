import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import React, { type ReactNode } from 'react';
import { afterEach, vi } from 'vitest';

import { buildUseFormState, getMockGameContext, getMockPage, inertiaRouterMock, resetTestMocks } from '@/tests/support/mocks';

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children?: ReactNode }) =>
        children ? <>{children}</> : null,
    Link: ({ children, href, ...props }: { children?: ReactNode; href?: unknown } & Record<string, unknown>) => (
        <a href={typeof href === 'string' ? href : '#'} {...props}>
            {children}
        </a>
    ),
    router: inertiaRouterMock,
    useForm: () => buildUseFormState(),
    usePage: () => getMockPage(),
}));

vi.mock('@/contexts/game-context', () => ({
    GameProvider: ({ children }: { children: ReactNode }) => <>{children}</>,
    useGame: () => getMockGameContext(),
    useOptionalGame: () => getMockGameContext(),
}));

vi.mock('@/layouts/game-layout', () => ({
    default: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

const routeShim = ((name?: string) =>
    name ? `/${name.replaceAll('.', '/')}` : '/') as typeof globalThis.route;
routeShim.current = () => false;
routeShim.has = () => true;
routeShim.params = {};
globalThis.route = routeShim;

if (!window.matchMedia) {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: vi.fn().mockImplementation((query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    });
}

if (!globalThis.ResizeObserver) {
    globalThis.ResizeObserver = class {
        observe(): void { }
        unobserve(): void { }
        disconnect(): void { }
    };
}

afterEach(() => {
    cleanup();
    resetTestMocks();
});
