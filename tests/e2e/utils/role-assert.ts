import { expect } from '@playwright/test';

export function isAdminProject(projectName: string) {
    return /admin/i.test(projectName);
}

export function isViewerProject(projectName: string) {
    return /viewer/i.test(projectName);
}

export async function expectVisibleForAdmin(page) {
    await expect(page.getByRole('button', { name: /nuevo|crear/i })).toBeVisible({ timeout: 2000 });
    // Export dropdown button, if present in the toolbar
    const exportBtn = page.getByRole('button', { name: /exportar/i });
    if (await exportBtn.count()) {
        await expect(exportBtn).toBeVisible();
    }
}

export async function expectHiddenForViewer(page) {
    // Common actions should not be visible for viewer
    const createBtn = page.getByRole('button', { name: /nuevo|crear/i });
    await expect(createBtn).toHaveCount(0);
    const exportBtn = page.getByRole('button', { name: /exportar/i });
    // Some tables may render export even if disabled; ensure not visible
    if (await exportBtn.count()) {
        await expect(exportBtn).toBeHidden();
    }
}
