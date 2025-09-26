import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { SectionNav } from '@/components/show-base/SectionNav';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, Pencil, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Item {
    id: number | string;
    created_at?: string | null;
    updated_at?: string | null;
    code?: string | null;
    is_active?: boolean | null;
    concessionaires_count?: number | null;
    concessionaires?: string[] | null;
    // Dynamic shape depends on module
    [key: string]: unknown;
}

interface ShowProps extends PageProps {
    item: Item;
    hasEditRoute?: boolean;
}

export default function ShowPage() {
    const { item, hasEditRoute } = usePage<ShowProps>().props;
    const [q, setQ] = useState('');
    const sections = useMemo(
        () => [
            { id: 'overview', title: 'Vista General' },
            { id: 'concessionaires', title: 'Concesionarios' },
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

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Códigos de área', href: '/catalogs/phone-area-code' },
        { title: String((item as any).code ?? (item as any).id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Código de área: ${String((item as any).code ?? (item as any).id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link href="/catalogs/phone-area-code" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{String((item as any).code ?? (item as any).id)}</h1>
                        </div>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        {hasEditRoute && (
                            <Button onClick={() => router.visit(`/catalogs/phone-area-code/${item.id}/edit`)}>
                                <Pencil className="h-4 w-4" />
                                Editar
                            </Button>
                        )}
                        <ConfirmAlert
                            trigger={
                                <Button variant="destructive" type="button">
                                    <Trash2 className="h-4 w-4" />
                                    Eliminar
                                </Button>
                            }
                            title="Eliminar registro"
                            description={`¿Está seguro de eliminar "${String((item as any).code ?? (item as any).id)}"? Esta acción no se puede deshacer.`}
                            confirmLabel="Eliminar"
                            onConfirm={async () => {
                                await new Promise<void>((resolve, reject) => {
                                    router.delete(`/catalogs/phone-area-code/${item.id}`, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            resolve();
                                            router.visit('/catalogs/phone-area-code');
                                        },
                                        onError: () => reject(new Error('delete_failed')),
                                    });
                                });
                            }}
                        />
                    </div>
                }
                aside={
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Resumen</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-sm">Estado</span>
                                    <div className="flex items-center gap-2">
                                        <span className={'h-2 w-2 shrink-0 rounded-full ' + (item.is_active ? 'bg-emerald-500' : 'bg-red-400')} />
                                        <Badge variant={item.is_active ? 'default' : 'destructive'} className="font-medium">
                                            {item.is_active ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </div>
                                </div>
                                {typeof item.concessionaires_count === 'number' && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground text-sm">Concesionarios</span>
                                        <span className="text-sm font-medium">{item.concessionaires_count}</span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="mt-4 hidden lg:block">
                            <CardHeader>
                                <CardTitle className="text-base">Secciones</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SectionNav sections={sections} />
                            </CardContent>
                        </Card>
                    </>
                }
            >
                <Tabs defaultValue="overview" className="space-y-6">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="overview">Vista General</TabsTrigger>
                        <TabsTrigger value="concessionaires">
                            Concesionarios
                            {typeof item.concessionaires_count === 'number' && (
                                <Badge variant="secondary" className="ml-2">
                                    {item.concessionaires_count}
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
                                            <dt className="text-muted-foreground text-sm font-medium">Código</dt>
                                            <dd className="mt-1 font-mono text-sm">{String((item as any).code ?? '—')}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">Estado</dt>
                                            <dd className="mt-1">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={
                                                            'h-2 w-2 shrink-0 rounded-full ' + (item.is_active ? 'bg-emerald-500' : 'bg-red-400')
                                                        }
                                                    />
                                                    <Badge variant={item.is_active ? 'default' : 'destructive'} className="font-medium">
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
                                                <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                Creado
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(item.created_at ?? null)}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-sm font-medium">
                                                <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                Última actualización
                                            </dt>
                                            <dd className="mt-1 text-sm">{formatDate(item.updated_at ?? null)}</dd>
                                        </div>
                                    </dl>
                                </CardContent>
                            </Card>
                        </ShowSection>
                    </TabsContent>

                    <TabsContent value="concessionaires" className="space-y-6">
                        <ShowSection id="concessionaires" title="Concesionarios asociados">
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="space-y-6">
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                <Badge variant="secondary" className="rounded-full px-2.5 py-0.5 text-xs font-medium">
                                                    {item.concessionaires?.length ?? 0}
                                                </Badge>
                                                <span>concesionarios</span>
                                            </div>
                                            <div className="relative w-full sm:w-72">
                                                <Input placeholder="Buscar por nombre" value={q} onChange={(e) => setQ(e.target.value)} />
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            {(item.concessionaires ?? [])
                                                .filter((n) => (q.trim() === '' ? true : String(n).toLowerCase().includes(q.toLowerCase())))
                                                .map((name, idx) => (
                                                    <Badge key={`conc-${idx}`} variant="outline" className="text-xs">
                                                        {name}
                                                    </Badge>
                                                ))}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </ShowSection>
                    </TabsContent>
                </Tabs>
            </ShowLayout>
        </AppLayout>
    );
}
