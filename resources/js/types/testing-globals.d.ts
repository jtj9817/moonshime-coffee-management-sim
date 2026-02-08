declare global {
    var route: ((name?: string, params?: Record<string, unknown>) => string) & {
        current: (name?: string) => boolean;
        has: (name: string) => boolean;
        params: Record<string, string>;
    };
}

export { };
