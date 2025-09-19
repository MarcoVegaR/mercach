import { expect, test } from '@playwright/test';
import { goToDashboard, goToRoles } from '../utils/navigation';
import { expectHiddenForViewer, expectVisibleForAdmin, isAdminProject, isViewerProject } from '../utils/role-assert';

function unique(label: string) {
    const ts = Date.now().toString(36);
    return `${label}-${ts}`;
}

async function findRowByText(page, text: string) {
    return page.getByRole('row', { name: new RegExp(text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) });
}

test.describe('Roles CRUD (admin)', () => {
    test.beforeEach(async ({ page }, testInfo) => {
        if (!isAdminProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        await goToRoles(page);
    });

    test('index shows and actions visible', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /gestión de roles|roles/i })).toBeVisible();
        await expectVisibleForAdmin(page);
    });

    test('create, edit, export and delete role', async ({ page }) => {
        const name = unique('E2E Rol');

        // Create
        await Promise.all([page.waitForURL(/\/roles\/create/), page.getByRole('link', { name: /nuevo rol/i }).click()]);
        // Ensure form is visible by checking a key field
        await expect(page.getByLabel(/nombre/i)).toBeVisible();

        await page.getByLabel(/nombre/i).fill(name);
        await Promise.all([page.waitForURL(/\/roles(\?.*)?$/), page.getByRole('button', { name: /crear rol/i }).click()]);

        // Filter by name
        await page.getByPlaceholder('Buscar...').fill(name);
        await page.keyboard.press('Enter');
        const row = await findRowByText(page, name);
        await expect(row).toBeVisible();

        // Edit
        await row.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /editar/i }).click();
        // The form page might not have a visible H1; assert on key field instead
        await expect(page.getByLabel(/nombre/i)).toBeVisible({ timeout: 10000 });

        const nameV2 = `${name} v2`;
        await page.getByLabel(/nombre/i).fill(nameV2);
        await Promise.all([page.waitForURL(/\/roles(\?.*)?$/), page.getByRole('button', { name: /actualizar rol/i }).click()]);

        // Export CSV
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page
                .getByRole('button', { name: /exportar/i })
                .click()
                .then(() => page.getByRole('menuitem', { name: /csv/i }).click()),
        ]);
        const filename = await download.suggestedFilename();
        expect(filename).toMatch(/roles.*\.csv$/i);

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

test.describe('Roles visibility (viewer)', () => {
    test('index visible but actions hidden', async ({ page }, testInfo) => {
        if (!isViewerProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        // If link visible, click and ensure actions hidden; else accept absent
        const link = page.getByRole('link', { name: 'Roles' });
        if ((await link.count()) > 0) {
            await link.click();
            await expectHiddenForViewer(page);
        } else {
            await expect(link).toHaveCount(0);
        }
    });
});
