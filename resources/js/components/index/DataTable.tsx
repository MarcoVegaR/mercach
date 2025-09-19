import {
    ColumnDef,
    ColumnFiltersState,
    OnChangeFn,
    RowSelectionState,
    SortingState,
    Table,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Copy, Download, FileJson, FileSpreadsheet, FileText } from 'lucide-react';

import { copyVisibleToClipboard } from '@/lib/export-from-table';
import { BulkActionBar } from './BulkActionBar';
import { ColumnVisibilityMenu } from './ColumnVisibilityMenu';
import { SortableHeader } from './SortableHeader';
import { TableToolbar } from './TableToolbar';

export type PaginationMode = 'offset' | 'simple' | 'cursor' | 'server';

export interface DataTableProps<TData = unknown> {
    // Core data and columns
    columns: ColumnDef<TData, unknown>[];
    data: TData[];

    // Pagination (manual)
    rowCount?: number;
    pageIndex: number;
    pageSize: number;
    onPageChange: (index: number) => void;
    onPageSizeChange: (size: number) => void;

    // Sorting (manual)
    sorting: SortingState;
    onSortingChange: OnChangeFn<SortingState>;

    // Filtering (manual)
    globalFilter?: string;
    onGlobalFilterChange?: (filter: string) => void;
    columnFilters?: ColumnFiltersState;
    onColumnFiltersChange?: OnChangeFn<ColumnFiltersState>;

    // Column visibility (controlled)
    columnVisibility?: VisibilityState;
    onColumnVisibilityChange?: OnChangeFn<VisibilityState>;

    // Row selection (controlled)
    rowSelection?: RowSelectionState;
    onRowSelectionChange?: OnChangeFn<RowSelectionState>;

    // UI customization
    isLoading?: boolean;
    emptyState?: React.ReactNode;
    toolbar?: React.ReactNode;
    // Export functionality
    canExport?: boolean;
    onExportClick?: (format: string, table: Table<TData>) => void;
    onDeleteSelectedClick?: () => void;
    onActivateSelectedClick?: () => void;
    onDeactivateSelectedClick?: () => void;
    paginationMode?: PaginationMode;
    getRowId?: (originalRow: TData, index: number) => string;
    enableRowSelection?: boolean;
    enableColumnVisibility?: boolean;
    enableGlobalFilter?: boolean;
    permissions?: Record<string, boolean>;
    className?: string;
    // Density (row padding)
    density?: 'comfortable' | 'compact';
    onDensityChange?: (density: 'comfortable' | 'compact') => void;
    // Which column id should be sticky on the left (besides selection). Defaults to 'name'.
    stickyLeftColumnId?: string;
}

const DEFAULT_PAGE_SIZES = [10, 25, 50, 100];

