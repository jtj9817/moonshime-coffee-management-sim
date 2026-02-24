import { test, expect } from '@playwright/test';
import { resetDatabase } from './utils';

test.describe('Authentication and Initialization', () => {
    test.beforeEach(async () => {
        await resetDatabase();
    });

    test('User can register and lands on dashboard with correct initial state', async ({ page }) => {
        await page.goto('/register');
        await page.getByLabel('Name').fill('Test Captain');
        await page.getByLabel('Email address').fill('captain@moonshine.com');
        await page.getByLabel('Password', { exact: true }).fill('password123');
        await page.getByLabel('Confirm password').fill('password123');
        await page.getByRole('button', { name: 'Create account' }).click();

        await expect(page).toHaveURL(/.*dashboard/);
        await expect(page.getByRole('heading', { name: 'Mission Control' })).toBeVisible();

        // Check for "$10,000" text (might be formatted as 10,000.00)
        await expect(page.locator('body')).toContainText('10,000');

        await expect(page.locator('body')).toContainText('Day 1');

        // Verify Welcome Banner
        await expect(page.getByText('Welcome, Manager!')).toBeVisible();
    });
});
