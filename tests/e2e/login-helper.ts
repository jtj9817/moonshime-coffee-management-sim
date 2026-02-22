import { Page, expect } from '@playwright/test';

export async function login(page: Page) {
    // Unique email to avoid conflicts if DB isn't reset perfectly or for parallel runs
    const uniqueId = Date.now();
    await page.goto('/register');
    await page.getByLabel('Name').fill('Test Captain');
    await page.getByLabel('Email address').fill(`captain_${uniqueId}@moonshine.com`);
    await page.getByLabel('Password', { exact: true }).fill('password123');
    await page.getByLabel('Confirm password').fill('password123');
    await page.getByRole('button', { name: 'Create account' }).click();

    // Wait for dashboard to ensure login success
    await expect(page).toHaveURL(/\/game\/dashboard/);
}