export function DataTable<TData>({
    columns,
    data,
    rowCount = data.length,
    pageIndex,
    pageSize,
    onPageChange,
    onPageSizeChange,
    sorting,
    onSortingChange,
    globalFilter,
    onGlobalFilterChange,
    columnFilters,
    onColumnFiltersChange,
    columnVisibility,
    onColumnVisibilityChange,
    rowSelection,
    onRowSelectionChange,
    isLoading = false,
    emptyState,
    toolbar,
    canExport: enableExport = false,
    onExportClick,
    onDeleteSelectedClick,
    onActivateSelectedClick,
    onDeactivateSelectedClick,
    paginationMode: _paginationMode = 'offset',
    getRowId,
    enableRowSelection = false,
    enableColumnVisibility = true,
    enableGlobalFilter = true,
    permissions = {},
    className,
    density = 'comfortable',
    onDensityChange,
    stickyLeftColumnId = 'name',
}: DataTableProps<TData>) {
    const pageCount = Math.ceil(rowCount / pageSize);

    // Add selection column if enabled
    const tableColumns = React.useMemo(() => {
        if (!enableRowSelection) return columns;

        const selectionColumn: ColumnDef<TData, unknown> = {
            id: 'select',
            header: ({ table }) => (
                <Checkbox
                    checked={table.getIsAllPageRowsSelected() ? true : table.getIsSomePageRowsSelected() ? 'indeterminate' : false}
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    aria-label="Seleccionar todos"
                    className="data-[state=checked]:border-primary data-[state=checked]:bg-primary focus-visible:ring-primary/50 border-2 border-slate-400 shadow-sm hover:border-slate-500 dark:border-slate-500 dark:hover:border-slate-400"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    aria-label="Seleccionar fila"
                    className="data-[state=checked]:border-primary data-[state=checked]:bg-primary focus-visible:ring-primary/50 mt-0.5 border-2 border-slate-400 shadow-sm hover:border-slate-500 dark:border-slate-500 dark:hover:border-slate-400"
                />
            ),
            enableSorting: false,
            enableHiding: false,
            size: 40,
        };

        return [selectionColumn, ...columns];
    }, [columns, enableRowSelection]);

    const table = useReactTable({
        data,
        columns: tableColumns,
        state: {
            sorting,
            columnVisibility: columnVisibility || {},
            rowSelection: rowSelection || {},
            pagination: { pageIndex, pageSize },
            globalFilter,
            columnFilters: columnFilters || [],
        },
        pageCount,
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
        enableRowSelection: enableRowSelection,
        onSortingChange,
        onColumnVisibilityChange,
        onRowSelectionChange,
        onPaginationChange: (updater) => {
            const newPagination = typeof updater === 'function' ? updater({ pageIndex, pageSize }) : updater;
            onPageChange(newPagination.pageIndex);
            onPageSizeChange(newPagination.pageSize);
        },
        onGlobalFilterChange,
        onColumnFiltersChange,
        getCoreRowModel: getCoreRowModel(),
        getRowId,
    });

    // IMPORTANT: count selected rows across all pages (from controlled state),
    // not just the currently loaded/visible page rows.
    const selectedCount = React.useMemo(() => {
        const state = rowSelection || {};
        return Object.values(state).filter(Boolean).length;
    }, [rowSelection]);

    const cellPaddingClass = density === 'compact' ? 'p-2' : 'p-3';

    const hasSelection = enableRowSelection;

    // Extract permissions
    const {
        canCreate: _canCreate,
        canEdit: _canEdit,
        canDelete: _canDelete,
        canExport: _canExport,
        canBulkDelete,
        canBulkSetActive,
    } = permissions || {};

    // Column visibility options for menu
    const columnVisibilityOptions = React.useMemo(
        () =>
            table
                .getAllColumns()
                .filter((column) => column.getCanHide())
                .map((column) => ({
                    id: column.id,
                    label: typeof column.columnDef.header === 'string' ? column.columnDef.header : column.id,
                    canHide: column.getCanHide(),
                })),
        [table],
    );

    if (isLoading) {
        return (
            <div className="flex h-32 items-center justify-center">
                <div className="text-muted-foreground">Cargando...</div>
            </div>
        );
    }

    // Don't return early for empty data - show the full table with controls

    return (
        <div className={cn('space-y-4', className)}>
            {/* Toolbar */}
            <div className="flex items-center justify-between gap-2">
                <TableToolbar
                    className="min-w-0 flex-1"
                    globalFilter={globalFilter}
                    onGlobalFilterChange={enableGlobalFilter ? onGlobalFilterChange : undefined}
                >
                    {toolbar}
                </TableToolbar>

                <div className="flex items-center gap-2">
                    {/* Density toggle fixed at right */}
                    {onDensityChange && (
                        <ToggleGroup type="single" value={density} onValueChange={(v) => v && onDensityChange(v as 'comfortable' | 'compact')}>
                            <ToggleGroupItem value="comfortable" aria-label="Densidad cómoda" className="px-2 py-1 text-xs">
                                Cómoda
                            </ToggleGroupItem>
                            <ToggleGroupItem value="compact" aria-label="Densidad compacta" className="px-2 py-1 text-xs">
                                Compacta
                            </ToggleGroupItem>
                        </ToggleGroup>
                    )}

                    {/* Export dropdown */}
                    {enableExport && onExportClick && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="flex items-center gap-2">
                                    <Download className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                    Exportar
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onClick={() => onExportClick('csv', table)} className="flex cursor-pointer items-center gap-2">
                                    <FileText className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                    Exportar como CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => onExportClick('xlsx', table)} className="flex cursor-pointer items-center gap-2">
                                    <FileSpreadsheet className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                    Exportar como Excel
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => onExportClick('json', table)} className="flex cursor-pointer items-center gap-2">
                                    <FileJson className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                    Exportar como JSON
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => copyVisibleToClipboard(table)} className="flex cursor-pointer items-center gap-2">
                                    <Copy className="h-4 w-4" />
                                    Copiar al portapapeles
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    {/* Column visibility */}
                    {enableColumnVisibility && (
                        <ColumnVisibilityMenu
                            columns={columnVisibilityOptions}
                            columnVisibility={columnVisibility || {}}
                            onColumnVisibilityChange={onColumnVisibilityChange || (() => {})}
                        />
                    )}
                </div>
            </div>

            {/* Bulk Action Bar */}
            {enableRowSelection && (
                <BulkActionBar
                    selectedCount={selectedCount}
                    onDeleteSelected={canBulkDelete ? onDeleteSelectedClick : undefined}
                    onActivateSelected={canBulkSetActive ? onActivateSelectedClick : undefined}
                    onDeactivateSelected={canBulkSetActive ? onDeactivateSelectedClick : undefined}
                    onClearSelection={() => {
                        // Clear the controlled selection state globally
                        if (onRowSelectionChange) {
                            onRowSelectionChange({});
                        } else {
                            table.toggleAllRowsSelected(false);
                        }
                    }}
                />
            )}

            {/* Table */}
            <div className="overflow-x-auto rounded-md border">
                <table className="w-full min-w-[900px]">
                    <thead>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id} className="border-b">
                                {headerGroup.headers.map((header) => {
                                    const sortDirection = header.column.getIsSorted();
                                    const canSort = header.column.getCanSort();

                                    const stickyId = stickyLeftColumnId;
                                    return (
                                        <SortableHeader
                                            key={header.id}
                                            sortDirection={sortDirection || false}
                                            onSort={canSort ? () => header.column.toggleSorting() : undefined}
                                            className={cn(
                                                'text-muted-foreground h-12 text-left align-middle font-medium [&:has([role=checkbox])]:pr-0',
                                                header.column.getCanHide() && !header.column.getIsVisible() && 'hidden',
                                                header.column.id === 'select' &&
                                                    'bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky left-0 z-10 w-[48px] border-r backdrop-blur-sm',
                                                header.column.id === stickyId &&
                                                    (hasSelection
                                                        ? 'bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky left-[48px] z-10 border-r backdrop-blur-sm'
                                                        : 'bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky left-0 z-10 border-r backdrop-blur-sm'),
                                                header.column.id === 'actions' &&
                                                    'bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky right-0 z-10 border-l text-right backdrop-blur-sm',
                                                header.column.id === 'users_count' && 'text-right',
                                            )}
                                        >
                                            {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                        </SortableHeader>
                                    );
                                })}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <tr
                                    key={row.id}
                                    className="group hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors"
                                    data-state={row.getIsSelected() && 'selected'}
                                >
                                    {row.getVisibleCells().map((cell) => {
                                        const isSelectCell = cell.column.id === 'select';
                                        const isActionsCell = cell.column.id === 'actions';
                                        const isStickyLeftCell = cell.column.id === stickyLeftColumnId;
                                        return (
                                            <td
                                                key={cell.id}
                                                data-state={row.getIsSelected() ? 'selected' : undefined}
                                                className={cn(
                                                    cellPaddingClass,
                                                    'align-middle [&:has([role=checkbox])]:pr-0',
                                                    isSelectCell &&
                                                        'bg-background/95 supports-[backdrop-filter]:bg-background/80 hover:bg-muted/50 data-[state=selected]:bg-muted sticky left-0 z-10 w-[48px] max-w-[48px] min-w-[48px] border-r backdrop-blur-sm',
                                                    isStickyLeftCell &&
                                                        (hasSelection
                                                            ? 'bg-background/95 supports-[backdrop-filter]:bg-background/80 hover:bg-muted/50 data-[state=selected]:bg-muted sticky left-[48px] z-10 border-r backdrop-blur-sm'
                                                            : 'bg-background/95 supports-[backdrop-filter]:bg-background/80 hover:bg-muted/50 data-[state=selected]:bg-muted sticky left-0 z-10 border-r backdrop-blur-sm'),
                                                    isActionsCell &&
                                                        'bg-background/95 supports-[backdrop-filter]:bg-background/80 hover:bg-muted/50 data-[state=selected]:bg-muted sticky right-0 z-10 border-l backdrop-blur-sm',
                                                )}
                                            >
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={tableColumns.length} className="h-24 text-center">
                                    {emptyState || 'No hay resultados.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-between px-2">
                <div className="flex items-center space-x-6 lg:space-x-8">
                    <div className="flex items-center space-x-2">
                        <p className="text-sm font-medium">Filas por página</p>
                        <Select value={`${pageSize}`} onValueChange={(value) => onPageSizeChange(Number(value))}>
                            <SelectTrigger className="h-8 w-[70px]">
                                <SelectValue placeholder={pageSize} />
                            </SelectTrigger>
                            <SelectContent side="top">
                                {DEFAULT_PAGE_SIZES.map((size) => (
                                    <SelectItem key={size} value={`${size}`}>
                                        {size}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="text-muted-foreground flex items-center text-sm">
                        {rowCount > 0
                            ? (() => {
                                  const visible = table.getRowModel().rows.length;
                                  const from = Math.min(pageIndex * pageSize + 1, rowCount);
                                  const to = Math.min(pageIndex * pageSize + visible, rowCount);
                                  return (
                                      <>
                                          Mostrando {from} a {to} de {rowCount} registros
                                      </>
                                  );
                              })()
                            : 'Sin registros'}
                    </div>
                    <div className="flex w-[100px] items-center justify-center text-sm font-medium">
                        Página {pageIndex + 1} de {Math.max(pageCount, 1)}
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button variant="outline" className="hidden h-8 w-8 p-0 lg:flex" onClick={() => onPageChange(0)} disabled={pageIndex === 0}>
                            <span className="sr-only">Ir a la primera página</span>
                            <ChevronsLeft className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" className="h-8 w-8 p-0" onClick={() => onPageChange(pageIndex - 1)} disabled={pageIndex === 0}>
                            <span className="sr-only">Ir a la página anterior</span>
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => onPageChange(pageIndex + 1)}
                            disabled={pageIndex >= pageCount - 1}
                        >
                            <span className="sr-only">Ir a la página siguiente</span>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            className="hidden h-8 w-8 p-0 lg:flex"
                            onClick={() => onPageChange(pageCount - 1)}
                            disabled={pageIndex >= pageCount - 1}
                        >
                            <span className="sr-only">Ir a la última página</span>
                            <ChevronsRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                <div className="text-muted-foreground text-xs">{selectedCount > 0 && `${selectedCount} de ${rowCount} fila(s) seleccionada(s).`}</div>
            </div>
        </div>
    );
}
