import { expect, test } from '@playwright/test';
import { goToDashboard } from './utils/navigation';
import { isAdminProject, isConsultoriaProject, isPortalProject } from './utils/role-assert';

/**
 * Smoke Tests - Minimal verification that each role can access the app
 *
 * Purpose: Detect if deployment broke basic functionality
 * NOT for: Testing CRUD, validations, or permissions (use PHP Feature tests)
 *
 * Detailed role tests are in: tests/e2e/roles/<role-name>.spec.ts
 */

test.describe('Smoke: Admin', () => {
    test.beforeEach(
        // eslint-disable-next-line no-empty-pattern
        async ({}, testInfo) => {
            if (!isAdminProject(testInfo.project.name)) test.skip();
        },
    );

    test('can login and see dashboard', async ({ page }) => {
        await goToDashboard(page);
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Verify main content loaded
        await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
        await expect(page.getByText(/dashboard|métricas|resumen/i).first()).toBeVisible();
    });
});

test.describe('Smoke: Consultoría Jurídica', () => {
    test.beforeEach(
        // eslint-disable-next-line no-empty-pattern
        async ({}, testInfo) => {
            if (!isConsultoriaProject(testInfo.project.name)) test.skip();
        },
    );

    test('can login and see dashboard', async ({ page }) => {
        await goToDashboard(page);
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Verify main content loaded
        await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
        await expect(page.getByText(/dashboard|métricas|resumen/i).first()).toBeVisible();
    });
});

test.describe('Smoke: Portal User', () => {
    test.beforeEach(
        // eslint-disable-next-line no-empty-pattern
        async ({}, testInfo) => {
            if (!isPortalProject(testInfo.project.name)) test.skip();
        },
    );

    test('can login and see portal', async ({ page }) => {
        await page.goto('/portal');
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Verify portal loaded (not admin dashboard)
        await expect(page).toHaveURL(/\/portal/);
        await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
    });
});
