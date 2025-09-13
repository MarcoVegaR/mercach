import { ColumnDef, SortingState } from '@tanstack/react-table';

export type PaginationMode = 'offset' | 'simple' | 'cursor';

export type SortableColumnDef<_TData> = ColumnDef<_TData> & { id: string; desc: boolean };

export type ColumnFiltersState = Array<{ id: string; value: unknown }>;

export type ColumnVisibilityState = Record<string, boolean>;

export type RowSelectionState = Record<string, boolean>;

export interface TableMeta<_TData = unknown> {
    totalRows?: number;
    pageIndex: number;
    pageSize: number;
    pageCount?: number;
}

export interface TableCallbacks<_TData> {
    onPageChange: (pageIndex: number) => void;
    onPageSizeChange: (pageSize: number) => void;
    onSortingChange: (sorting: SortingState) => void;
    onGlobalFilterChange?: (filter: string) => void;
    onColumnFiltersChange?: (filters: ColumnFiltersState) => void;
    onColumnVisibilityChange?: (visibility: ColumnVisibilityState) => void;
    onRowSelectionChange?: (selection: RowSelectionState) => void;
    onExportClick?: () => void;
    onDeleteSelectedClick?: () => void;
}

export interface TableState {
    sorting: SortingState;
    globalFilter?: string;
    columnFilters?: ColumnFiltersState;
    columnVisibility?: ColumnVisibilityState;
    rowSelection?: RowSelectionState;
    pagination: {
        pageIndex: number;
        pageSize: number;
    };
}
