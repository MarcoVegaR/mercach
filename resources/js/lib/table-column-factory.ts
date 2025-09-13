/* eslint-disable */
import { ColumnDef, createColumnHelper } from '@tanstack/react-table';
import { ColumnMetaConfig, createColumnMeta } from './table-column-meta';

// Generic column factory for consistent column definitions
export function createTableColumn<TData = any>() {
    const columnHelper = createColumnHelper<TData>();

    return {
        // Accessor column with meta
        accessor: <TValue = any>(
            accessor: any, // Use any to avoid complex TanStack type constraints
            config: {
                id?: string;
                header?: string | ((props: any) => any);
                cell?: (props: any) => any;
                meta?: ColumnMetaConfig<TData, TValue>;
                enableSorting?: boolean;
                enableColumnFilter?: boolean;
                enableHiding?: boolean;
                size?: number;
            } = {},
        ): ColumnDef<TData, TValue> => {
            const { meta, ...columnConfig } = config;

            return columnHelper.accessor(accessor, {
                ...columnConfig,
                meta: meta ? createColumnMeta(meta) : undefined,
                // Override TanStack defaults with meta values if provided
                enableSorting: config.enableSorting ?? meta?.sortable ?? true,
                enableColumnFilter: config.enableColumnFilter ?? meta?.filterable ?? false,
                enableHiding: config.enableHiding ?? meta?.hideable ?? true,
                size: config.size ?? (typeof meta?.width === 'number' ? meta.width : undefined),
            });
        },

        // Display column (no data accessor)
        display: (config: {
            id: string;
            header?: string | ((props: any) => any);
            cell?: (props: any) => any;
            meta?: ColumnMetaConfig<TData, any>;
            enableSorting?: boolean;
            enableColumnFilter?: boolean;
            enableHiding?: boolean;
            size?: number;
        }): ColumnDef<TData, unknown> => {
            const { meta, ...columnConfig } = config;

            return columnHelper.display({
                ...columnConfig,
                meta: meta ? createColumnMeta(meta) : undefined,
                enableSorting: config.enableSorting ?? meta?.sortable ?? false,
                enableColumnFilter: config.enableColumnFilter ?? meta?.filterable ?? false,
                enableHiding: config.enableHiding ?? meta?.hideable ?? true,
                size: config.size ?? (typeof meta?.width === 'number' ? meta.width : undefined),
            });
        },

        // Group column for nested headers
        group: (config: {
            id: string;
            header?: string | ((props: any) => any);
            columns: ColumnDef<TData>[];
            meta?: ColumnMetaConfig<TData, any>;
        }): ColumnDef<TData, unknown> => {
            const { meta, ...columnConfig } = config;

            return columnHelper.group({
                ...columnConfig,
                meta: meta ? createColumnMeta(meta) : undefined,
            });
        },
    };
}

// Utility functions to work with meta-enabled columns
export const columnUtils = {
    // Check if user has permission to see column
    hasPermission: (column: ColumnDef<any, any>, userPermissions: string[] = [], isGuest: boolean = false): boolean => {
        const meta = column.meta;
        if (!meta) return true;

        // Check guest restrictions
        if (isGuest && meta.hideFromGuests) {
            return false;
        }

        // Check permission requirements
        if (meta.requires) {
            const requiredPerms = Array.isArray(meta.requires) ? meta.requires : [meta.requires];
            return requiredPerms.some((perm) => userPermissions.includes(perm));
        }

        return true;
    },

    // Check if column is exportable
    isExportable: (column: ColumnDef<any, any>): boolean => {
        return column.meta?.exportable !== false;
    },

    // Get export header for column
    getExportHeader: (column: ColumnDef<any, any>): string => {
        const meta = column.meta;
        if (meta?.exportHeader) return meta.exportHeader;
        if (meta?.title) return meta.title;
        if (typeof column.header === 'string') return column.header;
        return column.id || 'Column';
    },

    // Format value for export
    formatForExport: <T>(column: ColumnDef<T, any>, value: any, row: T): string | number => {
        const meta = column.meta;

        // Use export-specific formatter if available
        if (meta?.exportFormat) {
            return meta.exportFormat(value, row);
        }

        // Fallback to display formatter
        if (meta?.format) {
            return meta.format(value, row);
        }

        // Default: convert to string
        return value != null ? String(value) : '';
    },

    // Get filtered columns based on permissions
    getVisibleColumns: <T>(columns: ColumnDef<T, any>[], userPermissions: string[] = [], isGuest: boolean = false): ColumnDef<T, any>[] => {
        return columns.filter((col) => columnUtils.hasPermission(col, userPermissions, isGuest));
    },

    // Get exportable columns from table instance
    getExportableColumns: <T>(visibleColumns: ColumnDef<T, any>[]): ColumnDef<T, any>[] => {
        return visibleColumns.filter(columnUtils.isExportable);
    },
};
