import AppLogo from '@/components/app-logo';
import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ConfirmWithReasonDialog } from '@/components/dialogs/confirm-with-reason-dialog';
import { ExportDialog, type ExportFormat } from '@/components/dialogs/export-dialog';
import { DataTable } from '@/components/index/DataTable';
import { GlobalActionsMenuBasic } from '@/components/menus/global-actions-menu';
import { RowActionsMenu, RowActionsMenuBasic, type ActionItem } from '@/components/menus/row-actions-menu';
import { StatsCard } from '@/components/stats-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker, type DateRange } from '@/components/ui/date-picker';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { SimpleTooltip } from '@/components/ui/tooltip-simple';
import { exportVisibleAsCSV } from '@/lib/export-from-table';
import { createTableColumn } from '@/lib/table-column-factory';
import { defaultRowIdGetter } from '@/lib/table-ids';
import { toast } from '@/lib/toast';
import { Head, Link } from '@inertiajs/react';
import { ColumnDef, RowSelectionState, SortingState } from '@tanstack/react-table';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import * as React from 'react';
import '../lib/table-column-meta'; // Import for module augmentation
// Demo functions that simulate delete-service behavior for playground
const demoDeleteSingle = async (id: string, name: string) => {
    return toast.promise(
        new Promise<void>((resolve) => {
            setTimeout(() => resolve(), 1500);
        }),
        {
            loading: `Eliminando ${name}...`,
            success: `Usuario ${name} eliminado correctamente`,
            error: 'Error al eliminar usuario',
        },
    );
};

const demoDeleteBulk = async (count: number) => {
    return toast.promise(
        new Promise<void>((resolve) => {
            setTimeout(() => resolve(), 2000);
        }),
        {
            loading: `Eliminando ${count} usuarios...`,
            success: `${count} usuarios eliminados correctamente`,
            error: 'Error al eliminar usuarios seleccionados',
        },
    );
};

const OPTIONS = [
    { value: '1', label: 'Manzana' },
    { value: '2', label: 'Pera' },
    { value: '3', label: 'Banana' },
    { value: '4', label: 'Naranja' },
];

// Mock data for DataTable demo
type MockUser = {
    id: string;
    name: string;
    email: string;
    role: string;
    status: 'active' | 'inactive' | 'pending';
    createdAt: string;
};

const MOCK_USERS: MockUser[] = [
    { id: '1', name: 'Ana García', email: 'ana@example.com', role: 'Admin', status: 'active', createdAt: '2024-01-15' },
    { id: '2', name: 'Carlos López', email: 'carlos@example.com', role: 'Usuario', status: 'active', createdAt: '2024-01-20' },
    { id: '3', name: 'María Rodríguez', email: 'maria@example.com', role: 'Editor', status: 'pending', createdAt: '2024-02-01' },
    { id: '4', name: 'José Martínez', email: 'jose@example.com', role: 'Usuario', status: 'inactive', createdAt: '2024-02-05' },
    { id: '5', name: 'Laura Sánchez', email: 'laura@example.com', role: 'Admin', status: 'active', createdAt: '2024-02-10' },
    { id: '6', name: 'Pedro Gómez', email: 'pedro@example.com', role: 'Usuario', status: 'active', createdAt: '2024-02-15' },
    { id: '7', name: 'Carmen Díaz', email: 'carmen@example.com', role: 'Editor', status: 'pending', createdAt: '2024-02-20' },
    { id: '8', name: 'Miguel Torres', email: 'miguel@example.com', role: 'Usuario', status: 'inactive', createdAt: '2024-02-25' },
];

// Create column helper for MockUser
const userColumn = createTableColumn<MockUser>();

