import { expect, test } from '@playwright/test';

const ADMIN_EMAIL = process.env.E2E_EMAIL_ADMIN ?? 'test@mailinator.com';
const ADMIN_PASSWORD = process.env.E2E_PASSWORD_ADMIN ?? '12345678';
const VIEWER_EMAIL = process.env.E2E_EMAIL_VIEWER ?? 'viewer@mailinator.com';
const VIEWER_PASSWORD = process.env.E2E_PASSWORD_VIEWER ?? '12345678';
const TWO_FA_CODE = process.env.E2E_2FA_CODE;

async function login(page, email: string, password: string) {
    await page.goto('/login');

    // Fill credentials using accessible labels (ES/EN tolerant)
    await page.getByLabel(/email|correo/i).fill(email);
    await page.getByLabel(/password|contraseñ/i).fill(password);
    await Promise.all([
        page.waitForNavigation({ url: /\/(dashboard|two-factor-challenge)/ }),
        page.getByRole('button', { name: /iniciar sesi[oó]n|acceder|log in/i }).click(),
    ]);

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
                await Promise.all([page.waitForNavigation({ url: /\/dashboard/ }), page.getByRole('button', { name: /verificar/i }).click()]);
            } else {
                // As a fallback, attempt to continue to dashboard
                await page.goto('/dashboard');
            }
        } else {
            // Skip 2FA in dev if no code
            await page.goto('/dashboard');
        }
    }

    // Ensure we are in dashboard
    await expect(page).toHaveURL(/\/dashboard/);
}

// Project: setup (runs before dependent projects)

test('persist admin storage state', async ({ page, context }) => {
    await login(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    await context.storageState({ path: 'tests/e2e/state.admin.json' });
});

test('persist viewer storage state', async ({ page, context }) => {
    await page.context().clearCookies();
    await login(page, VIEWER_EMAIL, VIEWER_PASSWORD);
    await context.storageState({ path: 'tests/e2e/state.viewer.json' });
});
