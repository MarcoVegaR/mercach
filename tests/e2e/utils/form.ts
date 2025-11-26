import { Page } from '@playwright/test';

/**
 * Generate a unique string with optional uppercase-only suffix.
 * Many catalog code fields require [A-Z0-9_\-\.] pattern.
 */
export function unique(label: string, options?: { uppercase?: boolean }) {
    const ts = Date.now().toString(36);
    const suffix = options?.uppercase ? ts.toUpperCase() : ts;
    return `${label}-${suffix}`;
}

/**
 * Generate a unique code string (uppercase only) for catalog code fields.
 */
export function uniqueCode(label: string) {
    return unique(label, { uppercase: true });
}

// Helper to select from shadcn/ui Select driven components by visible labels
export async function selectByLabelOption(page: Page, label: RegExp, option: RegExp) {
    // Try to find a trigger by associated label text
    const labelEl = page.getByText(label).first();
    const triggerCandidate = labelEl.locator('..').locator('button, [role="combobox"], [data-state]');
    try {
        await triggerCandidate.first().click({ timeout: 1500 });
    } catch {
        // Fallback: try a role-based query
        try {
            await page.getByRole('button', { name: label }).click({ timeout: 1500 });
        } catch {
            // Last resort: click by id matching common patterns
            const id = await page
                .locator(
                    `#${String(label)
                        .toLowerCase()
                        .replace(/[^a-z0-9_]+/g, '-')}`,
                )
                .elementHandle()
                .catch(() => null);
            if (id) await id.click();
        }
    }

    // Pick the option
    try {
        await page.getByRole('option', { name: option }).click({ timeout: 1500 });
    } catch {
        await page.getByText(option).first().click({ timeout: 1500 });
    }
}
