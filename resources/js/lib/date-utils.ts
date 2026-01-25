/**
 * Date formatting utilities that handle timezone correctly.
 *
 * When parsing YYYY-MM-DD strings, JavaScript interprets them as UTC midnight.
 * In timezones like UTC-4 (Caracas), this causes dates to display as the previous day.
 * These utilities append T12:00:00 to parse as local noon, avoiding the shift.
 */

/**
 * Parse a date string safely, avoiding timezone shift for date-only strings.
 */
export function parseLocalDate(date: string | null | undefined): Date | null {
    if (!date) return null;
    try {
        const str = String(date).trim();
        // If it's a date-only string (YYYY-MM-DD), parse as local noon to avoid UTC shift
        if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
            return new Date(str + 'T12:00:00');
        }
        // Otherwise parse as-is (includes timestamps)
        return new Date(str);
    } catch {
        return null;
    }
}

/**
 * Format a date as long format: "12 de diciembre de 2025"
 */
export function formatDateLong(date: string | null | undefined): string {
    const d = parseLocalDate(date);
    if (!d || isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
}

/**
 * Format a date as short format: "12/12/2025"
 */
export function formatDateShort(date: string | null | undefined): string {
    const d = parseLocalDate(date);
    if (!d || isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/**
 * Format a date as ISO format: "2025-12-12"
 */
export function formatDateISO(date: string | null | undefined): string {
    const d = parseLocalDate(date);
    if (!d || isNaN(d.getTime())) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Format a date as month-year: "diciembre 2025"
 */
export function formatMonthYear(date: string | null | undefined): string {
    const d = parseLocalDate(date);
    if (!d || isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('es-ES', { year: 'numeric', month: 'long' });
}

/**
 * Format a datetime with time: "12 de diciembre de 2025, 14:30"
 */
export function formatDateTime(date: string | null | undefined): string {
    if (!date) return '—';
    try {
        const d = new Date(String(date));
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
}
