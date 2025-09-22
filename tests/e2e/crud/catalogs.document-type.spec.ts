import { expect, test } from '@playwright/test';
import { goToCatalog, goToDashboard } from '../../e2e/utils/navigation';
import { expectHiddenForViewer, expectVisibleForAdmin, isAdminProject, isViewerProject } from '../../e2e/utils/role-assert';

function unique(label: string) {
    const ts = Date.now().toString(36);
    return `${label}-${ts}`;
}

async function findRowByText(page, text: string) {
    return page.getByRole('row', { name: new RegExp(text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) });
}

test.describe('Catalogs: Document Type (admin)', () => {
    test.describe.configure({ timeout: 90_000 });
    test.beforeEach(async ({ page }, testInfo) => {
        if (!isAdminProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        await goToCatalog(page, 'Tipos de documento', 'Identificación');
    });

    test('index shows and actions visible', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /tipos de documento/i })).toBeVisible({ timeout: 10000 });
        await expectVisibleForAdmin(page);
    });

    test('create, edit, export and delete', async ({ page }) => {
        const code = unique('E2E');
        const name = unique('E2E Doc');

        // Create: click and assert Create heading (more robust than waiting for URL alone)
        await page.getByRole('link', { name: /nuevo tipo de documento/i }).click();
        await expect(page.getByRole('heading', { name: /crear.*tipo de documento/i })).toBeVisible({ timeout: 10000 });

        await page.getByLabel(/c[oó]digo/i).fill(code);
        await page.getByLabel(/nombre/i).fill(name);
        // optional mask
        try {
            await page.getByLabel(/mask/i).fill('AAA-999');
        } catch {
            /* noop */
        }

        await Promise.all([page.waitForURL(/\/catalogs\/document-type(\?.*)?$/), page.getByRole('button', { name: /^crear$/i }).click()]);

        // Filter
        await page.getByPlaceholder('Buscar...').fill(name);
        await page.keyboard.press('Enter');
        const row = await findRowByText(page, name);
        await expect(row).toBeVisible();

        // Edit
        await row.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /editar/i }).click();
        await expect(page.getByRole('heading', { name: /editar.*tipo de documento/i })).toBeVisible();

        const nameV2 = `${name} v2`;
        await page.getByLabel(/nombre/i).fill(nameV2);
        await Promise.all([page.waitForURL(/\/catalogs\/document-type(\?.*)?$/), page.getByRole('button', { name: /^actualizar$/i }).click()]);

        // Export CSV (open dropdown first, then click CSV while waiting for download)
        await page.getByRole('button', { name: /exportar/i }).click();
        const csvItem = page.getByRole('menuitem', { name: /csv/i });
        await expect(csvItem).toBeVisible({ timeout: 5000 });
        const [download] = await Promise.all([page.waitForEvent('download'), csvItem.click()]);
        const filename = (await download.suggestedFilename()).toLowerCase();
        expect(filename.replace(/[-_]/g, '')).toContain('documenttype');

        // Delete
        await page.getByPlaceholder('Buscar...').fill(nameV2);
        await page.keyboard.press('Enter');
        const row2 = await findRowByText(page, nameV2);
        await row2.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /eliminar/i }).click();
        await page.getByRole('button', { name: /^eliminar$/i }).click();
        await expect(page.getByText(nameV2)).toHaveCount(0);
    });
});

// Viewer visibility

test.describe('Catalogs: Document Type (viewer)', () => {
    test('index link absent or actions hidden', async ({ page }, testInfo) => {
        if (!isViewerProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        const link = page.getByRole('link', { name: /tipos de documento/i });
        if ((await link.count()) > 0) {
            await link.click();
            await expectHiddenForViewer(page);
        } else {
            // No link visible for viewer; acceptable
            await expect(link).toHaveCount(0);
        }
    });
});
