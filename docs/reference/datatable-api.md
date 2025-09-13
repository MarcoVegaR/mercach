---
title: 'API de DataTable'
summary: 'Referencia de props, metadatos de columnas y utilidades de exportación/selección integradas con TanStack Table v8.'
icon: material/table
tags:
    - referencia
    - frontend
    - datatable
---

# API de DataTable

## DataTableProps Interface

```typescript
interface DataTableProps<TData> {
    // Core data
    columns: ColumnDef<TData, unknown>[];
    data: TData[];

    // Pagination
    rowCount?: number;
    pageIndex: number;
    pageSize: number;
    onPageChange: (pageIndex: number) => void;
    onPageSizeChange: (pageSize: number) => void;
    paginationMode?: 'offset' | 'simple' | 'cursor' | 'server';

    // Sorting
    sorting: SortingState;
    onSortingChange: OnChangeFn<SortingState>;

    // Filtering
    globalFilter?: string;
    onGlobalFilterChange?: (filter: string) => void;
    columnFilters?: ColumnFiltersState;
    onColumnFiltersChange?: OnChangeFn<ColumnFiltersState>;

    // Column visibility
    columnVisibility?: VisibilityState;
    onColumnVisibilityChange?: OnChangeFn<VisibilityState>;

    // Row selection
    rowSelection?: RowSelectionState;
    onRowSelectionChange?: OnChangeFn<RowSelectionState>;

    // UI customization
    isLoading?: boolean;
    emptyState?: React.ReactNode;
    toolbar?: React.ReactNode;
    onExportClick?: (table: Table<TData>) => void;
    onDeleteSelectedClick?: () => void;
    getRowId?: (originalRow: TData, index: number) => string;
    enableRowSelection?: boolean;
    enableColumnVisibility?: boolean;
    enableGlobalFilter?: boolean;
    permissions?: Record<string, boolean>;
    className?: string;
}
```

## Column Meta Configuration

```typescript
interface ColumnMetaConfig {
    label?: string;
    exportable?: boolean;
    permission?: string;
    sortable?: boolean;
    filterable?: boolean;
    hidden?: boolean;
    className?: string;
    headerClassName?: string;
}
```

## Table Column Factory

```typescript
// Create strongly-typed columns with meta
const userColumns = [
  createTableColumn<User>().accessor('name', {
    label: 'Nombre Completo',
    exportable: true,
    permission: 'users.view',
    sortable: true
  }),

  createTableColumn<User>().display({
    id: 'actions',
    label: 'Acciones',
    exportable: false,
    permission: 'users.manage',
    cell: ({ row }) => <RowActionsMenu items={actionItems} />
  })
]
```

## Delete Service Integration

```typescript
import { deleteSingle, deleteBulk } from '@/lib/delete-service';

// Single delete with confirmation
const handleDelete = async (id: string) => {
    await deleteSingle(id, {
        endpoint: '/api/users/:id',
        onSuccess: () => {
            // Refresh data
        },
    });
};

// Bulk delete with confirmation
const handleBulkDelete = async (selectedIds: string[]) => {
    await deleteBulk({
        endpoint: '/api/users/bulk-delete',
        selectedIds,
        onSuccess: () => {
            // Refresh data
        },
    });
};
```

## Export Integration

```typescript
import { Table } from '@tanstack/react-table';
import { exportVisibleAsCSV, exportSelectedAsCSV, buildServerExportUrl } from '@/lib/export-from-table';

// Client-side: export all visible rows/columns using column meta for headers/formatting
const onExportCsv = (table: Table<User>) => {
    exportVisibleAsCSV(table, 'users-export.csv');
};

// Client-side: export only selected rows
const onExportSelectedCsv = (table: Table<User>) => {
    exportSelectedAsCSV(table, 'users-selected.csv');
};

// Server-side: build URL preserving current state (filters, sorting, selection, columns)
const url = buildServerExportUrl('/api/users/export', table, 'csv');
// window.location.href = url
```

## Permission System

```typescript
// Column-level permissions
const columns = [
  createTableColumn<User>().accessor('salary', {
    label: 'Salario',
    permission: 'users.view_salary' // Only show if user has permission
  })
]

// Table-level permissions
<DataTable
  permissions={{
    canCreate: hasPermission('users.create'),
    canEdit: hasPermission('users.edit'),
    canDelete: hasPermission('users.delete'),
    canExport: hasPermission('users.export'),
    canBulkDelete: hasPermission('users.bulk_delete')
  }}
/>
```
