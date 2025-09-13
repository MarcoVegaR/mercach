import { toast } from '@/lib/toast';
import { Table } from '@tanstack/react-table';
import { arrayToCsv, downloadCsv } from './csv-utils';
import { columnUtils } from './table-column-factory';

/**
 * Export utilities that work with table instance and column meta
 */

/**
 * Export visible columns as CSV using column meta for headers and formatting
 */
export function exportVisibleAsCSV<T>(table: Table<T>, filename: string = 'export.csv'): void {
    void toast.promise(
        new Promise<void>((resolve, reject) => {
            try {
                const visibleColumns = table.getVisibleFlatColumns();
                const exportableColumns = columnUtils.getExportableColumns(visibleColumns);

                if (exportableColumns.length === 0) {
                    reject(new Error('No exportable columns visible'));
                    return;
                }

                // Get headers from meta
                const headers = exportableColumns.map((col) => columnUtils.getExportHeader(col));

                // Get all rows (filtered)
                const rows = table.getFilteredRowModel().rows;

                // Format data using column meta
                const exportData = rows.map((row) => {
                    const rowData: Record<string, unknown> = {};

                    exportableColumns.forEach((col, index) => {
                        const value = row.getValue(col.id!);
                        const formattedValue = columnUtils.formatForExport(col, value, row.original);
                        rowData[headers[index]] = formattedValue;
                    });

                    return rowData;
                });

                const csvContent = arrayToCsv(exportData, headers);
                downloadCsv(csvContent, filename);
                resolve();
            } catch (error) {
                reject(error instanceof Error ? error : new Error('Error generating CSV'));
            }
        }),
        {
            loading: `Generando ${filename}...`,
            success: `CSV exportado: ${filename}`,
            error: 'Error al generar CSV',
        },
    );
}

/**
 * Copy visible (filtered) rows to clipboard as TSV (tab-separated) so Excel pastes into cells
 */
export function copyVisibleToClipboard<T>(table: Table<T>): void {
    const copyPromise = (async (): Promise<void> => {
        const visibleColumns = table.getVisibleFlatColumns();
        const exportableColumns = columnUtils.getExportableColumns(visibleColumns);

        if (exportableColumns.length === 0) {
            throw new Error('No hay columnas exportables visibles');
        }

        // Headers from meta
        const headers = exportableColumns.map((col) => columnUtils.getExportHeader(col));

        // All filtered rows currently in the table (typically current page when server-paginated)
        const rows = table.getFilteredRowModel().rows;

        const sanitize = (val: unknown): string => {
            const s = val == null ? '' : String(val);
            // Replace tabs and newlines to preserve tabular structure when pasting
            return s.replace(/\r?\n/g, ' ').replace(/\t/g, ' ');
        };

        const headerLine = headers.map(sanitize).join('\t');
        const dataLines = rows.map((row) => {
            const cells = exportableColumns.map((col) => {
                const value = row.getValue(col.id!);
                const formatted = columnUtils.formatForExport(col, value, row.original);
                return sanitize(formatted);
            });
            return cells.join('\t');
        });

        const tsv = [headerLine, ...dataLines].join('\n');

        // Clipboard write with fallback
        const tryClipboardWrite = async (text: string): Promise<void> => {
            if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                await navigator.clipboard.writeText(text);
                return;
            }
            return new Promise<void>((resolve, reject) => {
                try {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';
                    document.body.appendChild(textarea);
                    textarea.focus();
                    textarea.select();
                    const successful = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    if (!successful) {
                        throw new Error('Fallback copy failed');
                    }
                    resolve();
                } catch (err) {
                    reject(err instanceof Error ? err : new Error('No se pudo copiar al portapapeles'));
                }
            });
        };

        await tryClipboardWrite(tsv);
    })();

    void toast.promise(copyPromise, {
        loading: 'Copiando al portapapeles...',
        success: 'Contenido copiado al portapapeles',
        error: 'Error al copiar al portapapeles',
    });
}

/**
 * Copy visible (filtered) rows as CSV to clipboard using column meta for headers and formatting
 */
export function copyVisibleAsCSV<T>(table: Table<T>): void {
    const copyPromise = (async (): Promise<void> => {
        const visibleColumns = table.getVisibleFlatColumns();
        const exportableColumns = columnUtils.getExportableColumns(visibleColumns);

        if (exportableColumns.length === 0) {
            throw new Error('No hay columnas exportables visibles');
        }

        // Headers from meta
        const headers = exportableColumns.map((col) => columnUtils.getExportHeader(col));

        // All filtered rows currently in the table (typically current page when server-paginated)
        const rows = table.getFilteredRowModel().rows;

        const exportData = rows.map((row) => {
            const rowData: Record<string, unknown> = {};
            exportableColumns.forEach((col, index) => {
                const value = row.getValue(col.id!);
                const formattedValue = columnUtils.formatForExport(col, value, row.original);
                rowData[headers[index]] = formattedValue;
            });
            return rowData;
        });

        const csvContent = arrayToCsv(exportData, headers);

        // Try async Clipboard API first (secure contexts), fallback to execCommand
        const tryClipboardWrite = async (text: string): Promise<void> => {
            if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                await navigator.clipboard.writeText(text);
                return;
            }

            // Fallback: create a temporary textarea and copy
            return new Promise<void>((resolve, reject) => {
                try {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';
                    document.body.appendChild(textarea);
                    textarea.focus();
                    textarea.select();
                    const successful = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    if (!successful) {
                        throw new Error('Fallback copy failed');
                    }
                    resolve();
                } catch (err) {
                    reject(err instanceof Error ? err : new Error('No se pudo copiar al portapapeles'));
                }
            });
        };

        await tryClipboardWrite(csvContent);
    })();

    void toast.promise(copyPromise, {
        loading: 'Copiando CSV...',
        success: 'CSV copiado al portapapeles',
        error: 'Error al copiar CSV',
    });
}

