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
    await page.goto('/login');

    // Wait for page to be fully loaded
    await page.waitForLoadState('networkidle');

    // Fill credentials using accessible labels (ES/EN tolerant)
    await page.getByLabel(/email|correo/i).fill(email);
    await page.getByLabel(/password|contraseñ/i).fill(password);

    // Click login and wait for the POST /login to resolve (more reliable for SPA/AJAX flows)
    const [resp] = await Promise.all([
        page.waitForResponse((r: Response) => r.url().includes('/login') && r.request().method() === 'POST', { timeout: 15_000 }),
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

    // Wait for navigation after login - increased timeout for CI
    await page.waitForLoadState('networkidle');

    // Wait for redirect to dashboard or 2FA; if SPA keeps URL, force navigation to dashboard
    try {
        await page.waitForURL(/\/(dashboard|two-factor-challenge)/, { timeout: 15_000 });
    } catch {
        // Force navigation to dashboard
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

    // Ensure we are in dashboard (increased timeout for CI)
    await expect(page).toHaveURL(/\/dashboard/, { timeout: 15_000 });
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
