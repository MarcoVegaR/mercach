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

test.describe('Catalogs: Local Type (admin)', () => {
    test.beforeEach(async ({ page }, testInfo) => {
        if (!isAdminProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        await goToCatalog(page, 'Tipos de local', 'Locales');
    });

    test('index shows and actions visible', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /tipos de local/i })).toBeVisible();
        await expectVisibleForAdmin(page);
    });

    test('create, show, edit, export, bulk setActive and delete', async ({ page }) => {
        const code = unique('E2E');
        const name = unique('E2E Tipo');

        // Create
        await page.getByRole('link', { name: /nuevo tipo de local/i }).click();
        await expect(page).toHaveURL(/\/catalogs\/local-type\/create/);
        await expect(page.getByRole('heading', { name: /crear.*tipo de local/i })).toBeVisible({ timeout: 10000 });

        await page.getByLabel(/c[oó]digo/i).fill(code);
        await page.getByLabel(/nombre/i).fill(name);

        await Promise.all([page.waitForURL(/\/catalogs\/local-type(\?.*)?$/), page.getByRole('button', { name: /^crear$/i }).click()]);

        // Filter and open show
        await page.getByPlaceholder('Buscar...').fill(name);
        await page.keyboard.press('Enter');
        const row = await findRowByText(page, name);
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /ver detalles/i }).click();
        await expect(page.getByRole('heading', { name: new RegExp(name, 'i') })).toBeVisible();

        // Back to index
        await page.goto('/catalogs/local-type');

        // Edit
        await page.getByPlaceholder('Buscar...').fill(name);
        await page.keyboard.press('Enter');
        const row2 = await findRowByText(page, name);
        await row2.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /editar/i }).click();
        await expect(page.getByRole('heading', { name: /editar.*tipo de local/i })).toBeVisible();

        const nameV2 = `${name} v2`;
        await page.getByLabel(/nombre/i).fill(nameV2);
        await Promise.all([page.waitForURL(/\/catalogs\/local-type(\?.*)?$/), page.getByRole('button', { name: /^actualizar$/i }).click()]);

        // Export CSV
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page
                .getByRole('button', { name: /exportar/i })
                .click()
                .then(() => page.getByRole('menuitem', { name: /csv/i }).click()),
        ]);
        const filename = (await download.suggestedFilename()).toLowerCase();
        // Accept either local-type or localtype naming
        expect(filename.replace(/[-_]/g, '')).toContain('localtype');

        // Bulk setActive (select row then activate)
        await page.getByPlaceholder('Buscar...').fill(nameV2);
        await page.keyboard.press('Enter');
        const row3 = await findRowByText(page, nameV2);
        await row3.getByRole('checkbox', { name: /seleccionar fila/i }).check();
        const activateBtn = page.getByRole('button', { name: /activar seleccionados/i });
        if (await activateBtn.count()) {
            await activateBtn.click();
            await page.getByRole('button', { name: /^activar$/i }).click();
        }

        // Delete the created row
        await page.getByPlaceholder('Buscar...').fill(nameV2);
        await page.keyboard.press('Enter');
        const row4 = await findRowByText(page, nameV2);
        await row4.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /eliminar/i }).click();
        await page.getByRole('button', { name: /^eliminar$/i }).click();
        await expect(page.getByText(nameV2)).toHaveCount(0);
    });
});

// Viewer visibility

test.describe('Catalogs: Local Type (viewer)', () => {
    test('index link absent or actions hidden', async ({ page }, testInfo) => {
        if (!isViewerProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        // If link visible, click and ensure actions hidden; else accept absent
        const link = page.getByRole('link', { name: /Tipos de local/i });
        if ((await link.count()) > 0) {
            await link.click();
            await expectHiddenForViewer(page);
        } else {
            await expect(link).toHaveCount(0);
        }
    });
});
