/**
 * CSV Security utilities - Protection against CSV Injection (Formula Injection)
 * @see https://owasp.org/www-community/attacks/CSV_Injection
 */

const DANGEROUS_CHARS = ['=', '+', '-', '@', '\t', '\r'];

/**
 * Escapes potentially dangerous characters that could be interpreted as formulas
 * by spreadsheet applications (Excel, LibreOffice, etc.)
 */
export function sanitizeCsvCell(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    const stringValue = String(value);

    // If the cell starts with dangerous characters, prefix with apostrophe
    if (DANGEROUS_CHARS.some((char) => stringValue.startsWith(char))) {
        return `'${stringValue}`;
    }

    return stringValue;
}

/**
 * Converts array of objects to CSV string with proper escaping
 */
export function arrayToCsv<T extends Record<string, unknown>>(data: T[], headers?: Array<keyof T>): string {
    if (!data.length) return '';

    const keys = headers || (Object.keys(data[0]) as Array<keyof T>);

    // Header row
    const headerRow = keys.map((key) => sanitizeCsvCell(String(key))).join(',');

    // Data rows
    const dataRows = data.map((row) =>
        keys
            .map((key) => {
                const cell = sanitizeCsvCell(row[key]);
                // Quote cells that contain commas, quotes, or newlines
                return /[,"\r\n]/.test(cell) ? `"${cell.replace(/"/g, '""')}"` : cell;
            })
            .join(','),
    );

    return [headerRow, ...dataRows].join('\r\n');
}

/**
 * Triggers download of CSV content
 */
export function downloadCsv(content: string, filename: string): void {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    link.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
    link.style.display = 'none';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Clean up object URL
    setTimeout(() => URL.revokeObjectURL(url), 100);
}
