import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { SectionNav } from '@/components/show-base/SectionNav';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useShow } from '@/hooks/use-show';
import AppLayout from '@/layouts/app-layout';
import { resourceCrumbs } from '@/lib/breadcrumbs';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    ChevronDown,
    ChevronUp,
    Database,
    FileText,
    Hash,
    Key,
    Lock,
    Pencil,
    Power,
    Search,
    Settings,
    Shield,
    Trash2,
    Users,
} from 'lucide-react';
import type React from 'react';
import { useCallback, useMemo, useState } from 'react';

interface Permission {
    id: number;
    name: string;
    description?: string;
    guard_name?: string;
    created_at?: string;
}

interface Role {
    id: number;
    name: string;
    guard_name: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    permissions_count?: number;
    users_count?: number;
    permissions?: Permission[];
}

interface RoleShowProps extends PageProps {
    item: Role;
    meta: {
        loaded_relations?: string[];
        loaded_counts?: string[];
        appended?: string[];
    };
    auth?: {
        can?: Record<string, boolean>;
        user?: {
            id: number;
            name: string;
        };
    };
}

export default function RoleShow({ item: initialItem, meta: initialMeta, auth }: RoleShowProps) {
    const { item, meta, loading, activeTab, setActiveTab, loadPart } = useShow<Role>({
        endpoint: `/roles/${initialItem.id}`,
        initialItem,
        initialMeta,
    });

    // no local state needed when using ConfirmAlert trigger

    // Search/filter and accordion expansion state for permissions UI
    const [permSearch, setPermSearch] = useState('');
    const [expandedGroups, setExpandedGroups] = useState<string[]>([]);

    // Load permissions when tab is activated
    const handleTabChange = useCallback(
        (value: string) => {
            setActiveTab(value);

            if (value === 'permissions' && !meta.loaded_relations?.includes('permissions')) {
                loadPart({
                    with: ['permissions'],
                    withCount: ['permissions'],
                });
            }
        },
        [meta.loaded_relations, loadPart, setActiveTab],
    );

    // Sections for navigation ("Metadatos" vive dentro de Vista General)
    const sections = useMemo(
        () => [
            { id: 'overview', title: 'Vista General' },
            { id: 'permissions', title: 'Permisos' },
        ],
        [],
    );

    // Icon mapping for permission categories (mirrors RoleForm)
    const categoryIcons: Record<string, React.ElementType> = useMemo(
        () => ({
            roles: Shield,
            users: Users,
            settings: Settings,
            permissions: Lock,
            audit: FileText,
            default: Database,
        }),
        [],
    );

    const categoryIconColors: Record<string, string> = useMemo(
        () => ({
            roles: 'text-blue-500 dark:text-blue-400',
            users: 'text-purple-500 dark:text-purple-400',
            settings: 'text-green-600 dark:text-green-400',
            permissions: 'text-amber-500 dark:text-amber-400',
            audit: 'text-rose-500 dark:text-rose-400',
            default: 'text-slate-500 dark:text-slate-400',
        }),
        [],
    );

    const getCategoryIcon = useCallback((category: string) => categoryIcons[category.toLowerCase()] || categoryIcons.default, [categoryIcons]);
    const getCategoryColor = useCallback(
        (category: string) => categoryIconColors[category.toLowerCase()] || categoryIconColors.default,
        [categoryIconColors],
    );

    // Category label translations (Spanish)
    const categoryLabels: Record<string, string> = {
        settings: 'Configuración',
        users: 'Usuarios',
    };
    const getCategoryLabel = (category: string) => {
        const key = category.toLowerCase();
        return categoryLabels[key] ?? category;
    };

    // Group permissions (assigned to role) by category with client-side search
    const groupedPermissions = useMemo(() => {
        const assigned = (item.permissions || []).filter((p) =>
            permSearch.trim() === '' ? true : ((p.description ?? p.name) as string).toLowerCase().includes(permSearch.toLowerCase()),
        );

        const groups = new Map<string, Permission[]>();
        assigned.forEach((permission) => {
            const category = permission.name.split('.')[0] || 'general';
            if (!groups.has(category)) groups.set(category, []);
            groups.get(category)!.push(permission);
        });

        return Array.from(groups.entries())
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([category, perms]) => ({
                category,
                permissions: perms.sort((a, b) => ((a.description ?? a.name) as string).localeCompare((b.description ?? b.name) as string)),
                icon: getCategoryIcon(category),
                color: getCategoryColor(category),
                totalCount: perms.length,
            }));
    }, [item.permissions, permSearch, getCategoryIcon, getCategoryColor]);

    const handleExpandAll = () => setExpandedGroups(groupedPermissions.map((g) => g.category));
    const handleCollapseAll = () => setExpandedGroups([]);

    // No breadcrumbs memo: render directly with UI components to match index design

    // Format date
    const formatDate = (date: string | null) => {
        if (!date) return '—';
        try {
            return new Date(date).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        } catch {
            return '—';
        }
    };

    return (
        <AppLayout breadcrumbs={resourceCrumbs('roles', 'show', { id: item.id, name: item.name })}>
            <Head title={`Rol: ${item.name}`} />

            <ShowLayout
                header={
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-4">
                            <Link href="/roles" className="text-muted-foreground hover:text-foreground transition-colors">
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                            <div>
                                <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                                    <Shield className="h-6 w-6 text-blue-500 dark:text-blue-400" />
                                    {item.name}
                                </h1>
                            </div>
                        </div>
                    </div>
                }
                actions={
                    auth?.can && (auth.can['roles.update'] || auth.can['roles.delete']) ? (
                        <div className="flex gap-2">
                            {auth.can['roles.update'] && (
                                <Button onClick={() => router.visit(`/roles/${item.id}/edit`)}>
                                    <Pencil className="h-4 w-4" />
                                    Editar
                                </Button>
                            )}
                            {auth.can['roles.delete'] && (
                                <ConfirmAlert
                                    trigger={
                                        <Button variant="destructive" type="button">
                                            <Trash2 className="h-4 w-4" />
                                            Eliminar
                                        </Button>
                                    }
                                    title="Eliminar Rol"
                                    description={`¿Está seguro de eliminar el rol "${item.name}"? Esta acción no se puede deshacer.`}
                                    confirmLabel="Eliminar"
                                    onConfirm={async () => {
                                        await new Promise<void>((resolve, reject) => {
                                            router.delete(`/roles/${item.id}`, {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    resolve();
                                                    router.visit('/roles');
                                                },
                                                onError: () => reject(new Error('role_delete_failed')),
                                            });
                                        });
                                    }}
                                    toastMessages={{
                                        loading: `Eliminando "${item.name}"…`,
                                        success: 'Rol eliminado',
                                        error: 'No se pudo eliminar el rol',
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
                                        <Power
                                            className={'mr-1 inline h-4 w-4 ' + (item.is_active ? 'text-success' : 'text-error')}
                                            aria-hidden="true"
                                        />
                                        Estado
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={item.is_active ? 'success' : 'error'} className="font-medium">
                                            {item.is_active ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground flex items-center text-sm">
                                        <Key className="mr-1 inline h-4 w-4 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                                        Guard
                                    </span>
                                    <span className="text-sm font-medium">{item.guard_name}</span>
                                </div>
                                {item.permissions_count !== undefined && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground flex items-center text-sm">
                                            <Key className="mr-1 inline h-4 w-4 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                                            Permisos
                                        </span>
                                        <span className="text-sm font-medium">{item.permissions_count}</span>
                                    </div>
                                )}
                                {item.users_count !== undefined && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground flex items-center text-sm">
                                            <Users className="mr-1 inline h-4 w-4 text-purple-500 dark:text-purple-400" aria-hidden="true" />
                                            Usuarios
                                        </span>
                                        <span className="text-sm font-medium">{item.users_count}</span>
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
                        <TabsTrigger value="permissions">
                            Permisos
                            {item.permissions_count !== undefined && (
                                <Badge variant="secondary" className="ml-2">
                                    {item.permissions_count}
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
                                                <Shield className="mr-1 inline h-4 w-4 text-blue-500 dark:text-blue-400" />
                                                Nombre
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.name}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Key className="mr-1 inline h-4 w-4 text-amber-500 dark:text-amber-400" />
                                                Guard
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.guard_name}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Power
                                                    className={'mr-1 inline h-4 w-4 ' + (item.is_active ? 'text-success' : 'text-error')}
                                                    aria-hidden="true"
                                                />
                                                Estado
                                            </dt>
                                            <dd className="mt-1">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={'h-2 w-2 shrink-0 rounded-full ' + (item.is_active ? 'bg-success' : 'bg-error')}
                                                        aria-label={item.is_active ? 'Activo' : 'Inactivo'}
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
                                                <Key className="mr-1 inline h-4 w-4 text-amber-500 dark:text-amber-400" />
                                                Total de Permisos
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.permissions_count || 0}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Users className="mr-1 inline h-4 w-4 text-purple-500 dark:text-purple-400" />
                                                Usuarios Asignados
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.users_count || 0}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>
                    </TabsContent>

                    <TabsContent value="permissions" className="space-y-6">
                        <ShowSection id="permissions" title="Permisos Asignados" loading={loading && activeTab === 'permissions'}>
                            {loading && activeTab === 'permissions' ? (
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            {[...Array(6)].map((_, i) => (
                                                <div key={i} className="flex items-center gap-2">
                                                    <Skeleton className="h-3 w-3 rounded" />
                                                    <Skeleton className="h-4 w-32" />
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : item.permissions && item.permissions.length > 0 ? (
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="space-y-6">
                                            {/* Search and controls */}
                                            <div className="sticky top-0 z-10 -mx-4 -mt-4 bg-white p-4 dark:bg-gray-800">
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                        <Badge variant="secondary" className="rounded-full px-2.5 py-0.5 text-xs font-medium">
                                                            {groupedPermissions.reduce((acc, g) => acc + g.totalCount, 0)}
                                                        </Badge>
                                                        <span>permisos</span>
                                                        <span aria-hidden="true">·</span>
                                                        <Button type="button" variant="ghost" size="sm" onClick={handleExpandAll} className="gap-1">
                                                            <ChevronDown className="h-3 w-3" />
                                                            Expandir todos
                                                        </Button>
                                                        <span aria-hidden="true">·</span>
                                                        <Button type="button" variant="ghost" size="sm" onClick={handleCollapseAll} className="gap-1">
                                                            <ChevronUp className="h-3 w-3" />
                                                            Colapsar todos
                                                        </Button>
                                                    </div>
                                                    <div className="relative w-full sm:w-72">
                                                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                                        <Input
                                                            placeholder="Buscar permisos"
                                                            value={permSearch}
                                                            onChange={(e) => setPermSearch(e.target.value)}
                                                            className="pl-9"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Grouped permissions accordion */}
                                            <Accordion
                                                type="multiple"
                                                value={expandedGroups}
                                                onValueChange={setExpandedGroups}
                                                className="w-full space-y-2"
                                            >
                                                {groupedPermissions.map((group) => {
                                                    const Icon = group.icon as React.ElementType;
                                                    return (
                                                        <AccordionItem
                                                            key={group.category}
                                                            value={group.category}
                                                            className="bg-card rounded-xl border px-4 shadow-sm"
                                                        >
                                                            <AccordionTrigger className="hover:no-underline">
                                                                <div className="flex w-full items-center justify-between pr-4">
                                                                    <div className="flex items-center gap-3">
                                                                        <Icon className={`h-4 w-4 ${group.color}`} />
                                                                        <span className="text-sm font-medium capitalize">
                                                                            {getCategoryLabel(group.category)}
                                                                        </span>
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="ml-2 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                                        >
                                                                            {group.totalCount}
                                                                        </Badge>
                                                                    </div>
                                                                </div>
                                                            </AccordionTrigger>
                                                            <AccordionContent>
                                                                <div className="grid gap-3 pb-2 sm:grid-cols-2">
                                                                    {group.permissions.map((permission) => (
                                                                        <div
                                                                            key={permission.id}
                                                                            className="hover:bg-muted flex items-start gap-2 rounded-md p-2"
                                                                        >
                                                                            <Key className="mt-0.5 h-3 w-3 flex-shrink-0 text-amber-500 dark:text-amber-400" />
                                                                            <span className="text-sm leading-relaxed">
                                                                                {(permission.description ?? permission.name) as string}
                                                                            </span>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </AccordionContent>
                                                        </AccordionItem>
                                                    );
                                                })}
                                            </Accordion>
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : (
                                <Card className="border-dashed">
                                    <CardContent className="pt-6">
                                        <div className="mx-auto max-w-prose py-8 text-center leading-relaxed">
                                            <Key className="text-muted-foreground/30 mx-auto mb-3 h-12 w-12" />
                                            <p className="text-muted-foreground mb-1 text-sm font-medium">Sin permisos asignados</p>
                                            <p className="text-muted-foreground text-xs">Este rol no tiene permisos asignados actualmente</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </ShowSection>
                    </TabsContent>
                </Tabs>
            </ShowLayout>

            {/* ConfirmAlert handled inline as trigger above */}
        </AppLayout>
    );
}
