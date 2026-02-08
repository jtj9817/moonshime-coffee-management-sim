import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Analytics from '@/pages/game/analytics';
import { renderPage } from '@/tests/support/render-page';

describe('game analytics page', () => {
    it('renders scoped aggregate props and currency as formatted display values', () => {
        renderPage(
            <Analytics
                overviewMetrics={{
                    cash: 150000,
                    netWorth: 325000,
                    revenue7Day: 84500,
                }}
                inventoryTrends={[
                    { day: 1, value: 10 },
                    { day: 2, value: 15 },
                ]}
                spendingByCategory={[
                    { category: 'Coffee Beans', amount: 35000 },
                    { category: 'Dairy', amount: 10000 },
                ]}
                locationComparison={[
                    {
                        name: 'Downtown Cafe',
                        inventoryValue: 250000,
                        utilization: 62,
                        itemCount: 12,
                    },
                ]}
                storageUtilization={[]}
                fulfillmentMetrics={{
                    totalOrders: 5,
                    deliveredOrders: 4,
                    fulfillmentRate: 80,
                    averageDeliveryTime: 2.5,
                }}
                spikeImpactAnalysis={[]}
            />,
        );

        expect(screen.getByText('$1,500.00')).toBeInTheDocument();
        expect(screen.getByText('$3,250.00')).toBeInTheDocument();
        expect(screen.getByText('Downtown Cafe')).toBeInTheDocument();
        expect(screen.queryByText('150000')).not.toBeInTheDocument();
    });
});
