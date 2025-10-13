import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, Coins, Pencil, Trash2 } from 'lucide-react';

interface Item {
    id: number | string;
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
        { title: 'Tasas de cambio', href: '/catalogs/fx-rate' },
        { title: String((item as any).id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tasa de cambio: ${String((item as any).id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link href="/catalogs/fx-rate" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div className="flex items-center gap-2">
                            <Coins className="h-6 w-6 text-indigo-500" />
                            <h1 className="text-2xl font-bold tracking-tight">Tasa de cambio #{String((item as any).id)}</h1>
                        </div>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        {hasEditRoute && (
                            <Button onClick={() => router.visit(`/catalogs/fx-rate/${item.id}/edit`)}>
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
                            description={`¿Está seguro de eliminar "${String((item as any).id)}"? Esta acción no se puede deshacer.`}
                            confirmLabel="Eliminar"
                            onConfirm={async () => {
                                await new Promise<void>((resolve, reject) => {
                                    router.delete(`/catalogs/fx-rate/${item.id}`, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            resolve();
                                            router.visit('/catalogs/fx-rate');
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
                                    <dt className="text-muted-foreground text-sm font-medium">Moneda</dt>
                                    <dd className="mt-1 text-sm">
                                        <Badge variant="outline" className="font-semibold">
                                            {String((item as any).currency_code ?? '—')}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Fecha de tasa</dt>
                                    <dd className="mt-1 text-sm">{formatDate(String((item as any).rate_date ?? ''))}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Fecha valor</dt>
                                    <dd className="mt-1 text-sm">{formatDate(String((item as any).value_date ?? ''))}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Publicado el</dt>
                                    <dd className="mt-1 text-sm">{formatDate(String((item as any).published_at ?? ''))}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Tasa (VES)</dt>
                                    <dd className="mt-1 text-sm">
                                        {new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
                                            Number((item as any).rate_to_ves ?? 0),
                                        )}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Vigente desde</dt>
                                    <dd className="mt-1 text-sm">{formatDate(String((item as any).operational_from ?? ''))}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Vigente hasta</dt>
                                    <dd className="mt-1 text-sm">{formatDate(String((item as any).operational_to ?? ''))}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Fuente</dt>
                                    <dd className="mt-1 text-sm">{String((item as any).source ?? '—')}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">Oficial (BCV)</dt>
                                    <dd className="mt-1 text-sm">{(item as any).is_official ? 'Sí' : 'No'}</dd>
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
                                    <dd className="mt-1 text-sm">{formatDate(String((item as any).created_at ?? ''))}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm font-medium">
                                        <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                        Última actualización
                                    </dt>
                                    <dd className="mt-1 text-sm">{formatDate(String((item as any).updated_at ?? ''))}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </ShowSection>
            </ShowLayout>
        </AppLayout>
    );
}
