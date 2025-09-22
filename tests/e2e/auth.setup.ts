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
    // Click login and wait for the POST /login to resolve (more reliable for SPA/AJAX flows)
    const [resp] = await Promise.all([
        page.waitForResponse((r) => r.url().endsWith('/login') && r.request().method() === 'POST'),
        page.getByRole('button', { name: /iniciar sesi[oó]n|acceder|log in/i }).click(),
    ]);
    // Accept 2xx and 3xx as successful login POST (Fortify redirects with 302/303)
    {
        const status = resp.status();
        const okStatuses = new Set([200, 201, 204, 302, 303, 307, 308]);
        if (!okStatuses.has(status)) {
            throw new Error(`Login POST failed: ${status} ${resp.statusText()}`);
        }
    }
    // Wait for redirect to dashboard or 2FA; if SPA keeps URL, force navigation to dashboard
    try {
        await page.waitForURL(/\/(dashboard|two-factor-challenge)/, { timeout: 10_000 });
    } catch {
        await page.goto('/dashboard');
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
                    page.waitForResponse((r) => /two-factor-challenge/.test(r.url()) && r.request().method() === 'POST'),
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
