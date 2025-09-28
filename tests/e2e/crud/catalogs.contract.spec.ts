import { expect, test } from '@playwright/test';
import { goToCatalog, goToDashboard } from '../../e2e/utils/navigation';
import { isAdminProject } from '../../e2e/utils/role-assert';

function unique(label: string) {
    const ts = Date.now().toString(36);
    return `${label}-${ts}`;
}

// Admin-only: verify Contract Show tabs and relations
// This spec creates a minimal Contract and navigates to its Show page
// to assert tabs (Detalles/Documentos), relations lists, and Delete visibility.
test.describe('Catalogs: Contracts (admin) — Show page', () => {
    test.describe.configure({ timeout: 120_000 });

    test.beforeEach(async ({ page }, testInfo) => {
        if (!isAdminProject(testInfo.project.name)) test.skip();
        await goToDashboard(page);
        await goToCatalog(page, 'Contratos', 'Contratos y Acuerdos');
    });

    test('create contract and verify Show tabs + relations', async ({ page }) => {
        const number = unique('C-E2E');

        // Create contract
        await page.getByRole('link', { name: /nuevo contrato/i }).click();
        await expect(page.getByRole('heading', { name: /crear contrato/i })).toBeVisible();

        // Fill form (pick first available options in selects/comboboxes)
        await page.getByLabel(/n[uú]mero/i).fill(number);

        await page.getByLabel(/tipo de contrato/i).click();
        await page.getByRole('option').first().click();

        await page.getByLabel(/modalidad/i).click();
        await page.getByRole('option').first().click();

        await page.getByLabel(/rubro/i).click();
        // Combobox: type any char to open options if needed
        await page.keyboard.type('a');
        const rubroOpt = page.getByRole('option').first();
        await expect(rubroOpt).toBeVisible();
        await rubroOpt.click();

        await page.getByLabel(/firmante principal/i).click();
        await page.getByRole('option').first().click();

        await page.getByLabel(/^locales$/i).click();
        await page.keyboard.type('l');
        await page.getByRole('option').first().click();
        await page.keyboard.press('Escape');

        await Promise.all([page.waitForURL(/\/catalogs\/contract(\?.*)?$/), page.getByRole('button', { name: /^crear$/i }).click()]);

        // Filter and open Show page for the created contract
        await page.getByPlaceholder('Buscar...').fill(number);
        await page.keyboard.press('Enter');
        const row = page.getByRole('row', { name: new RegExp(number.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) });
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: /abrir menú/i }).click();
        await page.getByRole('menuitem', { name: /ver detalles/i }).click();

        // Assert Show header and tabs
        await expect(page.getByRole('heading', { name: /contrato/i })).toBeVisible();
        await expect(page.getByRole('tab', { name: /detalles/i })).toBeVisible();
        await expect(page.getByRole('tab', { name: /documentos/i })).toBeVisible();

        // In Detalles: relations cards
        await expect(page.getByRole('heading', { name: /locales asociados/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /concesionarios asociados/i })).toBeVisible();

        // Documentos tab content: no PDF by default
        await page.getByRole('tab', { name: /documentos/i }).click();
        await expect(page.getByText(/no hay contrato disponible/i)).toBeVisible();

        // Delete button should be visible for admin (permission-based), but avoid executing it
        await expect(page.getByRole('button', { name: /eliminar/i })).toBeVisible();
    });
});
