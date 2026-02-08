import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import Ordering from '@/pages/game/ordering';
import { renderPage } from '@/tests/support/render-page';

vi.mock('@/components/game/new-order-dialog', () => ({
    NewOrderDialog: () => null,
}));

vi.mock('@/components/game/cancel-order-dialog', () => ({
    CancelOrderDialog: () => null,
}));

describe('game ordering page', () => {
    it('renders scoped order totals using display formatting only', () => {
        renderPage(
            <Ordering
                orders={[
                    {
                        id: '12345678-order',
                        vendor_id: 'vendor-1',
                        status: 'pending',
                        total_cost: 45250,
                        delivery_date: null,
                        delivery_day: 6,
                        created_at: '2026-02-08T00:00:00Z',
                        vendor: {
                            id: 'vendor-1',
                            name: 'Northern Beans',
                            reliability_score: 91,
                            metrics: null,
                        },
                        items: [],
                    },
                ]}
                vendorProducts={[
                    {
                        vendor: {
                            id: 'vendor-1',
                            name: 'Northern Beans',
                            reliability_score: 91,
                        },
                        products: [],
                    },
                ]}
            />,
        );

        expect(screen.getByText('Northern Beans')).toBeInTheDocument();
        expect(screen.getByText('$452.50')).toBeInTheDocument();
        expect(screen.queryByText('45250')).not.toBeInTheDocument();
    });
});
