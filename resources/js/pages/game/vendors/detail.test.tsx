import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import VendorDetail from '@/pages/game/vendors/detail';
import { renderPage } from '@/tests/support/render-page';

describe('game vendor detail page', () => {
    it('renders user-scoped totals and order amounts using display formatting only', () => {
        renderPage(
            <VendorDetail
                vendor={{
                    id: 'vendor-1',
                    name: 'Northern Beans',
                    reliability_score: 88,
                    metrics: null,
                    products: [],
                    orders: [
                        {
                            id: '12345678-order',
                            vendor_id: 'vendor-1',
                            status: 'shipped',
                            total_cost: 12500,
                            delivery_date: null,
                            delivery_day: 4,
                            created_at: '2026-02-08T00:00:00Z',
                        },
                    ],
                }}
                metrics={{
                    totalOrders: 1,
                    totalSpent: 250000,
                    avgDeliveryTime: 3,
                    onTimeDeliveryRate: 92,
                }}
            />,
        );

        expect(screen.getByText('Northern Beans')).toBeInTheDocument();
        expect(screen.getByText('$2,500.00')).toBeInTheDocument();
        expect(screen.getByText('$125.00')).toBeInTheDocument();
        expect(screen.queryByText('250000')).not.toBeInTheDocument();
    });
});
