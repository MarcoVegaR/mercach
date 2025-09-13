import '@tanstack/react-table';

// Module augmentation to extend TanStack Table's ColumnMeta globally
declare module '@tanstack/react-table' {
    interface ColumnMeta<TData, TValue> {
        // Display properties
        title?: string;
        description?: string;

        // Export configuration
        exportable?: boolean;
        exportHeader?: string;
        exportLabel?: string; // legacy alias used in some tables
        exportWidth?: number;

        // Formatting functions
        format?: (value: TValue, row: TData) => string | number;
        exportFormat?: (value: TValue, row: TData) => string | number;

        // Permissions and visibility
        requires?: string | string[]; // Permission(s) required to see this column
        hideFromGuests?: boolean;

        // Column behavior
        sortable?: boolean;
        filterable?: boolean;
        hideable?: boolean;

        // Server-side mapping
        accessorKeyServer?: string;

        // Styling hints
        align?: 'left' | 'center' | 'right';
        width?: number | string;
        className?: string;

        // Accessibility
        ariaLabel?: string;
    }
}

export interface ColumnMetaConfig<TData = unknown, TValue = unknown> {
    title?: string;
    description?: string;
    exportable?: boolean;
    exportHeader?: string;
    exportWidth?: number;
    format?: (value: TValue, row: TData) => string | number;
    exportFormat?: (value: TValue, row: TData) => string | number;
    requires?: string | string[];
    hideFromGuests?: boolean;
    sortable?: boolean;
    filterable?: boolean;
    hideable?: boolean;
    align?: 'left' | 'center' | 'right';
    width?: number | string;
    className?: string;
    ariaLabel?: string;
}

// Helper function to create strongly typed meta
export function createColumnMeta<TData = unknown, TValue = unknown>(config: ColumnMetaConfig<TData, TValue>): ColumnMetaConfig<TData, TValue> {
    return {
        exportable: true,
        sortable: true,
        filterable: false,
        hideable: true,
        align: 'left',
        ...config,
    };
}
