import { expect, test } from '@playwright/test';

// Runs in two projects: desktop 1440x900 and mobile 390x844.

test('home page renders with a title, one h1 and no horizontal scroll', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);

    await expect(page).toHaveTitle(/.+/);
    await expect(page.locator('h1')).toHaveCount(1);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'horizontal overflow in px').toBeLessThanOrEqual(0);
});

test('head carries canonical, robots and the page is not a placeholder', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
    await expect(page.locator('meta[name="robots"]')).toHaveCount(1);
    await expect(page.locator('body')).not.toContainText(/\bTODO\b/);
});

test('robots.txt answers and states the switch', async ({ request }) => {
    const response = await request.get('/robots.txt');
    expect(response.status()).toBe(200);
    expect(await response.text()).toContain('User-agent: *');
});

test('unknown page answers 404', async ({ request }) => {
    const response = await request.get('/this-page-does-not-exist/');
    expect(response.status()).toBe(404);
});
