import { arrayToCsv, downloadCsv } from '@/lib/csv-utils';
import { toast } from '@/lib/toast';
import { ColumnFiltersState, RowSelectionState, SortingState } from '@tanstack/react-table';

export type ExportFormat = 'csv' | 'json' | 'pdf';

export interface ExportParams {
    format: ExportFormat;
    // Current table state
    sorting?: SortingState;
    globalFilter?: string;
    columnFilters?: ColumnFiltersState;
    rowSelection?: RowSelectionState;
    // Pagination
    pageIndex?: number;
    pageSize?: number;
    // Selection mode
    selectedOnly?: boolean;
}

export interface ExportOptions<TData = unknown> {
    endpoint: string;
    filename?: string;
    headers?: Record<string, string>;
    formatData?: (data: unknown) => unknown[];
    onSuccess?: (data?: TData) => void;
    onError?: (error: Error) => void;
    customExporter?: (data: unknown, options: ExportOptions<TData>) => Promise<void>;
}

/**
 * Builds export URL with current table state parameters
 */
function buildExportUrl(baseUrl: string, params: ExportParams): string {
    const url = new URL(baseUrl, window.location.origin);

    // Add format
    url.searchParams.set('format', params.format);

    // Add filters
    if (params.globalFilter) {
        url.searchParams.set('q', params.globalFilter);
    }

    // Add sorting
    if (params.sorting && params.sorting.length > 0) {
        const { id, desc } = params.sorting[0];
        url.searchParams.set('sort', id);
        url.searchParams.set('dir', desc ? 'desc' : 'asc');
    }

    // Add column filters
    if (params.columnFilters && params.columnFilters.length > 0) {
        params.columnFilters.forEach((filter) => {
            url.searchParams.set(`filters[${filter.id}]`, String(filter.value));
        });
    }

    // Add pagination (for server-side exports that respect current page)
    if (params.pageIndex !== undefined) {
        url.searchParams.set('page', String(params.pageIndex + 1)); // Convert to 1-based
    }
    if (params.pageSize !== undefined) {
        url.searchParams.set('perPage', String(params.pageSize));
    }

    // Add selection
    if (params.selectedOnly && params.rowSelection) {
        const selectedIds = Object.keys(params.rowSelection).filter((id) => params.rowSelection![id]);
        if (selectedIds.length > 0) {
            url.searchParams.set('selected', selectedIds.join(','));
        }
    }

    return url.toString();
}

/**
 * Export data to CSV format (client-side download)
 */
export async function exportToCsv<T extends Record<string, unknown>>(
    data: T[],
    filename: string = 'export.csv',
    headers?: Array<keyof T>,
): Promise<void> {
    void toast.promise(
        new Promise<void>((resolve, reject) => {
            try {
                const csvContent = arrayToCsv(data, headers);
                downloadCsv(csvContent, filename);
                resolve();
            } catch (error) {
                reject(error instanceof Error ? error : new Error('Error generating CSV'));
            }
        }),
        {
            loading: 'Generando CSV...',
            success: `CSV exportado: ${filename}`,
            error: 'Error al generar CSV',
        },
    );
}

/**
 * Export data to JSON format (client-side download)
 */
export async function exportToJson<T>(data: T[], filename: string = 'export.json'): Promise<void> {
    void toast.promise(
        new Promise<void>((resolve, reject) => {
            try {
                const jsonContent = JSON.stringify(data, null, 2);
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
            loading: 'Generando JSON...',
            success: 'JSON descargado exitosamente',
            error: 'Error al generar JSON',
        },
    );
}

/**
 * Export data via server endpoint (for PDF or large datasets)
 */
export async function exportViaServer<TData = unknown>(params: ExportParams, options: ExportOptions<TData>): Promise<void> {
    const { endpoint, filename, headers = {}, onSuccess, onError, customExporter: _customExporter } = options;

    const exportPromise = (async (): Promise<void> => {
        const url = buildExportUrl(endpoint, params);

        if (params.format === 'pdf' || params.format === 'csv') {
            // For PDF and server-side CSV, use direct download
            window.location.href = url;
            onSuccess?.();
        } else {
            // For JSON, use fetch
            const response = await fetch(url, { headers });

            if (!response.ok) {
                throw new Error(`Export failed: ${response.statusText}`);
            }

            const data = await response.json();

            if (params.format === 'json') {
                await exportToJson(data, filename);
            }

            onSuccess?.(data);
        }
    })();

    try {
        void toast.promise(exportPromise, {
            loading: `Exportando ${params.format.toUpperCase()}...`,
            success: 'Exportación completada',
            error: 'Error en la exportación',
        });
        await exportPromise;
    } catch (error) {
        const err = error instanceof Error ? error : new Error('Export failed');
        onError?.(err);
        throw err;
    }
}

/**
 * Main export function - handles all formats
 */
export async function exportData<TData extends Record<string, unknown> = Record<string, unknown>>(
    data: TData[] | null, // null means server-side export
    params: ExportParams,
    options: Partial<ExportOptions<TData>> = {},
): Promise<void> {
    const defaultOptions: ExportOptions<TData> = {
        endpoint: '/api/export',
        filename: `export-${new Date().toISOString().split('T')[0]}`,
        ...options,
    };

    // Client-side export for small datasets
    if (data && data.length > 0 && params.format !== 'pdf') {
        let exportData = data;
        // Filter selected rows if requested
        if (params.selectedOnly && params.rowSelection) {
            const selectedIds = Object.keys(params.rowSelection).filter((id) => params.rowSelection![id]);
            exportData = data.filter((_, index) => selectedIds.includes(String(index)));
        }

        if (params.format === 'csv') {
            return exportToCsv(exportData, defaultOptions.filename);
        } else if (params.format === 'json') {
            return exportToJson(exportData, defaultOptions.filename);
        }
    }

    // Server-side export for large datasets or PDF
    return exportViaServer(params, defaultOptions as ExportOptions);
}

// Convenience functions for common export scenarios
export const exportSelectedRows = <T extends Record<string, unknown>>(
    data: T[],
    selection: RowSelectionState,
    format: ExportFormat = 'csv',
    filename?: string,
) => exportData(data, { format, rowSelection: selection, selectedOnly: true }, { filename });

export const exportAllRows = <T extends Record<string, unknown>>(data: T[], format: ExportFormat = 'csv', filename?: string) =>
    exportData(data, { format }, { filename });

export const exportWithCurrentFilters = <T extends Record<string, unknown>>(
    data: T[] | null,
    tableState: {
        sorting: SortingState;
        globalFilter?: string;
        columnFilters?: ColumnFiltersState;
    },
    format: ExportFormat = 'csv',
    options?: Partial<ExportOptions<T>>,
) => exportData<T>(data, { format, ...tableState }, options as Partial<ExportOptions<T>>);