// Base columns without actions (defined outside component)
const baseUserColumns: ColumnDef<MockUser>[] = [
    userColumn.accessor('name', {
        id: 'name',
        header: 'Nombre',
        cell: ({ row }) => <div className="font-medium">{row.getValue('name')}</div>,
        meta: {
            title: 'Nombre completo',
            exportable: true,
            exportHeader: 'Nombre',
            sortable: true,
            filterable: true,
            hideable: true,
            align: 'left',
            format: (value) => String(value),
            requires: ['users.view'],
        },
    }),

    userColumn.accessor('email', {
        id: 'email',
        header: 'Email',
        cell: ({ row }) => <div className="lowercase">{row.getValue('email')}</div>,
        meta: {
            title: 'Correo electrónico',
            exportable: true,
            exportHeader: 'Email',
            sortable: true,
            filterable: true,
            hideable: true,
            align: 'left',
            format: (value) => String(value).toLowerCase(),
            requires: ['users.view'],
        },
    }),

    userColumn.accessor('role', {
        id: 'role',
        header: 'Rol',
        cell: ({ row }) => <Badge variant={row.getValue('role') === 'Admin' ? 'default' : 'secondary'}>{row.getValue('role')}</Badge>,
        meta: {
            title: 'Rol del usuario',
            exportable: true,
            exportHeader: 'Rol',
            sortable: true,
            filterable: true,
            hideable: true,
            align: 'center',
            format: (value) => String(value),
            requires: ['users.view'],
        },
    }),

    userColumn.accessor('status', {
        id: 'status',
        header: 'Estado',
        cell: ({ row }) => {
            const status = row.getValue('status');
            return (
                <Badge variant={status === 'active' ? 'default' : status === 'pending' ? 'secondary' : 'destructive'}>
                    {status === 'active' ? 'Activo' : status === 'pending' ? 'Pendiente' : 'Inactivo'}
                </Badge>
            );
        },
        meta: {
            title: 'Estado del usuario',
            exportable: true,
            exportHeader: 'Estado',
            sortable: true,
            filterable: true,
            hideable: true,
            align: 'center',
            format: (value) => {
                const status = String(value);
                return status === 'active' ? 'Activo' : status === 'pending' ? 'Pendiente' : 'Inactivo';
            },
            requires: ['users.view'],
        },
    }),

    userColumn.accessor('createdAt', {
        id: 'createdAt',
        header: 'Fecha de Creación',
        cell: ({ row }) => {
            const date = new Date(row.getValue('createdAt'));
            return date.toLocaleDateString('es-ES');
        },
        meta: {
            title: 'Fecha de registro',
            exportable: true,
            exportHeader: 'Fecha de Creación',
            sortable: true,
            filterable: true,
            hideable: true,
            align: 'center',
            format: (value) => {
                const date = new Date(String(value));
                return date.toISOString().split('T')[0]; // YYYY-MM-DD format for CSV
            },
            requires: ['users.view'],
        },
    }),
];

