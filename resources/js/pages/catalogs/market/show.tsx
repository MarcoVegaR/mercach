import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, Pencil, Trash2 } from 'lucide-react';

interface Item {
    id: number | string;
    created_at?: string | null;
    updated_at?: string | null;
    // Dynamic shape depends on module
    [key: string]: unknown;
}

interface ShowProps extends PageProps {
    item: Item;
    hasEditRoute?: boolean;
}

export default function ShowPage() {
    const { item, hasEditRoute } = usePage<ShowProps>().props;

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
        { title: 'Mercados', href: '/catalogs/market' },
        { title: String((item as any).name ?? (item as any).code ?? (item as any).id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Mercado: ${String((item as any).name ?? (item as any).code ?? (item as any).id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link href="/catalogs/market" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                {String((item as any).name ?? (item as any).code ?? (item as any).id)}
                            </h1>
                        </div>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        {hasEditRoute && (
                            <Button onClick={() => router.visit(`/catalogs/market/${item.id}/edit`)}>
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
                            description={`¿Está seguro de eliminar "${String((item as any).name ?? (item as any).code ?? (item as any).id)}"? Esta acción no se puede deshacer.`}
                            confirmLabel="Eliminar"
                            onConfirm={async () => {
                                await new Promise<void>((resolve, reject) => {
                                    router.delete(`/catalogs/market/${item.id}`, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            resolve();
                                            router.visit('/catalogs/market');
                                        },
                                        onError: () => reject(new Error('delete_failed')),
                                    });
                                });
                            }}
                        />
                    </div>
                }
                aside={
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
                        </CardContent>
                    </Card>
                }
            >
                <ShowSection id="overview" title="Información Básica">
                    <Card>
                        <CardContent className="pt-6">
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Código</dt>
                                    <dd className="mt-1 font-mono text-sm">{String((item as any).code ?? '—')}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Nombre</dt>
                                    <dd className="mt-1 text-sm">{String((item as any).name ?? '—')}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Address</dt>
                                    <dd className="mt-1 text-sm">{String((item as any).address ?? '—')}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Estado</dt>
                                    <dd className="mt-1">
                                        <div className="flex items-center gap-2">
                                            <span className={'h-2 w-2 shrink-0 rounded-full ' + (item.is_active ? 'bg-emerald-500' : 'bg-red-400')} />
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
            </ShowLayout>
        </AppLayout>
    );
}
