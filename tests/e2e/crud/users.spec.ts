import { expect, test } from '@playwright/test';
import { goToDashboard, goToUsers } from '../utils/navigation';
import { expectHiddenForViewer, expectVisibleForAdmin, isAdminProject, isViewerProject } from '../utils/role-assert';

function unique(label: string) {
    const ts = Date.now().toString(36);
    const rnd = Math.random().toString(36).slice(2, 6);
    return `${label}-${ts}-${rnd}`;
}

// Common helpers
async function findRowByText(page, text: string) {
    // Find table row that contains the given text
    return page.getByRole('row', { name: new RegExp(text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) });
}

// Admin flow: full CRUD + export + bulk (happy path)
test.describe('Users CRUD (admin)', () => {
    test.beforeEach(async ({ page }, testInfo) => {
        if (!isAdminProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        await goToUsers(page);
    });

    test('index shows and actions visible', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /gestión de usuarios|usuarios/i })).toBeVisible();
        await expectVisibleForAdmin(page);
    });

    test('create, edit, export, delete, bulk selection works', async ({ page }) => {
        const name = unique('E2E User');
        const email = `${unique('e2e')}@example.test`;
        const password = 'E2ePwd!23456';

        // Create
        await Promise.all([page.waitForURL(/\/users\/create/), page.getByRole('link', { name: /nuevo usuario/i }).click()]);

        await page.locator('input[name="name"]').fill(name);
        await page.locator('input[name="email"]').fill(email);
        await page.locator('input[name="password"]').fill(password);
        await page.locator('input[name="password_confirmation"]').fill(password);

        await page.getByRole('button', { name: /crear usuario/i }).click();
        // Expect index table search to appear (more robust than waiting for URL only)
        await expect(page.getByPlaceholder('Buscar...')).toBeVisible({ timeout: 10000 });

        // Filter table by email and assert row exists
        await page.getByPlaceholder('Buscar...').fill(email);
        await page.keyboard.press('Enter');
        const row = await findRowByText(page, email);
        await expect(row).toBeVisible();

        // Edit: open actions menu in that row -> Editar
        await row.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /editar/i }).click();
        // Wait for a key field to appear on the form (more robust than heading)
        const nameInput = page.locator('input[name="name"]');
        await expect(nameInput).toBeVisible({ timeout: 10000 });

        const nameV2 = `${name} v2`;
        await nameInput.fill(nameV2);
        await Promise.all([page.waitForURL(/\/users(\?.*)?$/), page.getByRole('button', { name: /actualizar usuario/i }).click()]);

        // Export: open Exportar and select CSV, expect a download
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page
                .getByRole('button', { name: /exportar/i })
                .click()
                .then(() => page.getByRole('menuitem', { name: /csv/i }).click()),
        ]);
        const filename = await download.suggestedFilename();
        expect(filename).toMatch(/users.*\.csv$/i);

        // Bulk selection (toggle active): select the row via checkbox
        await page.getByPlaceholder('Buscar...').fill(email);
        await page.keyboard.press('Enter');
        const targetRow = await findRowByText(page, email);
        await targetRow.getByRole('checkbox', { name: /seleccionar fila/i }).check();

        // Depending on current active state, click any of the bulk buttons available
        // Try activate then deactivate buttons; ignore if not present
        const tryClick = async (nameRegex: RegExp) => {
            const btn = page.getByRole('button', { name: nameRegex });
            if (await btn.count()) {
                await btn.click();
                // Confirm dialog if appears
                const confirm = page.getByRole('button', { name: /activar|desactivar|eliminar/i });
                if (await confirm.count()) await confirm.click();
            }
        };
        await tryClick(/activar seleccionados/i);
        await tryClick(/desactivar seleccionados/i);

        // Delete the created user
        await page.getByPlaceholder('Buscar...').fill(email);
        await page.keyboard.press('Enter');
        const row2 = await findRowByText(page, email);
        await row2.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /eliminar/i }).click();
        await page.getByRole('button', { name: /^eliminar$/i }).click();
        await expect(page.getByText(email)).toHaveCount(0);
    });
});

// Viewer: can see index but actions are hidden

test.describe('Users visibility (viewer)', () => {
    test('index link absent or actions hidden', async ({ page }, testInfo) => {
        if (!isViewerProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        const link = page.getByRole('link', { name: 'Usuarios' });
        if ((await link.count()) > 0) {
            await link.click();
            await expectHiddenForViewer(page);
        } else {
            // Attempt direct URL and accept 403 page or hidden actions
            await page.goto('/users');
            const forbidden = page.getByRole('heading', { name: /403|acceso denegado/i });
            if ((await forbidden.count()) > 0) {
                await expect(forbidden).toBeVisible();
            } else {
                await expectHiddenForViewer(page);
            }
        }
    });
});
