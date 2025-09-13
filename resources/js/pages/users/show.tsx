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
import { resourceCrumbs } from '@/lib/breadcrumbs';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, Hash, Mail, Power, Shield, Trash2, User as UserIcon, Users } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

interface UserRoleRef {
    id: number;
    name: string;
}

interface UserItem {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles_count?: number | null;
    created_at: string | null;
    updated_at: string | null;
    roles?: UserRoleRef[] | null;
}

interface UserShowProps extends PageProps {
    item: UserItem;
    meta: {
        loaded_relations?: string[];
        loaded_counts?: string[];
        appended?: string[];
    };
    auth?: {
        can?: Record<string, boolean>;
        user?: { id: number; name: string };
    };
}

export default function UserShow({ item: initialItem, meta: initialMeta, auth }: UserShowProps) {
    const { item, meta, loading, activeTab, setActiveTab, loadPart } = useShow<UserItem>({
        endpoint: `/users/${initialItem.id}`,
        initialItem,
        initialMeta,
    });

    const [roleSearch, setRoleSearch] = useState('');

    // Load roles when tab is activated
    const handleTabChange = useCallback(
        (value: string) => {
            setActiveTab(value);

            if (value === 'roles' && !meta.loaded_relations?.includes('roles')) {
                loadPart({ with: ['roles'], withCount: ['roles'] });
            }
        },
        [meta.loaded_relations, loadPart, setActiveTab],
    );

    const sections = useMemo(
        () => [
            { id: 'overview', title: 'Vista General' },
            { id: 'roles', title: 'Roles' },
        ],
        [],
    );

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

    const rolesFiltered = useMemo(() => {
        const roles = item.roles ?? [];
        const q = roleSearch.trim().toLowerCase();
        if (!q) return roles;
        return roles.filter((r) => r.name.toLowerCase().includes(q));
    }, [item.roles, roleSearch]);

    return (
        <AppLayout breadcrumbs={resourceCrumbs('users', 'show', { id: item.id, name: item.name })}>
            <Head title={`Usuario: ${item.name}`} />

            <ShowLayout
                header={
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-4">
                            <Link href="/users" className="text-muted-foreground hover:text-foreground transition-colors">
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                            <div>
                                <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                                    <UserIcon className="h-6 w-6 text-purple-500 dark:text-purple-400" />
                                    {item.name}
                                </h1>
                            </div>
                        </div>
                    </div>
                }
                actions={
                    auth?.can && (auth.can['users.update'] || auth.can['users.delete']) ? (
                        <div className="flex gap-2">
                            {auth.can['users.update'] && (
                                <Button onClick={() => router.visit(`/users/${item.id}/edit`)}>
                                    <Shield className="h-4 w-4" />
                                    Editar
                                </Button>
                            )}
                            {auth.can['users.delete'] && (
                                <ConfirmAlert
                                    trigger={
                                        <Button variant="destructive" type="button">
                                            <Trash2 className="h-4 w-4" />
                                            Eliminar
                                        </Button>
                                    }
                                    title="Eliminar Usuario"
                                    description={`¿Está seguro de eliminar el usuario "${item.name}"? Esta acción no se puede deshacer.`}
                                    confirmLabel="Eliminar"
                                    onConfirm={async () => {
                                        await new Promise<void>((resolve, reject) => {
                                            router.delete(`/users/${item.id}`, {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    resolve();
                                                    router.visit('/users');
                                                },
                                                onError: () => reject(new Error('user_delete_failed')),
                                            });
                                        });
                                    }}
                                    toastMessages={{
                                        loading: `Eliminando "${item.name}"…`,
                                        success: 'Usuario eliminado',
                                        error: 'No se pudo eliminar el usuario',
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
                                        <Mail className="mr-1 inline h-4 w-4 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                                        Email
                                    </span>
                                    <span className="text-sm font-medium">{item.email}</span>
                                </div>
                                {item.roles_count !== undefined && item.roles_count !== null && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground flex items-center text-sm">
                                            <Users className="mr-1 inline h-4 w-4 text-purple-500 dark:text-purple-400" aria-hidden="true" />
                                            Roles
                                        </span>
                                        <span className="text-sm font-medium">{item.roles_count}</span>
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
                        <TabsTrigger value="roles">
                            Roles
                            {item.roles_count !== undefined && item.roles_count !== null && (
                                <Badge variant="secondary" className="ml-2">
                                    {item.roles_count}
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
                                                <UserIcon className="mr-1 inline h-4 w-4 text-purple-500 dark:text-purple-400" />
                                                Nombre
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.name}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Mail className="mr-1 inline h-4 w-4 text-amber-500 dark:text-amber-400" />
                                                Email
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.email}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Power className={'mr-1 inline h-4 w-4 ' + (item.is_active ? 'text-success' : 'text-error')} />
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
                                                <Users className="mr-1 inline h-4 w-4 text-purple-500 dark:text-purple-400" />
                                                Total de Roles
                                            </dt>
                                            <dd className="mt-1 text-sm">{item.roles_count ?? 0}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>
                    </TabsContent>

                    <TabsContent value="roles" className="space-y-6">
                        <ShowSection id="roles" title="Roles Asignados" loading={loading && activeTab === 'roles'}>
                            {loading && activeTab === 'roles' ? (
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
                            ) : item.roles && item.roles.length > 0 ? (
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="space-y-6">
                                            {/* Controls */}
                                            <div className="sticky top-0 z-10 -mx-4 -mt-4 bg-white p-4 dark:bg-gray-800">
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                        <Badge variant="secondary" className="rounded-full px-2.5 py-0.5 text-xs font-medium">
                                                            {item.roles?.length ?? 0}
                                                        </Badge>
                                                        <span>roles</span>
                                                    </div>
                                                    <div className="relative w-full sm:w-72">
                                                        <Input
                                                            placeholder="Buscar roles"
                                                            value={roleSearch}
                                                            onChange={(e) => setRoleSearch(e.target.value)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Roles list */}
                                            <div className="flex flex-wrap gap-2">
                                                {rolesFiltered.map((r) => (
                                                    <Badge key={r.id} variant="outline" className="text-xs">
                                                        {r.name}
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
                                            <Shield className="text-muted-foreground/30 mx-auto mb-3 h-12 w-12" />
                                            <p className="text-muted-foreground mb-1 text-sm font-medium">Sin roles asignados</p>
                                            <p className="text-muted-foreground text-xs">Este usuario no tiene roles asignados actualmente</p>
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
