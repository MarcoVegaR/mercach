import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, Pencil, Trash2 } from 'lucide-react';
import React from 'react';

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
    const [activeTab, setActiveTab] = React.useState<'detalles' | 'documentos'>('detalles');

    const photoPath = (item as any).photo_path as string | null | undefined;
    const photoRemoteUrl = (item as any).photo_url as string | null | undefined;
    const photoSrc = photoPath ? `/storage/${photoPath}` : (photoRemoteUrl ?? undefined);
    const idDocPath = (item as any).id_document_path as string | null | undefined;
    const idDocRemoteUrl = (item as any).id_document_url as string | null | undefined;
    const idDocSrc = idDocPath ? `/storage/${idDocPath}` : (idDocRemoteUrl ?? undefined);
    const idDocIsPdf = (idDocSrc ?? '').toLowerCase().endsWith('.pdf');
    const name = String((item as any).full_name ?? (item as any).id ?? '');
    const initial = (name || 'C').trim().charAt(0).toUpperCase();
    const docCode = String((item as any).document_type_code ?? '');
    const docNum = String((item as any).document_number ?? '');
    const documentDisplay = docCode && docNum ? `${docCode}-${docNum}` : `${docCode}${docNum}`;

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
        { title: 'Concesionarios', href: '/catalogs/concessionaire' },
        { title: String((item as any).id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Concesionario: ${String((item as any).id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-start gap-4 sm:items-center sm:gap-6">
                        <Link href="/catalogs/concessionaire" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <Avatar className="ring-background h-20 w-20 shadow-md ring-2">
                            {photoSrc ? <AvatarImage src={photoSrc} alt={name} /> : <AvatarFallback className="text-lg">{initial}</AvatarFallback>}
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <h1 className="truncate text-2xl font-bold tracking-tight" title={name}>
                                {name}
                            </h1>
                            <p
                                className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                                title={documentDisplay || String((item as any).email ?? '')}
                            >
                                {[documentDisplay, String((item as any).email ?? '')].filter(Boolean).map((v, i) => (
                                    <span key={i} className="inline-flex items-center gap-1">
                                        {v}
                                    </span>
                                ))}
                            </p>
                        </div>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        {hasEditRoute && (
                            <Button onClick={() => router.visit(`/catalogs/concessionaire/${item.id}/edit`)}>
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
                                    router.delete(`/catalogs/concessionaire/${item.id}`, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            resolve();
                                            router.visit('/catalogs/concessionaire');
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
                <Card>
                    <CardContent className="pt-6">
                        <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'detalles' | 'documentos')} className="space-y-4">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="detalles">Detalles</TabsTrigger>
                                <TabsTrigger value="documentos">Documentos</TabsTrigger>
                            </TabsList>

                            <TabsContent value="detalles">
                                <ShowSection id="overview" title="Información Básica">
                                    <Card>
                                        <CardContent className="pt-6">
                                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Tipo de concesionario</dt>
                                                    <dd className="mt-1 text-sm">{String((item as any).concessionaire_type_name ?? '—')}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Nombre completo</dt>
                                                    <dd className="mt-1 text-sm">{String((item as any).full_name ?? '—')}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Documento</dt>
                                                    <dd className="mt-1 text-sm">{documentDisplay || '—'}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Dirección fiscal</dt>
                                                    <dd className="mt-1 text-sm">{String((item as any).fiscal_address ?? '—')}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Correo electrónico</dt>
                                                    <dd className="mt-1 text-sm">{String((item as any).email ?? '—')}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Teléfono</dt>
                                                    <dd className="mt-1 text-sm">{String((item as any).phone_number ?? '—')}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Foto</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {photoSrc ? (
                                                            <a
                                                                href={photoSrc}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                Ver foto
                                                            </a>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Documento de identidad</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {idDocSrc ? (
                                                            <a
                                                                href={idDocSrc}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                Ver documento
                                                            </a>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </dd>
                                                </div>
                                                {/* Estado mostrado solo en el resumen lateral para evitar duplicación */}
                                            </dl>
                                        </CardContent>
                                    </Card>

                                    <Card className="mt-6">
                                        <CardContent className="pt-6">
                                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">
                                                        <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                        Creado
                                                    </dt>
                                                    <dd className="mt-1 text-sm">
                                                        {formatDate(((item as any).created_at as string | null) ?? null)}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">
                                                        <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                        Última actualización
                                                    </dt>
                                                    <dd className="mt-1 text-sm">
                                                        {formatDate(((item as any).updated_at as string | null) ?? null)}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </CardContent>
                                    </Card>
                                </ShowSection>
                            </TabsContent>

                            <TabsContent value="documentos">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Documento de identidad</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {idDocSrc ? (
                                            idDocIsPdf ? (
                                                <object
                                                    data={`${idDocSrc}#toolbar=1&navpanes=0&scrollbar=1`}
                                                    type="application/pdf"
                                                    className="h-[600px] w-full rounded-md border"
                                                >
                                                    <p className="text-muted-foreground text-sm">
                                                        No se pudo incrustar el PDF. Puedes abrirlo en una nueva pestaña:{' '}
                                                        <a href={idDocSrc} target="_blank" rel="noopener noreferrer" className="underline">
                                                            Abrir documento
                                                        </a>
                                                        .
                                                    </p>
                                                </object>
                                            ) : (
                                                <div className="flex flex-col items-center gap-3">
                                                    <img src={idDocSrc} alt="Documento" className="max-h-[600px] w-auto rounded-md border" />
                                                    <a
                                                        href={idDocSrc}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        Abrir en pestaña nueva
                                                    </a>
                                                </div>
                                            )
                                        ) : (
                                            <p className="text-muted-foreground text-sm">— No hay documento disponible —</p>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </ShowLayout>
        </AppLayout>
    );
}