export default function Playground() {
    // Combobox single (use empty string for "no selection" to satisfy Combobox types)
    const [single, setSingle] = React.useState<string>('');

    // Combobox multi
    const [multi, setMulti] = React.useState<string[]>([]);

    // Export dialog state (to trigger from GlobalActionsMenu)
    const [exportOpen, setExportOpen] = React.useState(false);
    const globalActionsRef = React.useRef<HTMLButtonElement>(null);

    // Date pickers
    const [dateSingle, setDateSingle] = React.useState<Date | undefined>(undefined);
    const [dateRange, setDateRange] = React.useState<DateRange | undefined>(undefined);

    // DataTable states
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
    const [globalFilter, setGlobalFilter] = React.useState('');

    // Pagination states
    const [pageIndex, setPageIndex] = React.useState(0);
    const [pageSize, setPageSize] = React.useState(10);
    const [columnVisibility, setColumnVisibility] = React.useState({});

    // Delete confirmation state
    const [deleteConfirm, setDeleteConfirm] = React.useState<{
        show: boolean;
        type: 'single' | 'bulk';
        data?: { id: string; name: string };
        count?: number;
    }>({ show: false, type: 'single' });

    // Create actions column inside component to access setDeleteConfirm
    const actionsColumn = React.useMemo(
        (): ColumnDef<MockUser> =>
            userColumn.display({
                id: 'actions',
                header: 'Acciones',
                cell: ({ row }) => {
                    const user = row.original;
                    const actions: ActionItem[] = [
                        {
                            key: 'view',
                            label: 'Ver detalles',
                            icon: Eye,
                            onSelect: () => {
                                toast.info(`Ver detalles de ${user.name}`);
                            },
                        },
                        {
                            key: 'edit',
                            label: 'Editar',
                            icon: Pencil,
                            onSelect: () => {
                                toast.info(`Editar ${user.name}`);
                            },
                        },
                        {
                            key: 'delete',
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => {
                                // Delay to allow dropdown to close and prevent focus conflicts
                                setTimeout(() => {
                                    setDeleteConfirm({
                                        show: true,
                                        type: 'single',
                                        data: { id: user.id, name: user.name },
                                    });
                                }, 100);
                            },
                        },
                    ];

                    return <RowActionsMenu items={actions} />;
                },
                meta: {
                    title: 'Acciones',
                    exportable: false, // Actions shouldn't be exported
                    hideable: false, // Always show actions
                    sortable: false,
                    filterable: false,
                    align: 'center',
                    requires: ['users.delete'], // Require delete permission for this column
                },
            }),
        [setDeleteConfirm],
    );

    // Complete columns array
    const userColumns = React.useMemo(() => [...baseUserColumns, actionsColumn], [actionsColumn]);

    // Mock server-side logic - simulate sorting, filtering, and pagination
    const processedUsers = React.useMemo(() => {
        let filteredUsers = [...MOCK_USERS];

        // Apply global filter (search)
        if (globalFilter) {
            const searchTerm = globalFilter.toLowerCase();
            filteredUsers = filteredUsers.filter(
                (user) =>
                    user.name.toLowerCase().includes(searchTerm) ||
                    user.email.toLowerCase().includes(searchTerm) ||
                    user.role.toLowerCase().includes(searchTerm) ||
                    user.status.toLowerCase().includes(searchTerm),
            );
        }

        // Apply sorting
        if (sorting.length > 0) {
            const { id, desc } = sorting[0]; // Take first sort
            filteredUsers.sort((a, b) => {
                const aValue = a[id as keyof MockUser] as string;
                const bValue = b[id as keyof MockUser] as string;

                if (aValue < bValue) return desc ? 1 : -1;
                if (aValue > bValue) return desc ? -1 : 1;
                return 0;
            });
        }

        return filteredUsers;
    }, [globalFilter, sorting]);

    // Get current page data
    const currentPageUsers = React.useMemo(() => {
        const startIndex = pageIndex * pageSize;
        return processedUsers.slice(startIndex, startIndex + pageSize);
    }, [processedUsers, pageIndex, pageSize]);

    // Reset page when filters change
    React.useEffect(() => {
        setPageIndex(0);
    }, [globalFilter, sorting]);

    return (
        <>
            <Head title="Playground" />
            <header className="border-border/50 bg-background/60 supports-[backdrop-filter]:bg-background/70 sticky top-0 z-10 border-b backdrop-blur">
                <div className="container mx-auto flex items-center justify-between px-6 py-4">
                    <div className="flex items-center gap-3">
                        <AppLogo />
                        <span className="text-muted-foreground hidden text-sm sm:inline">Playground</span>
                    </div>
                    <nav className="flex items-center gap-3">
                        <Button asChild variant="ghost">
                            <Link href="/">Volver al inicio</Link>
                        </Button>
                    </nav>
                </div>
            </header>
            <div className="container mx-auto max-w-6xl space-y-8 p-6">
                {/* Header / Hero */}
                <Card className="bg-card/70 supports-[backdrop-filter]:bg-card/60 relative overflow-hidden border shadow-sm">
                    <div className="pointer-events-none absolute inset-0 -z-10">
                        <PlaceholderPattern className="text-foreground/10 [&_path]:stroke-current" />
                    </div>
                    <CardHeader>
                        <CardTitle className="text-2xl">Playground UI</CardTitle>
                        <CardDescription>
                            Explora componentes interactivos del diseño: formularios, menús, diálogos, métricas y tablas.
                        </CardDescription>
                    </CardHeader>
                </Card>

                {/* Combobox */}
                <Card>
                    <CardHeader>
                        <CardTitle>Combobox</CardTitle>
                        <CardDescription>Entradas con búsqueda, selección única y múltiple.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        <div className="space-y-2">
                            <h3 className="text-sm font-medium">Single</h3>
                            <Combobox
                                id="cb-single"
                                options={OPTIONS}
                                value={single}
                                onChange={(v) => setSingle(typeof v === 'string' ? v : '')}
                                placeholder="Selecciona una fruta…"
                                searchPlaceholder="Buscar fruta…"
                                allowCreate
                            />
                            <p className="text-muted-foreground text-sm">Valor: {single || '—'}</p>
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-sm font-medium">Multiple</h3>
                            <Combobox
                                id="cb-multi"
                                options={OPTIONS}
                                value={multi}
                                onChange={(v) => setMulti(Array.isArray(v) ? v : [])}
                                multiple
                                placeholder="Selecciona frutas…"
                                searchPlaceholder="Buscar frutas…"
                                allowCreate
                            />
                            <p className="text-muted-foreground text-sm">Valores: {multi.length ? multi.join(', ') : '—'}</p>
                        </div>
                    </CardContent>
                </Card>

                {/* Date Pickers */}
                <Card>
                    <CardHeader>
                        <CardTitle>Date Picker</CardTitle>
                        <CardDescription>Controles para fecha única y rango con presets y zona horaria.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-start gap-6">
                            <div className="space-y-2">
                                <h3 className="text-muted-foreground text-sm font-medium">Fecha única</h3>
                                <DatePicker
                                    id="dp-single"
                                    mode="single"
                                    value={dateSingle}
                                    onChange={(v) => setDateSingle(v as Date | undefined)}
                                    placeholder="Selecciona una fecha"
                                    timezoneHint="Zona horaria del navegador"
                                />
                                <p className="text-muted-foreground text-sm">Valor: {dateSingle ? dateSingle.toLocaleDateString() : '—'}</p>
                            </div>
                            <div className="space-y-2">
                                <h3 className="text-muted-foreground text-sm font-medium">Rango de fechas</h3>
                                <DatePicker
                                    id="dp-range"
                                    mode="range"
                                    value={dateRange}
                                    onChange={(v) => setDateRange(v as DateRange | undefined)}
                                    presets={[
                                        { label: 'Últimos 7 días', getValue: () => ({ from: new Date(Date.now() - 6 * 86400000), to: new Date() }) },
                                        {
                                            label: 'Este mes',
                                            getValue: () => {
                                                const now = new Date();
                                                const start = new Date(now.getFullYear(), now.getMonth(), 1);
                                                const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                                                return { from: start, to: end };
                                            },
                                        },
                                    ]}
                                    placeholder="Selecciona un rango"
                                />
                                <p className="text-muted-foreground text-sm">
                                    Valor: {dateRange?.from ? dateRange.from.toLocaleDateString() : '—'} –{' '}
                                    {dateRange?.to ? dateRange.to.toLocaleDateString() : '—'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Menús y Diálogos */}
                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Menús</CardTitle>
                            <CardDescription>Acciones globales y por fila con accesibilidad y atajos.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-3">
                                <RowActionsMenuBasic
                                    onEdit={() => toast.success('Editar (demo)')}
                                    onDelete={() => toast.success('Eliminar (demo)')}
                                />
                                <GlobalActionsMenuBasic
                                    onExport={() => setExportOpen(true)}
                                    onDeleteSelected={() => toast.success('Eliminar seleccionados (demo)')}
                                    ref={globalActionsRef}
                                />
                            </div>
                            <ExportDialog
                                open={exportOpen}
                                onOpenChange={setExportOpen}
                                title="Exportar datos"
                                onExport={async (format: ExportFormat) => {
                                    await new Promise((r) => setTimeout(r, 1000));
                                    console.log('Export format:', format);
                                }}
                                toastMessages={{
                                    loading: 'Exportando…',
                                    success: 'Export listo',
                                    error: 'Error al exportar',
                                }}
                                focusAfterClose={globalActionsRef}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Diálogos</CardTitle>
                            <CardDescription>Confirmaciones accesibles con feedback y validación.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap items-center gap-3">
                                <ConfirmAlert
                                    trigger={<Button variant="destructive">Eliminar simple…</Button>}
                                    title="¿Eliminar elemento?"
                                    description="Esta acción no se puede deshacer."
                                    confirmLabel="Eliminar"
                                    onConfirm={async () => {
                                        await new Promise((r) => setTimeout(r, 1000));
                                    }}
                                    toastMessages={{
                                        loading: 'Eliminando…',
                                        success: 'Elemento eliminado',
                                        error: 'Error al eliminar',
                                    }}
                                />

                                <ConfirmWithReasonDialog
                                    trigger={<Button variant="outline">Eliminar con motivo…</Button>}
                                    title="Eliminar elemento con motivo"
                                    description="Indica el motivo de la eliminación."
                                    confirmLabel="Eliminar"
                                    validateReason={(r) => (r.trim().length < 3 ? 'Mínimo 3 caracteres' : null)}
                                    onConfirm={async (reason) => {
                                        await new Promise((r) => setTimeout(r, 1200));
                                        console.log('Motivo:', reason);
                                    }}
                                    toastMessages={{
                                        loading: 'Procesando…',
                                        success: () => 'Listo',
                                        error: () => 'Error',
                                    }}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Stats & Tooltip */}
                <Card>
                    <CardHeader>
                        <CardTitle>Métricas & Tooltip</CardTitle>
                        <CardDescription>Tarjetas con variación de tendencia y ejemplo de ayuda contextual.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <StatsCard
                                title="Ingresos"
                                value="$24.3k"
                                delta={12.3}
                                intent="success"
                                deltaDirection="auto"
                                subtitle="Últimos 30 días"
                            />
                            <StatsCard
                                title="Suscripciones"
                                value="1,204"
                                delta={-4.5}
                                intent="error"
                                deltaDirection="auto"
                                subtitle="vs. período anterior"
                            />
                        </div>
                        <div>
                            <SimpleTooltip content="Este botón tiene una ayuda contextual" side="top">
                                <Button variant="outline">Hover o foco para ver Tooltip</Button>
                            </SimpleTooltip>
                        </div>
                    </CardContent>
                </Card>

                {/* DataTable Demo */}
                <Card>
                    <CardHeader>
                        <CardTitle>DataTable — TanStack v8</CardTitle>
                        <CardDescription>Tabla con búsqueda global, selección, exportación y acciones contextualizadas.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={userColumns}
                            data={currentPageUsers}
                            rowCount={processedUsers.length}
                            pageIndex={pageIndex}
                            pageSize={pageSize}
                            onPageChange={setPageIndex}
                            onPageSizeChange={setPageSize}
                            sorting={sorting}
                            onSortingChange={setSorting}
                            rowSelection={rowSelection}
                            onRowSelectionChange={setRowSelection}
                            columnVisibility={columnVisibility}
                            onColumnVisibilityChange={setColumnVisibility}
                            globalFilter={globalFilter}
                            onGlobalFilterChange={(value) => setGlobalFilter(value ?? '')}
                            getRowId={defaultRowIdGetter}
                            permissions={{
                                canCreate: true,
                                canEdit: true,
                                canDelete: true,
                                canExport: true,
                                canBulkDelete: true,
                            }}
                            onExportClick={(_format, table) => {
                                // Demo: Export using column meta-driven export
                                exportVisibleAsCSV(table, 'usuarios-filtrados.csv');
                            }}
                            onDeleteSelectedClick={() => {
                                const selectedIds = Object.keys(rowSelection).filter((id) => rowSelection[id]);
                                if (selectedIds.length > 0) {
                                    setDeleteConfirm({
                                        show: true,
                                        type: 'bulk',
                                        count: selectedIds.length,
                                    });
                                } else {
                                    toast.error('Selecciona al menos un usuario para eliminar');
                                }
                            }}
                            emptyState={
                                <div className="py-8 text-center">
                                    <p className="text-muted-foreground">No hay usuarios para mostrar</p>
                                    <Button variant="outline" className="mt-2">
                                        Agregar usuario
                                    </Button>
                                </div>
                            }
                        />
                    </CardContent>
                </Card>

                {/* Delete Confirmation Dialogs */}
                <ConfirmAlert
                    open={deleteConfirm.show}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeleteConfirm({ show: false, type: 'single' });
                        }
                    }}
                    title={deleteConfirm.type === 'single' ? 'Eliminar Usuario' : 'Eliminar Usuarios Seleccionados'}
                    description={
                        deleteConfirm.type === 'single' && deleteConfirm.data
                            ? `¿Estás seguro de eliminar a "${deleteConfirm.data.name}"? Esta acción no se puede deshacer.`
                            : `¿Estás seguro de eliminar ${deleteConfirm.count} usuarios seleccionados? Esta acción no se puede deshacer.`
                    }
                    confirmLabel="Eliminar"
                    onConfirm={async () => {
                        if (deleteConfirm.type === 'single' && deleteConfirm.data) {
                            await demoDeleteSingle(deleteConfirm.data.id, deleteConfirm.data.name);
                        } else if (deleteConfirm.type === 'bulk' && deleteConfirm.count) {
                            await demoDeleteBulk(deleteConfirm.count);
                            setRowSelection({});
                        }
                        setDeleteConfirm({ show: false, type: 'single' });
                    }}
                />
            </div>
        </>
    );
}
