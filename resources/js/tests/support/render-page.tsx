import { render } from '@testing-library/react';
import type { ReactElement } from 'react';

import { mergeMockPageProps } from '@/tests/support/mocks';

export const renderPage = (
    page: ReactElement,
    options: {
        pageProps?: Record<string, unknown>;
    } = {},
) => {
    if (options.pageProps) {
        mergeMockPageProps(options.pageProps);
    }

    return render(page);
};
