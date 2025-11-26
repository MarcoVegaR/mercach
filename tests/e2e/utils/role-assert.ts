import { Page, expect } from '@playwright/test';

// ============================================
// Project name detection helpers
// ============================================

export function isAdminProject(projectName: string) {
    return /admin/i.test(projectName);
}

export function isConsultoriaProject(projectName: string) {
    return /consultoria/i.test(projectName);
}

export function isCobranzaProject(projectName: string) {
    return /cobranza/i.test(projectName);
}

export function isPortalProject(projectName: string) {
    return /portal/i.test(projectName);
}

// ============================================
// Admin role assertions
// ============================================

export async function expectVisibleForAdmin(page: Page) {
    await expect(page.getByRole('button', { name: /nuevo|crear/i })).toBeVisible({ timeout: 2000 });
    const exportBtn = page.getByRole('button', { name: /exportar/i });
    if (await exportBtn.count()) {
        await expect(exportBtn).toBeVisible();
    }
}

// ============================================
// Consultoría Jurídica role assertions
// ============================================

/** Verify CRUD actions are visible (for modules where consultoria has full access) */
export async function expectCrudVisibleForConsultoria(page: Page) {
    await expect(page.getByRole('button', { name: /nuevo|crear/i })).toBeVisible({ timeout: 5000 });
    const exportBtn = page.getByRole('button', { name: /exportar/i });
    if (await exportBtn.count()) {
        await expect(exportBtn).toBeVisible();
    }
}

/** Verify only read actions are visible (no create/edit) */
export async function expectReadOnlyForConsultoria(page: Page) {
    // Should NOT see create button
    const createBtn = page.getByRole('button', { name: /nuevo|crear/i });
    await expect(createBtn).toHaveCount(0);
    // May see export button (view permission often includes export)
}

/** Verify access is denied (403 or redirect) */
export async function expectAccessDenied(page: Page) {
    const pageText = (await page.textContent('body').catch(() => '')) || '';
    const is403 =
        page.url().includes('403') ||
        pageText.includes('403') ||
        pageText.includes('PERMISSION') ||
        pageText.includes('forbidden') ||
        pageText.includes('No tienes permiso');
    expect(is403).toBeTruthy();
}
