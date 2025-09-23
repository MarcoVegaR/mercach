import { expect, test } from '@playwright/test';
import { goToDashboard, goToLocales, goToRoles, goToUsers } from './utils/navigation';
import { isAdminProject } from './utils/role-assert';

// Smoke test for main navigation and a minimal create+show flow (Locales)

test.describe('Smoke navigation', () => {
    test('dashboard and main sections load (admin/viewer)', async ({ page }, testInfo) => {
        await goToDashboard(page);
        if (isAdminProject(testInfo.project.name)) {
            await goToUsers(page);
            await goToRoles(page);
            await goToLocales(page);
        } else {
            // Viewer: just assert dashboard and absence of admin group link
            const adminButton = page.getByRole('button', { name: 'Administración' });
            await expect(adminButton).toHaveCount(0);
        }
    });
});

// Minimal create+show in Locales (admin only)

test.describe('Locales minimal create+show (admin)', () => {
    test('create minimal Local and verify Show summary state', async ({ page }, testInfo) => {
        // Allow extra time in CI or when multiple workers are busy
        test.setTimeout(60_000);
        if (!isAdminProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        await goToLocales(page);

        // Click "+ Nuevo Local" with better error handling
        await Promise.all([
            page.waitForURL(/\/catalogs\/local\/create/),
            page.waitForResponse((res) => res.url().includes('/catalogs/local/create') && res.status() < 400),
            page.getByRole('link', { name: /nuevo local|new local/i }).click(),
        ]);

        // Check we're not redirected to login (auth failure)
        await expect(page).not.toHaveURL(/\/login/i);
        await expect(page.getByRole('heading', { name: /crear local|create local/i })).toBeVisible({ timeout: 10000 });
        // Ensure form fields are present before interacting
        await expect(page.getByLabel(/c[oó]digo/i)).toBeVisible({ timeout: 10000 });

        // Fill minimal required fields
        // Code must follow pattern /^[A-Z]-[0-9]{2}$/ per LocalStoreRequest (e.g., A-01)
        // Make it unique per project to avoid collisions across concurrent browsers
        const projInitial = (testInfo.project.name[0] || 'Z').toUpperCase();
        const two = String((Date.now() + Math.floor(Math.random() * 100)) % 100).padStart(2, '0');
        let code = `${projInitial}-${two}`;
        const name = `E2E Local ${Date.now().toString(36).slice(-3)}`;
        await page.getByLabel(/c[oó]digo/i).fill(code);
        await page.getByLabel(/nombre/i).fill(name);

        // Select dropdowns when options are available
        // Market
        await page.getByRole('combobox', { name: /mercado/i }).click();
        await page.locator('[role="listbox"] [role="option"]').first().click({ timeout: 5000 });
        // Tipo de local
        await page.getByRole('combobox', { name: /tipo de local/i }).click();
        await page.locator('[role="listbox"] [role="option"]').first().click({ timeout: 5000 });
        // Ubicación
        await page.getByRole('combobox', { name: /ubicaci[óo]n/i }).click();
        await page.locator('[role="listbox"] [role="option"]').first().click({ timeout: 5000 });

        // Área m² (requerido)
        await page.locator('input[name="area_m2"]').fill('10');

        // Submit with retry on duplicate code: stay within /^[A-Z]-[0-9]{2}$/ pattern
        // Try several randomized candidates quickly to avoid clashes with seeded data
        let created = false;
        for (let i = 0; i < 10; i++) {
            const num = String((Date.now() + Math.floor(Math.random() * 1000) + i) % 100).padStart(2, '0');
            const candidate = `${projInitial}-${num}`;
            await expect(page.getByLabel(/c[oó]digo/i)).toBeVisible({ timeout: 5000 });
            await page.getByLabel(/c[oó]digo/i).fill(candidate);
            await page.getByRole('button', { name: /^crear$/i }).click();
            try {
                await page.waitForURL(/\/catalogs\/local(?:\?.*)?$/, { timeout: 7000 });
                code = candidate; // use actual created code later for filtering
                created = true;
                break;
            } catch {
                // If URL didn't change, try detecting index heading as a success indicator
                try {
                    await expect(page.getByRole('heading', { name: /^Locales$/i })).toBeVisible({ timeout: 7000 });
                    code = candidate;
                    created = true;
                    break;
                } catch {
                    // Still on create (likely duplicate or validation). Try next candidate.
                }
            }
        }
        expect(created).toBeTruthy();

        // Ensure index heading is visible, then the search input (avoids race with navigation/hydration)
        await expect(page.getByRole('heading', { name: /^Locales$/i })).toBeVisible({ timeout: 10000 });
        await expect(page.getByPlaceholder('Buscar...')).toBeVisible({ timeout: 10000 });

        // Filter by code and open Show (Nombre column may be hidden; Código is visible)
        await page.getByPlaceholder('Buscar...').fill(code);
        await page.keyboard.press('Enter');
        const row = page
            .getByRole('row')
            .filter({ has: page.getByRole('cell', { name: code }) })
            .first();
        await expect(row).toBeVisible({ timeout: 10000 });
        const rowMenuBtn = row.getByRole('button', { name: /abrir menú/i });
        if ((await rowMenuBtn.count()) > 0) {
            try {
                await rowMenuBtn.click({ timeout: 3000 });
            } catch {
                await page
                    .getByRole('button', { name: /abrir menú/i })
                    .first()
                    .click();
            }
        } else {
            await page
                .getByRole('button', { name: /abrir menú/i })
                .first()
                .click();
        }
        const detalles = page.getByRole('menuitem', { name: /ver detalles/i });
        await expect(detalles).toBeVisible({ timeout: 5000 });
        // Use toHaveURL for SPA navigation reliability
        await detalles.click();
        await expect(page).toHaveURL(/\/catalogs\/local\/\d+$/);

        // Verify Show page loaded: H1 with item name OR code (UI may fall back to code)
        const h1 = page.getByRole('heading', { level: 1 });
        await expect(h1).toBeVisible({ timeout: 10000 });
        const escapeRx = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const nameOrCode = new RegExp(`${escapeRx(name)}|${escapeRx(code)}`);
        await expect(h1).toContainText(nameOrCode);
        // Additionally, ensure the code is visible somewhere in the details
        await expect(page.getByText(code)).toBeVisible();
        // Aside summary card (scope to complementary region to avoid strict mode)
        const aside = page.getByRole('complementary');
        await expect(aside).toBeVisible({ timeout: 10000 });
        await expect(aside.getByText(/resumen/i)).toBeVisible();
        await expect(aside.getByText(/activo/i)).toBeVisible();
    });
});
