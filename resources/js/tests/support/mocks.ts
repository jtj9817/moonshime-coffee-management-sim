import { vi } from 'vitest';

type GenericRecord = Record<string, unknown>;

const defaultPageProps = (): GenericRecord => ({
    auth: {
        user: {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            created_at: '',
            updated_at: '',
            email_verified_at: '',
        },
    },
    game: null,
});

let pagePropsState: GenericRecord = defaultPageProps();

export const inertiaRouterMock = {
    delete: vi.fn(),
    get: vi.fn(),
    patch: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    reload: vi.fn(),
    visit: vi.fn(),
};

let nextUseFormState: GenericRecord | null = null;

const buildDefaultUseFormState = (): GenericRecord => ({
    data: {},
    errors: {},
    post: vi.fn(),
    processing: false,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    reset: vi.fn(),
    setData: vi.fn(),
    clearErrors: vi.fn(),
    transform: vi.fn(),
});

const defaultGameContext = () => ({
    gameState: {
        cash: 1_000_000,
        day: 1,
        has_placed_first_order: false,
        level: 1,
        reputation: 75,
        strikes: 0,
        xp: 0,
    },
    locations: [] as Array<GenericRecord>,
    products: [] as Array<GenericRecord>,
    vendors: [] as Array<GenericRecord>,
    alerts: [] as Array<GenericRecord>,
    activeSpikes: [] as Array<GenericRecord>,
    currentSpike: null as GenericRecord | null,
    currentLocationId: 'all',
    setCurrentLocationId: vi.fn(),
    advanceDay: vi.fn(),
    refreshData: vi.fn(),
    markAlertRead: vi.fn(),
});

let gameContextState = defaultGameContext();

export const setMockPageProps = (props: GenericRecord): void => {
    pagePropsState = props;
};

export const mergeMockPageProps = (props: GenericRecord): void => {
    pagePropsState = {
        ...pagePropsState,
        ...props,
    };
};

export const getMockPage = (): {
    component: string;
    props: GenericRecord;
    url: string;
    version: null;
} => ({
    component: 'tests/component',
    props: pagePropsState,
    url: '/tests',
    version: null,
});

export const setNextUseFormState = (state: GenericRecord): void => {
    nextUseFormState = state;
};

export const buildUseFormState = (): GenericRecord => {
    if (nextUseFormState) {
        const state = nextUseFormState;
        nextUseFormState = null;
        return state;
    }

    return buildDefaultUseFormState();
};

export const setMockGameContext = (
    overrides: Partial<ReturnType<typeof defaultGameContext>>,
): void => {
    gameContextState = {
        ...gameContextState,
        ...overrides,
    };
};

export const getMockGameContext = (): ReturnType<typeof defaultGameContext> =>
    gameContextState;

export const resetTestMocks = (): void => {
    pagePropsState = defaultPageProps();
    nextUseFormState = null;
    gameContextState = defaultGameContext();
    vi.clearAllMocks();
};
