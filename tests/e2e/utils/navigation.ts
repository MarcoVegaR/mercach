import { Page, expect } from '@playwright/test';

export async function goToDashboard(page: Page) {
    await page.goto('/');
    // If welcome page, click to panel; otherwise go directly
    if (page.url().endsWith('/')) {
        try {
            await page.getByRole('link', { name: /ir al panel|dashboard/i }).click({ timeout: 2000 });
        } catch {
            /* noop */
        }
    }
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/dashboard/);
}

export async function openAdminMenu(page: Page) {
    // The admin menu is now called "Configuración" in the sidebar
    const configButton = page.getByRole('button', { name: 'Configuración' });
    // In viewer projects this group may not exist
    if ((await configButton.count()) === 0) return;
    await expect(configButton).toBeVisible();
    // Ensure at least one known submenu link is visible (Usuarios, Roles or Auditoría)
    const usersLink = page.getByRole('link', { name: 'Usuarios' });
    const rolesLink = page.getByRole('link', { name: 'Roles' });
    const auditoriaLink = page.getByRole('link', { name: 'Auditoría' });
    for (let i = 0; i < 2; i++) {
        const visible =
            (await usersLink.isVisible().catch(() => false)) ||
            (await rolesLink.isVisible().catch(() => false)) ||
            (await auditoriaLink.isVisible().catch(() => false));
        if (visible) break;
        await configButton.click();
    }
}

export async function openCatalogsGroup(page: Page, groupTitle: string) {
    // Ensure "Catálogos" group visible
    await expect(page.getByText('Catálogos')).toBeVisible();
    // Always try to open the subgroup by clicking its trigger button
    const groupButton = page.getByRole('button', { name: new RegExp(groupTitle, 'i') });
    try {
        await groupButton.click({ timeout: 1500 });
    } catch {
        /* noop: already open */
    }
}

export async function goToUsers(page: Page) {
    // Navigate directly (more reliable in CI than sidebar clicks)
    await page.goto('/users');
    await expect(page).not.toHaveURL(/\/login/i);
    await expect(page.getByRole('heading', { name: /gestión de usuarios|usuarios|users/i })).toBeVisible({ timeout: 10000 });
}

export async function goToRoles(page: Page) {
    // Navigate directly (more reliable in CI than sidebar clicks)
    await page.goto('/roles');
    await expect(page).not.toHaveURL(/\/login/i);
    await expect(page.getByRole('heading', { name: /gestión de roles|roles/i })).toBeVisible({ timeout: 10000 });
}

// Map of catalog titles to their URL paths
const catalogUrls: Record<string, string> = {
    'tipos de local': '/catalogs/local-type',
    'estados de local': '/catalogs/local-status',
    'ubicaciones de local': '/catalogs/local-location',
    'tipos de concesionario': '/catalogs/concessionaire-type',
    'tipos de documento': '/catalogs/document-type',
    'códigos de área': '/catalogs/phone-area-code',
    bancos: '/catalogs/bank',
    'cuentas receptoras': '/catalogs/company-bank-account',
    'tipos de pago': '/catalogs/payment-type',
    'estados de pago': '/catalogs/payment-status',
    'estados de cargo': '/catalogs/charge-status',
    'tipos de gasto': '/catalogs/expense-type',
    'tasas de cambio': '/catalogs/fx-rate',
    'tipos de contrato': '/catalogs/contract-type',
    'modalidades de contrato': '/catalogs/contract-modality',
    'estados de contrato': '/catalogs/contract-status',
    rubros: '/catalogs/trade-category',
    'tarifas de mercado': '/catalogs/market-tariff',
    contratos: '/catalogs/contract',
    locales: '/catalogs/local',
    cesionarios: '/catalogs/concessionaire',
    mercados: '/catalogs/market',
};

export async function goToCatalog(page: Page, itemTitle: string, _groupGuess?: string) {
    // Navigate directly using URL map (more reliable in CI than sidebar clicks)
    const url = catalogUrls[itemTitle.toLowerCase()];
    if (url) {
        await page.goto(url);
    } else {
        // Fallback: try to construct URL from title
        const slug = itemTitle
            .toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '');
        await page.goto(`/catalogs/${slug}`);
    }

    // Check we're not redirected to login (auth failure)
    await expect(page).not.toHaveURL(/\/login/i);
    await expect(page.getByRole('heading', { name: new RegExp(itemTitle, 'i') })).toBeVisible({ timeout: 10000 });
}

export async function goToLocales(page: Page) {
    // Navigate directly to locals index to avoid menu grouping ambiguity
    await page.goto('/catalogs/local');

    // Check we're not redirected to login (auth failure)
    await expect(page).not.toHaveURL(/\/login/i);
    await expect(page.getByRole('heading', { name: /local(es)?|gestión de locales|locals/i })).toBeVisible({ timeout: 10000 });
}
