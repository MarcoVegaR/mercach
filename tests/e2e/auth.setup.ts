import { expect, Page, test } from '@playwright/test';

// Increase test timeout to 60s for CI stability
test.setTimeout(60_000);

// Role: admin - Full permissions
const ADMIN_EMAIL = process.env.E2E_EMAIL_ADMIN ?? 'test@mailinator.com';
const ADMIN_PASSWORD = process.env.E2E_PASSWORD_ADMIN ?? '12345678';

// Role: consultoria-juridica - Contracts, Concessionaires, Locals (view)
const CONSULTORIA_EMAIL = process.env.E2E_EMAIL_CONSULTORIA ?? 'lauravalecillos@mailinator.com';
const CONSULTORIA_PASSWORD = process.env.E2E_PASSWORD_CONSULTORIA ?? '12345678';

// Role: portal - Cesionario self-service portal
const PORTAL_EMAIL = process.env.E2E_EMAIL_PORTAL ?? 'eva.nunez.portal@mailinator.com';
const PORTAL_PASSWORD = process.env.E2E_PASSWORD_PORTAL ?? '12345678';

const TWO_FA_CODE = process.env.E2E_2FA_CODE;

/**
 * Simplified login function for CI stability.
 * Waits for navigation after login instead of complex conditional logic.
 */
async function login(page: Page, email: string, password: string) {
    console.log(`[E2E] Starting login for: ${email}`);

    // Navigate to login page and wait for it to be ready
    await page.goto('/login', { waitUntil: 'networkidle' });
    console.log(`[E2E] Loaded login page: ${page.url()}`);

    // Wait for form fields to be visible and fill credentials
    const emailField = page.locator('#email');
    const passwordField = page.locator('#password');

    await emailField.waitFor({ state: 'visible', timeout: 10_000 });
    await emailField.fill(email);
    console.log(`[E2E] Filled email, value: ${await emailField.inputValue()}`);

    await passwordField.waitFor({ state: 'visible', timeout: 10_000 });
    await passwordField.fill(password);
    console.log(`[E2E] Filled password, has value: ${(await passwordField.inputValue()).length > 0}`);

    // Click login button and capture response
    const [response] = await Promise.all([
        page.waitForResponse((r) => r.url().includes('/login') && r.request().method() === 'POST', { timeout: 30_000 }),
        page.locator('button[type="submit"]').click(),
    ]);

    const status = response.status();
    console.log(`[E2E] Login POST response status: ${status}`);

    // For 303/302 redirects, manually navigate to avoid cookie issues with auto-redirect
    if (status === 303 || status === 302) {
        // Give the server time to save the session
        await page.waitForTimeout(500);
        // Navigate manually to dashboard/portal
        await page.goto('/dashboard', { waitUntil: 'networkidle' });
        console.log(`[E2E] After manual navigation: ${page.url()}`);

        // If redirected back to login, try portal
        if (page.url().includes('/login')) {
            await page.goto('/portal', { waitUntil: 'networkidle' });
            console.log(`[E2E] After portal navigation: ${page.url()}`);
        }
    } else if (status !== 200) {
        const body = await response.text().catch(() => 'Unable to read body');
        console.log(`[E2E] Response body: ${body.slice(0, 500)}`);
        throw new Error(`Login failed with status ${status}`);
    }

    // Handle 2FA if needed
    if (page.url().includes('/two-factor-challenge') && TWO_FA_CODE) {
        console.log('[E2E] 2FA challenge detected');
        await page.locator('input').first().fill(TWO_FA_CODE);
        await Promise.all([page.waitForURL(/\/(dashboard|portal)/, { timeout: 15_000 }), page.locator('button[type="submit"]').click()]);
    }

    // Final URL check
    const finalUrl = page.url();
    console.log(`[E2E] Final URL: ${finalUrl}`);

    if (finalUrl.includes('/login')) {
        // Take screenshot for debugging
        await page.screenshot({ path: `test-results/login-failed-${email.split('@')[0]}.png` });
        throw new Error(`Login failed - still on login page. Check screenshot for details.`);
    }

    // Verify we're on an authenticated page
    await expect(page).toHaveURL(/\/(dashboard|portal|two-factor)/, { timeout: 10_000 });
    console.log(`[E2E] Login successful for: ${email}`);
}

// Project: setup (runs before dependent projects)

test('persist admin storage state', async ({ page, context }) => {
    await login(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    await context.storageState({ path: 'tests/e2e/state.admin.json' });
});

test('persist consultoria-juridica storage state', async ({ page, context }) => {
    await page.context().clearCookies();
    await login(page, CONSULTORIA_EMAIL, CONSULTORIA_PASSWORD);
    await context.storageState({ path: 'tests/e2e/state.consultoria.json' });
});

test('persist portal-user storage state', async ({ page, context }) => {
    await page.context().clearCookies();
    await login(page, PORTAL_EMAIL, PORTAL_PASSWORD);
    await context.storageState({ path: 'tests/e2e/state.portal.json' });
});

// Future: Add more role setups as needed
// test('persist gestor-cobranza storage state', async ({ page, context }) => {
//     await page.context().clearCookies();
//     await login(page, COBRANZA_EMAIL, COBRANZA_PASSWORD);
//     await context.storageState({ path: 'tests/e2e/state.cobranza.json' });
// });
