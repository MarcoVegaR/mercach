import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { SectionNav } from '@/components/show-base/SectionNav';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useShow } from '@/hooks/use-show';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Building2, Calendar, Hash, Pencil, Store, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

interface LocalRef {
    id: number;
    code: string;
}

interface Item {
    id: number | string;
    code?: string | null;
    name?: string | null;
    description?: string | null;
    is_active?: boolean | null;
    locals_count?: number | null;
    created_at?: string | null;
    updated_at?: string | null;
    locals?: LocalRef[] | null;
}

interface ShowProps extends PageProps {
    item: Item;
    meta: {
        loaded_relations?: string[];
        loaded_counts?: string[];
        appended?: string[];
    };
    hasEditRoute?: boolean;
    auth?: {
        can?: Record<string, boolean>;
        user?: { id: number; name: string };
    };
}

export default function ShowPage({ item: initialItem, meta: initialMeta, hasEditRoute, auth }: ShowProps) {
    const { item, meta, loading, activeTab, setActiveTab, loadPart } = useShow<Item>({
        endpoint: `/catalogs/local-type/${initialItem.id}`,
        initialItem,
        initialMeta,
    });

    const [localSearch, setLocalSearch] = useState('');

    // Load locals when tab is activated
    const handleTabChange = useCallback(
        (value: string) => {
            setActiveTab(value);

            if (value === 'locals' && !meta.loaded_relations?.includes('locals')) {
                loadPart({ with: ['locals'], withCount: ['locals'] });
            }
        },
        [meta.loaded_relations, loadPart, setActiveTab],
    );

    const sections = useMemo(
        () => [
            { id: 'overview', title: 'Vista General' },
            { id: 'locals', title: 'Locales' },
        ],
        [],
    );

    const formatDate = (date?: string | null) => {
        if (!date) return '—';
        try {
            return new Date(date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
        } catch {
            return '—';
        }
    };

    const localsFiltered = useMemo(() => {
        const locals = item.locals ?? [];
        const q = localSearch.trim().toLowerCase();
        if (!q) return locals;
        return locals.filter((l) => l.code.toLowerCase().includes(q));
    }, [item.locals, localSearch]);

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Tipos de local', href: '/catalogs/local-type' },
        { title: String(item.name ?? item.code ?? item.id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tipo de local: ${String(item.name ?? item.code ?? item.id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link href="/catalogs/local-type" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                                <Building2 className="h-6 w-6 text-blue-500 dark:text-blue-400" />
                                {String(item.name ?? item.code ?? item.id)}
                            </h1>
                        </div>
                    </div>
                }
                actions={
                    auth?.can && (auth.can['catalogs.local-type.update'] || auth.can['catalogs.local-type.delete']) ? (
                        <div className="flex gap-2">
                            {auth.can['catalogs.local-type.update'] && hasEditRoute && (
                                <Button onClick={() => router.visit(`/catalogs/local-type/${item.id}/edit`)}>
                                    <Pencil className="h-4 w-4" />
                                    Editar
                                </Button>
                            )}
                            {auth.can['catalogs.local-type.delete'] && (
                                <ConfirmAlert
                                    trigger={
                                        <Button variant="destructive" type="button">
                                            <Trash2 className="h-4 w-4" />
                                            Eliminar
                                        </Button>
                                    }
                                    title="Eliminar Tipo de Local"
                                    description={`¿Está seguro de eliminar "${String(item.name ?? item.code ?? item.id)}"? Esta acción no se puede deshacer.`}
                                    confirmLabel="Eliminar"
                                    onConfirm={async () => {
                                        await new Promise<void>((resolve, reject) => {
                                            router.delete(`/catalogs/local-type/${item.id}`, {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    resolve();
                                                    router.visit('/catalogs/local-type');
                                                },
                                                onError: () => reject(new Error('delete_failed')),
                                            });
                                        });
                                    }}
                                    toastMessages={{
                                        loading: `Eliminando "${String(item.name ?? item.code ?? item.id)}"…`,
                                        success: 'Tipo de local eliminado',
                                        error: 'No se pudo eliminar el tipo de local',
                                    }}
                                />
                            )}
                        </div>
                    ) : null
                }
                aside={
                    <>
                        {/* Summary Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resumen</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground flex items-center text-sm">
                                        <Building2 className="mr-1 inline h-4 w-4 text-blue-500 dark:text-blue-400" />
                                        Estado
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={item.is_active ? 'success' : 'error'} className="font-medium">
                                            {item.is_active ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </div>
                                </div>
                                {item.locals_count !== undefined && item.locals_count !== null && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground flex items-center text-sm">
                                            <Store className="mr-1 inline h-4 w-4 text-green-500 dark:text-green-400" />
                                            Locales
                                        </span>
                                        <span className="text-sm font-medium">{item.locals_count}</span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Section Navigation */}
                        <Card className="hidden lg:block">
                            <CardHeader>
                                <CardTitle className="text-base">Secciones</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionNav sections={sections} activeTab={activeTab} />
                            </CardContent>
                        </Card>
                    </>
                }
            >
                {/* Main Content with Tabs */}
                <Tabs value={activeTab} onValueChange={handleTabChange} className="space-y-4">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="overview">Vista General</TabsTrigger>
                        <TabsTrigger value="locals">
                            Locales
                            {item.locals_count !== undefined && item.locals_count !== null && (
                                <Badge variant="secondary" className="ml-2">
                                    {item.locals_count}
                                </Badge>
                            )}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="space-y-6">
                        <ShowSection id="overview" title="Información Básica">
                            <Card>
                                <CardContent className="pt-6">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Hash className="mr-1 inline h-4 w-4 text-gray-500 dark:text-gray-400" />
                                                ID
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.id}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Hash className="mr-1 inline h-4 w-4 text-gray-500 dark:text-gray-400" />
                                                Código
                                            </dt>
                                            <dd className="mt-1 font-mono text-sm">{String(item.code ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Building2 className="mr-1 inline h-4 w-4 text-blue-500 dark:text-blue-400" />
                                                Nombre
                                            </dt>
                                            <dd className="mt-1 text-sm">{String(item.name ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Descripción</dt>
                                            <dd className="mt-1 text-sm">{String(item.description ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Estado</dt>
                                            <dd className="mt-1">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={'h-2 w-2 shrink-0 rounded-full ' + (item.is_active ? 'bg-success' : 'bg-error')}
                                                    />
                                                    <Badge variant={item.is_active ? 'success' : 'error'} className="font-medium">
                                                        {item.is_active ? 'Activo' : 'Inactivo'}
                                                    </Badge>
                                                </div>
                                            </dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>

                        <ShowSection id="metadata" title="Metadatos">
                            <Card>
                                <CardContent className="pt-6">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Calendar className="mr-1 inline h-4 w-4 text-green-500 dark:text-green-400" />
                                                Creado
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(item.created_at)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Calendar className="mr-1 inline h-4 w-4 text-green-500 dark:text-green-400" />
                                                Última Actualización
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(item.updated_at)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Store className="mr-1 inline h-4 w-4 text-green-500 dark:text-green-400" />
                                                Total de Locales
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.locals_count ?? 0}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>
                    </TabsContent>

                    <TabsContent value="locals" className="space-y-6">
                        <ShowSection id="locals" title="Locales Asociados" loading={loading && activeTab === 'locals'}>
                            {loading && activeTab === 'locals' ? (
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            {[...Array(6)].map((_, i) => (
                                                <div key={`skeleton-local-${i}`} className="flex items-center gap-2">
                                                    <Skeleton className="h-3 w-3 rounded" />
                                                    <Skeleton className="h-4 w-32" />
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : item.locals && item.locals.length > 0 ? (
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="space-y-6">
                                            {/* Controls */}
                                            <div className="sticky top-0 z-10 -mx-4 -mt-4 bg-white p-4 dark:bg-gray-800">
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                        <Badge variant="secondary" className="rounded-full px-2.5 py-0.5 text-xs font-medium">
                                                            {item.locals?.length ?? 0}
                                                        </Badge>
                                                        <span>locales</span>
                                                    </div>
                                                    <div className="relative w-full sm:w-72">
                                                        <Input
                                                            placeholder="Buscar locales"
                                                            value={localSearch}
                                                            onChange={(e) => setLocalSearch(e.target.value)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Locals list */}
                                            <div className="flex flex-wrap gap-2">
                                                {localsFiltered.map((l, index) => (
                                                    <Badge key={`local-${l.id}-${index}`} variant="outline" className="font-mono text-xs">
                                                        {l.code}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : (
                                <Card className="border-dashed">
                                    <CardContent className="pt-6">
                                        <div className="mx-auto max-w-prose py-8 text-center leading-relaxed">
                                            <Store className="text-muted-foreground/30 mx-auto mb-3 h-12 w-12" />
                                            <p className="text-muted-foreground mb-1 text-sm font-medium">Sin locales asociados</p>
                                            <p className="text-muted-foreground text-xs">Este tipo de local no tiene locales asociados actualmente</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </ShowSection>
                    </TabsContent>
                </Tabs>
            </ShowLayout>
        </AppLayout>
    );
}