/**
 * Export selected rows as CSV
 */
export function exportSelectedAsCSV<T>(table: Table<T>, filename: string = 'selected-export.csv'): void {
    void toast.promise(
        new Promise<void>((resolve, reject) => {
            try {
                const selectedRows = table.getSelectedRowModel().rows;

                if (selectedRows.length === 0) {
                    reject(new Error('No rows selected'));
                    return;
                }

                const visibleColumns = table.getVisibleFlatColumns();
                const exportableColumns = columnUtils.getExportableColumns(visibleColumns);

                if (exportableColumns.length === 0) {
                    reject(new Error('No exportable columns visible'));
                    return;
                }

                // Get headers from meta
                const headers = exportableColumns.map((col) => columnUtils.getExportHeader(col));

                // Format selected rows using column meta
                const exportData = selectedRows.map((row) => {
                    const rowData: Record<string, unknown> = {};

                    exportableColumns.forEach((col, index) => {
                        const value = row.getValue(col.id!);
                        const formattedValue = columnUtils.formatForExport(col, value, row.original);
                        rowData[headers[index]] = formattedValue;
                    });

                    return rowData;
                });

                const csvContent = arrayToCsv(exportData, headers);
                downloadCsv(csvContent, filename);
                resolve();
            } catch (error) {
                reject(error instanceof Error ? error : new Error('Error generating CSV'));
            }
        }),
        {
            loading: `Generando ${filename}...`,
            success: `CSV exportado: ${filename}`,
            error: 'Error al generar CSV',
        },
    );
}

/**
 * Export as JSON with column meta formatting
 */
export function exportVisibleAsJSON<T>(table: Table<T>, filename: string = 'export.json'): void {
    void toast.promise(
        new Promise<void>((resolve, reject) => {
            try {
                const visibleColumns = table.getVisibleFlatColumns();
                const exportableColumns = columnUtils.getExportableColumns(visibleColumns);

                if (exportableColumns.length === 0) {
                    reject(new Error('No exportable columns visible'));
                    return;
                }

                // Get all rows (filtered)
                const rows = table.getFilteredRowModel().rows;

                // Format data using column meta
                const exportData = rows.map((row) => {
                    const rowData: Record<string, unknown> = {};

                    exportableColumns.forEach((col) => {
                        const header = columnUtils.getExportHeader(col);
                        const value = row.getValue(col.id!);
                        const formattedValue = columnUtils.formatForExport(col, value, row.original);
                        rowData[header] = formattedValue;
                    });

                    return rowData;
                });

                const jsonContent = JSON.stringify(exportData, null, 2);
                const blob = new Blob([jsonContent], { type: 'application/json' });
                const url = URL.createObjectURL(blob);

                const link = document.createElement('a');
                link.href = url;
                link.download = filename.endsWith('.json') ? filename : `${filename}.json`;
                link.style.display = 'none';

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                setTimeout(() => URL.revokeObjectURL(url), 100);
                resolve();
            } catch (error) {
                reject(error instanceof Error ? error : new Error('Error generating JSON'));
            }
        }),
        {
            loading: `Generando ${filename}...`,
            success: `JSON exportado: ${filename}`,
            error: 'Error al generar JSON',
        },
    );
}

/**
 * Build export URL for server-side export with current table state
 */
export function buildServerExportUrl<T>(baseUrl: string, table: Table<T>, format: 'csv' | 'json' | 'pdf' = 'csv'): string {
    const url = new URL(baseUrl);

    // Add table state parameters
    const state = table.getState();

    // Sorting
    if (state.sorting.length > 0) {
        url.searchParams.set('sort', JSON.stringify(state.sorting));
    }

    // Global filter
    if (state.globalFilter) {
        url.searchParams.set('search', state.globalFilter);
    }

    // Column filters
    if (state.columnFilters.length > 0) {
        url.searchParams.set('filters', JSON.stringify(state.columnFilters));
    }

    // Selected rows
    const selectedIds = Object.keys(state.rowSelection).filter((id) => state.rowSelection[id]);
    if (selectedIds.length > 0) {
        url.searchParams.set('selected', JSON.stringify(selectedIds));
    }

    // Visible/exportable columns
    const visibleColumns = table.getVisibleFlatColumns();
    const exportableColumns = columnUtils.getExportableColumns(visibleColumns);
    const columnIds = exportableColumns.map((col) => col.id!).filter(Boolean);
    if (columnIds.length > 0) {
        url.searchParams.set('columns', JSON.stringify(columnIds));
    }

    // Format
    url.searchParams.set('format', format);

    return url.toString();
}
