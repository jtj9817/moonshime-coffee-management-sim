import { test, expect } from '@playwright/test';
import { login } from './login-helper';
import { resetDatabase } from './utils';

test.describe('Phase 2: Core Engagement', () => {
    test.beforeEach(async () => {
        await resetDatabase();
    });

    test('Quest System tracks progress', async ({ page }) => {
        await login(page);

        await expect(page.getByText('Active Quests')).toBeVisible();

        // Go to Ordering
        await page.goto('/game/ordering');

        // New Order
        await page.getByRole('button', { name: 'New Order' }).click();

        // Fill Dialog
        // Use getByLabel where possible as NewOrderDialog uses Label components.
        // Vendor
        await page.locator('button', { hasText: 'Select vendor' }).click();
        await page.getByRole('option').first().click();

        // Destination Store
        await page.locator('button', { hasText: 'Select store' }).click();
        await page.getByRole('option').first().click();

        // Ship From
        await page.locator('button', { hasText: 'Select departure point' }).click();
        await page.getByRole('option').first().click();

        // Product - waits for options to load?
        await page.locator('button', { hasText: /Pick a product|Select vendor first/i }).click();
        await page.getByRole('option').first().click();

        // Quantity
        const qtyInput = page.getByLabel('Quantity');
        await qtyInput.fill('50');

        // Add Item (+)
        await page.locator('button:has-text("+")').click();

        // Submit
        await page.getByRole('button', { name: /Confirm Order/i }).click();

        // Verify Quest
        await page.goto('/game/dashboard');

        await expect(page.locator('.rounded-xl').filter({ hasText: 'Quest' })).toContainText(/Completed|100%/);
    });

    test('Active Spike Resolution options are available', async ({ page }) => {
        await login(page);

        let spikeActive = false;
        // Check initially
        if (await page.getByText(/Active Spike/i).count() > 0) {
            spikeActive = true;
        } else {
            // Advance up to 7 days
            for (let i = 1; i <= 7; i++) {
                const nextDayBtn = page.getByRole('button', { name: /Next Day/i });
                await nextDayBtn.click({ force: true });

                await expect(page.getByText(`Day ${i + 1}`)).toBeVisible();

                if (await page.getByText(/Active Spike/i).count() > 0) {
                    spikeActive = true;
                    break;
                }
            }
        }

        if (spikeActive) {
            await page.getByRole('link', { name: /View Details|Open War Room/i }).click();
            await expect(page).toHaveURL(/.*spike-history/);
            await expect(page.getByRole('button', { name: /Resolve|Mitigate|Expedite/i })).toBeVisible();
        } else {
             console.log('No spike occurred within 7 days');
             test.skip();
        }
    });
});
