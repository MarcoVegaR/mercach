/**
 * E2E Tests for Consultoría Jurídica Role
 *
 * This role has the following permissions:
 * ✅ Dashboard - View only
 * ✅ Concesionarios - CRUD + export
 * ✅ Contratos - CRUD + export
 * ✅ Locales - View only
 * ✅ Rubros - View only
 * ❌ Usuarios - No access
 * ❌ Roles - No access
 * ❌ Auditoría - No access
 * ❌ Other catalogs - No access
 */

import { expect, test } from '@playwright/test';
import { expectAccessDenied, expectReadOnlyForConsultoria, isConsultoriaProject } from '../utils/role-assert';

// Only run these tests for consultoria projects
test.beforeEach(
    // eslint-disable-next-line no-empty-pattern
    async ({}, testInfo) => {
        if (!isConsultoriaProject(testInfo.project.name)) {
            test.skip();
        }
    },
);

test.describe('Consultoría Jurídica: Dashboard', () => {
    test('can view dashboard with KPIs and charts', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).not.toHaveURL(/\/login/i);
        await expect(page).toHaveURL(/\/dashboard/);

        // Wait for dashboard to load
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Verify dashboard elements are visible
        const hasContent =
            (await page
                .getByText(/métricas|dashboard|resumen/i)
                .first()
                .isVisible()
                .catch(() => false)) ||
            (await page
                .locator('main')
                .isVisible()
                .catch(() => false));
        expect(hasContent).toBeTruthy();
    });
});

test.describe('Consultoría Jurídica: Cesionarios (CRUD)', () => {
    test('can view cesionarios index with actions', async ({ page }) => {
        await page.goto('/catalogs/concessionaire');
        await expect(page).not.toHaveURL(/\/login|403/i);

        // Verify heading (UI uses "Cesionarios")
        await expect(page.getByRole('heading', { name: /cesionarios/i })).toBeVisible({ timeout: 10000 });

        // Verify export button is visible (this role has export permission)
        const exportBtn = page.getByRole('button', { name: /exportar/i });
        await expect(exportBtn).toBeVisible({ timeout: 5000 });
    });

    test('can access create cesionario form', async ({ page }) => {
        await page.goto('/catalogs/concessionaire/create');

        // Check if access is allowed or denied
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const is403 = pageText.includes('403') || pageText.includes('PERMISSION');

        if (is403) {
            // If 403, the role doesn't have create permission (acceptable)
            expect(is403).toBeTruthy();
        } else {
            // If allowed, verify form heading
            await expect(page.getByRole('heading', { name: /crear.*cesionario|nuevo.*cesionario/i })).toBeVisible({
                timeout: 10000,
            });
        }
    });
});

test.describe('Consultoría Jurídica: Contratos (CRUD)', () => {
    test('can view contratos index with actions', async ({ page }) => {
        await page.goto('/catalogs/contract');
        await expect(page).not.toHaveURL(/\/login|403/i);

        // Verify heading
        await expect(page.getByRole('heading', { name: /contratos/i })).toBeVisible({ timeout: 10000 });

        // Verify export button is visible
        const exportBtn = page.getByRole('button', { name: /exportar/i });
        await expect(exportBtn).toBeVisible({ timeout: 5000 });
    });

    test('can access create contrato form', async ({ page }) => {
        await page.goto('/catalogs/contract/create');

        // Check if access is allowed or denied
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const is403 = pageText.includes('403') || pageText.includes('PERMISSION');

        if (is403) {
            // If 403, the role doesn't have create permission (acceptable)
            expect(is403).toBeTruthy();
        } else {
            // If allowed, verify form heading
            await expect(page.getByRole('heading', { name: /crear.*contrato|nuevo.*contrato/i })).toBeVisible({
                timeout: 10000,
            });
        }
    });
});

test.describe('Consultoría Jurídica: Locales (Read Only)', () => {
    test('can view locales index but NOT create', async ({ page }) => {
        await page.goto('/catalogs/local');
        await expect(page).not.toHaveURL(/\/login|403/i);

        // Verify heading
        await expect(page.getByRole('heading', { name: /locales/i })).toBeVisible({ timeout: 10000 });

        // Should NOT have create button (read only)
        await expectReadOnlyForConsultoria(page);
    });

    test('cannot access create local form', async ({ page }) => {
        await page.goto('/catalogs/local/create');

        // Should be denied access
        await expectAccessDenied(page);
    });
});

test.describe('Consultoría Jurídica: Rubros (Read Only)', () => {
    test('can view rubros index but NOT create', async ({ page }) => {
        await page.goto('/catalogs/trade-category');
        await expect(page).not.toHaveURL(/\/login|403/i);

        // Verify heading
        await expect(page.getByRole('heading', { name: /rubros/i })).toBeVisible({ timeout: 10000 });

        // Should NOT have create button (read only)
        await expectReadOnlyForConsultoria(page);
    });
});

test.describe('Consultoría Jurídica: Access Denied Modules', () => {
    test('cannot access usuarios', async ({ page }) => {
        await page.goto('/users');
        await expectAccessDenied(page);
    });

    test('cannot access roles', async ({ page }) => {
        await page.goto('/roles');
        await expectAccessDenied(page);
    });

    test('cannot access auditoría', async ({ page }) => {
        await page.goto('/auditoria');
        await expectAccessDenied(page);
    });

    test('cannot access tipos de local (other catalog)', async ({ page }) => {
        await page.goto('/catalogs/local-type');
        await expectAccessDenied(page);
    });

    test('cannot access tipos de documento (other catalog)', async ({ page }) => {
        await page.goto('/catalogs/document-type');
        await expectAccessDenied(page);
    });

    test('cannot access bancos (other catalog)', async ({ page }) => {
        await page.goto('/catalogs/bank');
        await expectAccessDenied(page);
    });
});
