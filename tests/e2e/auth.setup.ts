import { expect, Page, Response, test } from '@playwright/test';

// Role: admin - Full permissions
const ADMIN_EMAIL = process.env.E2E_EMAIL_ADMIN ?? 'test@mailinator.com';
const ADMIN_PASSWORD = process.env.E2E_PASSWORD_ADMIN ?? '12345678';

// Role: consultoria-juridica - Contracts, Concessionaires, Locals (view)
const CONSULTORIA_EMAIL = process.env.E2E_EMAIL_CONSULTORIA ?? 'lauravalecillos@mailinator.com';
const CONSULTORIA_PASSWORD = process.env.E2E_PASSWORD_CONSULTORIA ?? '12345678';

// Role: portal - Cesionario self-service portal
const PORTAL_EMAIL = process.env.E2E_EMAIL_PORTAL ?? 'eva.nunez.portal@mailinator.com';
const PORTAL_PASSWORD = process.env.E2E_PASSWORD_PORTAL ?? '12345678';

// Future roles:
// Role: gestor-cobranza - Collections management
// const COBRANZA_EMAIL = process.env.E2E_EMAIL_COBRANZA ?? 'arelis@mailinator.com';
// const COBRANZA_PASSWORD = process.env.E2E_PASSWORD_COBRANZA ?? '12345678';

const TWO_FA_CODE = process.env.E2E_2FA_CODE;

async function login(page: Page, email: string, password: string) {
    // Navigate to login page
    await page.goto('/login');
    await page.waitForLoadState('domcontentloaded');

    // Wait for login form to be interactive
    const emailInput = page.getByLabel(/email|correo/i);
    await emailInput.waitFor({ state: 'visible', timeout: 10_000 });

    // Fill credentials
    await emailInput.fill(email);
    await page.getByLabel(/password|contraseñ/i).fill(password);

    // Find and click login button
    const loginButton = page.getByRole('button', { name: /iniciar sesi[oó]n|acceder|log in/i });
    await loginButton.waitFor({ state: 'visible', timeout: 5_000 });

    // Click login and wait for POST response
    const [resp] = await Promise.all([
        page.waitForResponse((r: Response) => r.url().includes('/login') && r.request().method() === 'POST', { timeout: 30_000 }),
        loginButton.click(),
    ]);

    // Validate login response
    const status = resp.status();
    const validStatuses = new Set([200, 201, 204, 302, 303, 307, 308]);
    if (!validStatuses.has(status)) {
        // Get response body for debugging
        let body = '';
        try {
            body = await resp.text();
        } catch {
            /* ignore */
        }
        throw new Error(`Login POST failed with status ${status}: ${body.slice(0, 200)}`);
    }

    // Wait for page to settle after login
    await page.waitForLoadState('networkidle');

    // Check if we're still on login page (login failed but returned 200)
    const currentUrl = page.url();
    if (currentUrl.includes('/login')) {
        // Check for validation errors on page
        const errorText = await page
            .locator('[class*="error"], [class*="alert-danger"], .text-red-500')
            .textContent()
            .catch(() => '');
        if (errorText) {
            throw new Error(`Login failed with validation error: ${errorText}`);
        }
        // Try navigating to dashboard directly
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');
    }

    // Handle redirect to dashboard or 2FA
    try {
        await page.waitForURL(/\/(dashboard|two-factor-challenge|portal)/, { timeout: 20_000 });
    } catch {
        // If still not redirected, force navigation
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');
    }

    // If 2FA challenge appears, try to solve with provided code; otherwise skip
    if (page.url().includes('/two-factor-challenge')) {
        if (TWO_FA_CODE) {
            // Try common selectors for InputOTP / recovery input
            const otpFilled = await (async () => {
                try {
                    // Try labeled input
                    await page.getByLabel(/ingresa.*c[oó]digo|TOTP|recovery/i).fill(TWO_FA_CODE);
                    return true;
                } catch {
                    /* noop */
                }
                try {
                    const firstOtp = page.locator('input').first();
                    await firstOtp.fill('');
                    for (const ch of TWO_FA_CODE) await page.keyboard.type(ch);
                    return true;
                } catch {
                    /* noop */
                }
                return false;
            })();

            if (otpFilled) {
                const [verifyResp] = await Promise.all([
                    page.waitForResponse((r: Response) => /two-factor-challenge/.test(r.url()) && r.request().method() === 'POST'),
                    page.getByRole('button', { name: /verificar/i }).click(),
                ]);
                if (!verifyResp.ok()) throw new Error('2FA verify failed');
                await page.waitForURL(/\/dashboard/, { timeout: 10_000 }).catch(() => page.goto('/dashboard'));
            } else {
                // As a fallback, attempt to continue to dashboard
                await page.goto('/dashboard');
            }
        } else {
            // Skip 2FA in dev if no code
            await page.goto('/dashboard');
        }
    }

    // Ensure we are in dashboard or portal (portal users may redirect to /portal)
    await expect(page).toHaveURL(/\/(dashboard|portal)/, { timeout: 20_000 });
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
