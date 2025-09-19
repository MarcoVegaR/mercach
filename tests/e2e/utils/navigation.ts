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
    // Prefer the actual collapsible trigger button for "Administración"
    const adminButton = page.getByRole('button', { name: 'Administración' });
    // In viewer projects this group may not exist
    if ((await adminButton.count()) === 0) return;
    await expect(adminButton).toBeVisible();
    // Ensure at least one known submenu link is visible (Usuarios or Roles)
    const usersLink = page.getByRole('link', { name: 'Usuarios' });
    const rolesLink = page.getByRole('link', { name: 'Roles' });
    for (let i = 0; i < 2; i++) {
        const visible = (await usersLink.isVisible().catch(() => false)) || (await rolesLink.isVisible().catch(() => false));
        if (visible) break;
        await adminButton.click();
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
    await openAdminMenu(page);
    const link = page.getByRole('link', { name: 'Usuarios' });
    if ((await link.count()) > 0) {
        try {
            await expect(link).toBeVisible({ timeout: 3000 });
            await link.click();
        } catch {
            await page.goto('/users');
        }
    } else {
        await page.goto('/users');
    }
    await expect(page.getByRole('heading', { name: /gestión de usuarios|usuarios/i })).toBeVisible({ timeout: 10000 });
}

export async function goToRoles(page: Page) {
    await openAdminMenu(page);
    const link = page.getByRole('link', { name: 'Roles' });
    if ((await link.count()) > 0) {
        try {
            await expect(link).toBeVisible({ timeout: 3000 });
            await link.click();
        } catch {
            await page.goto('/roles');
        }
    } else {
        await page.goto('/roles');
    }
    await expect(page.getByRole('heading', { name: /gestión de roles|roles/i })).toBeVisible({ timeout: 10000 });
}

export async function goToCatalog(page: Page, itemTitle: string, groupGuess?: string) {
    // Try to ensure the item link is visible, toggling its group if provided
    const link = page.getByRole('link', { name: new RegExp(itemTitle, 'i') });
    if (groupGuess) {
        const groupButton = page.getByRole('button', { name: new RegExp(groupGuess, 'i') });
        for (let i = 0; i < 2; i++) {
            if (await link.isVisible().catch(() => false)) break;
            try {
                await groupButton.click({ timeout: 1500 });
            } catch {
                /* noop */
            }
        }
    }
    await expect(link).toBeVisible({ timeout: 10000 });
    await link.click();
    await expect(page.getByRole('heading', { name: new RegExp(itemTitle, 'i') })).toBeVisible({ timeout: 10000 });
}

export async function goToLocales(page: Page) {
    // Navigate directly to locals index to avoid menu grouping ambiguity
    await page.goto('/catalogs/local');
    await expect(page.getByRole('heading', { name: /local(es)?|gestión de locales/i })).toBeVisible({ timeout: 10000 });
}
