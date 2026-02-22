import { test, expect } from '@playwright/test';
import { login } from './login-helper';
import { resetDatabase } from './utils';

test.describe('Phase 3: Strategic Planning Tools', () => {
    test.beforeEach(async () => {
        await resetDatabase();
    });

    test('Scenario Calculator is accessible and interactive', async ({ page }) => {
        await login(page);

        await page.goto('/game/ordering');

        // Check for header
        await expect(page.getByText('What-If Scenario Planner')).toBeVisible();

        // Find Inputs
        // Use nth() to find inputs in order if getByLabel fails
        const inputs = page.locator('input[type="number"]');

        // Current Stock (index 0)
        const currentStock = inputs.nth(0);
        await expect(currentStock).toBeVisible();
        await currentStock.fill('20');
        await currentStock.blur();

        // Check output
        await expect(page.getByText('Stockout Horizon')).toBeVisible();
        // Check for calculated value
        await expect(page.getByText(/Day \d+/)).toBeVisible();
    });

    test('Bulk Order Scheduler allows scheduling recurring orders', async ({ page }) => {
        await login(page);

        await page.goto('/game/ordering');

        // New Order
        await page.getByRole('button', { name: 'New Order' }).click();

        // Select Vendor
        await page.locator('button', { hasText: 'Select vendor' }).click();
        await page.getByRole('option').first().click();

        // Select Store
        await page.locator('button', { hasText: 'Select store' }).click();
        await page.getByRole('option').first().click();

        // Select Departure
        await page.locator('button', { hasText: 'Select departure point' }).click();
        await page.getByRole('option').first().click();

        // Select Product
        await page.locator('button', { hasText: /Pick a product|Select vendor first/i }).click();
        await page.getByRole('option').first().click();

        // Quantity
        const qtyInput = page.getByLabel('Quantity');
        await qtyInput.fill('50');

        // Add Item
        await page.locator('button:has-text("+")').click();

        // Check Schedule
        await page.getByLabel('Schedule this order').check();

        // Set Interval
        const intervalInput = page.getByLabel(/Repeat every/i);
        await expect(intervalInput).toBeVisible();
        await intervalInput.fill('7');

        // Save
        await page.getByRole('button', { name: 'Save Schedule' }).click();

        // Verify
        await expect(page.getByText('Scheduled Orders')).toBeVisible();
        await expect(page.getByText('Every 7d')).toBeVisible();
    });
});
