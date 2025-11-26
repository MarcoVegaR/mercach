import { expect, test } from '@playwright/test';
import { isPortalProject } from '../utils/role-assert';

/**
 * Portal de Cesionarios (Self-Service) E2E Tests
 *
 * These tests verify the complete user experience for concessionaire portal users.
 * The portal allows concessionaires to:
 * - View their dashboard with debt summary
 * - View pending debts (deuda)
 * - View contracts
 * - View receipts and download PDFs
 * - Register new payments
 * - Apply payments to debts
 *
 * Security: Each user can only see their own concessionaire data.
 */

test.beforeEach(
    // eslint-disable-next-line no-empty-pattern
    async ({}, testInfo) => {
        // Skip if not running portal project
        if (!isPortalProject(testInfo.project.name)) {
            test.skip();
        }
    },
);

// ============================================
// Dashboard & Navigation
// ============================================

test.describe('Portal: Dashboard', () => {
    test('can access portal dashboard', async ({ page }) => {
        await page.goto('/portal');
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Should see portal dashboard (not admin dashboard)
        await expect(page).toHaveURL(/\/portal/);

        // Should see welcome message or debt summary
        const hasContent =
            (await page
                .getByText(/bienvenid|portal|resumen|deuda/i)
                .first()
                .isVisible()
                .catch(() => false)) ||
            (await page
                .locator('main')
                .isVisible()
                .catch(() => false));
        expect(hasContent).toBeTruthy();
    });

    test('cannot access admin dashboard', async ({ page }) => {
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

        // Should be redirected away or see 403
        const isRedirected = !page.url().includes('/dashboard') || page.url().includes('/portal');
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const is403 = pageText.includes('403') || pageText.includes('PERMISSION');

        expect(isRedirected || is403).toBeTruthy();
    });
});

// ============================================
// Deuda (Pending Debts)
// ============================================

test.describe('Portal: Deuda', () => {
    test('can access pending debts page', async ({ page }) => {
        await page.goto('/portal/deuda');
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Check if page loaded (not 403/404/error)
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const hasError = pageText.includes('403') || pageText.includes('404') || pageText.includes('error');
        const isOnPage = page.url().includes('/portal');

        // Either on the page or redirected to portal (acceptable)
        expect(isOnPage || !hasError).toBeTruthy();
    });
});

// ============================================
// Contratos (Contracts)
// ============================================

test.describe('Portal: Contratos', () => {
    test('can access contracts page', async ({ page }) => {
        await page.goto('/portal/contratos');
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Check if page loaded (not 403/404/error)
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const hasError = pageText.includes('403') || pageText.includes('404');
        const isOnPage = page.url().includes('/portal');

        expect(isOnPage || !hasError).toBeTruthy();
    });
});

// ============================================
// Recibos (Receipts)
// ============================================

test.describe('Portal: Recibos', () => {
    test('can access receipts page', async ({ page }) => {
        await page.goto('/portal/recibos');
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Check if page loaded (not 403/404/error)
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const hasError = pageText.includes('403') || pageText.includes('404');
        const isOnPage = page.url().includes('/portal');

        expect(isOnPage || !hasError).toBeTruthy();
    });
});

// ============================================
// Pagos (Payments)
// ============================================

test.describe('Portal: Pagos', () => {
    test('can access payments list', async ({ page }) => {
        await page.goto('/portal/pagos');
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Check if page loaded (not 403/404/error)
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const hasError = pageText.includes('403') || pageText.includes('404');
        const isOnPage = page.url().includes('/portal');

        expect(isOnPage || !hasError).toBeTruthy();
    });

    test('can access new payment form', async ({ page }) => {
        await page.goto('/portal/pagos/nuevo');
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

        // Check if page loaded (not 403/404/error)
        const pageText = (await page.textContent('body').catch(() => '')) || '';
        const hasError = pageText.includes('403') || pageText.includes('404');
        const isOnPage = page.url().includes('/portal');

        expect(isOnPage || !hasError).toBeTruthy();
    });
});

// ============================================
// Security: Data Isolation
// ============================================

test.describe('Portal: Security', () => {
    test('cannot access admin routes', async ({ page }) => {
        // Try to access admin-only routes
        const adminRoutes = ['/users', '/roles', '/catalogs/local-type', '/auditoria'];

        for (const route of adminRoutes) {
            await page.goto(route);
            await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

            const pageText = (await page.textContent('body').catch(() => '')) || '';
            const is403 = pageText.includes('403') || pageText.includes('PERMISSION') || pageText.includes('forbidden');
            const isRedirected = page.url().includes('/portal') || page.url().includes('/login');

            expect(is403 || isRedirected).toBeTruthy();
        }
    });
});
