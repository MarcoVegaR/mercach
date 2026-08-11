import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, CalendarCheck, Pencil, Printer, Trash2, UserPlus } from 'lucide-react';
import React from 'react';
import { printLifeProofForms } from './columns';

interface Item {
    id: number | string;
    // Dynamic shape depends on module
    [key: string]: unknown;
}

interface ShowProps extends PageProps {
    item: Item;
    hasEditRoute?: boolean;
    auth?: { can?: Record<string, boolean> };
}

export default function ShowPage() {
    const { item, hasEditRoute, auth } = usePage<ShowProps>().props;
    const canDelete = !!auth?.can?.['catalogs.concessionaire.delete'];
    const canUpdate = !!auth?.can?.['catalogs.concessionaire.update'];
    const [activeTab, setActiveTab] = React.useState<'detalles' | 'documentos' | 'contratos'>('detalles');
    const [openInvite, setOpenInvite] = React.useState(false);
    const [openLifeProof, setOpenLifeProof] = React.useState(false);
    const invite = useForm<{ name: string; email: string }>({
        name: String((item as any).full_name ?? ''),
        email: String((item as any).email ?? ''),
    });
    const lifeProof = useForm<{ life_proof_at: string }>({
        life_proof_at: new Intl.DateTimeFormat('en-CA', {
            timeZone: 'America/Caracas',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(new Date()),
    });

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
            const value = /^\d{4}-\d{2}-\d{2}$/.test(date) ? `${date}T12:00:00` : date;
            return new Date(value).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
        } catch {
            return '—';
        }
    };

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Cesionarios', href: '/catalogs/concessionaire' },
        { title: String((item as any).id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Cesionario: ${String((item as any).id)}`} />

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
                        <Button variant="outline" asChild>
                            <a href={`/catalogs/concessionaire/${item.id}/profile-pdf`} target="_blank" rel="noopener noreferrer">
                                <Printer className="h-4 w-4" />
                                Ficha PDF
                            </a>
                        </Button>
                        <Button variant="outline" type="button" onClick={() => printLifeProofForms([item.id])}>
                            <CalendarCheck className="h-4 w-4" />
                            Fe de vida
                        </Button>
                        {(item as any).portal_user_exists !== true ? (
                            <Dialog open={openInvite} onOpenChange={setOpenInvite}>
                                <DialogTrigger asChild>
                                    <Button variant="outline" type="button">
                                        <UserPlus className="h-4 w-4" />
                                        Generar usuario
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Generar usuario de Portal</DialogTitle>
                                    </DialogHeader>
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            router.post(`/catalogs/concessionaire/${item.id}/portal-users`, invite.data, {
                                                preserveScroll: true,
                                                onSuccess: () => setOpenInvite(false),
                                            });
                                        }}
                                        className="space-y-4"
                                    >
                                        <div className="space-y-2">
                                            <Label htmlFor="name">Nombre</Label>
                                            <Input id="name" value={invite.data.name} onChange={(e) => invite.setData('name', e.target.value)} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="email">Correo</Label>
                                            <Input id="email" type="email" value={invite.data.email} disabled readOnly />
                                            <p className="text-muted-foreground text-xs">
                                                Debe coincidir con el correo registrado del concesionario. Para cambiarlo, actualiza el correo del
                                                concesionario.
                                            </p>
                                        </div>
                                        <div className="flex justify-end gap-2">
                                            <Button type="button" variant="ghost" onClick={() => setOpenInvite(false)}>
                                                Cancelar
                                            </Button>
                                            <Button type="submit">Invitar</Button>
                                        </div>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        ) : (
                            <Button
                                variant="outline"
                                type="button"
                                onClick={() => router.post(`/catalogs/concessionaire/${item.id}/portal-users/reset`, {}, { preserveScroll: true })}
                            >
                                <UserPlus className="h-4 w-4" />
                                Restablecer acceso
                            </Button>
                        )}
                        {hasEditRoute && (
                            <Button onClick={() => router.visit(`/catalogs/concessionaire/${item.id}/edit`)}>
                                <Pencil className="h-4 w-4" />
                                Editar
                            </Button>
                        )}
                        {canDelete && (
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
                        )}
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
                        <Tabs
                            value={activeTab}
                            onValueChange={(v) => setActiveTab(v as 'detalles' | 'documentos' | 'contratos')}
                            className="space-y-4"
                        >
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="detalles">Detalles</TabsTrigger>
                                <TabsTrigger value="documentos">Documentos</TabsTrigger>
                                <TabsTrigger value="contratos">Contratos</TabsTrigger>
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

                                    <Card className="mt-6">
                                        <CardHeader>
                                            <CardTitle className="flex items-center justify-between gap-3 text-base">
                                                <span className="flex items-center gap-2">
                                                    <CalendarCheck className="h-4 w-4 text-teal-600" />
                                                    Fe de vida
                                                </span>
                                                {canUpdate && (
                                                    <Dialog open={openLifeProof} onOpenChange={setOpenLifeProof}>
                                                        <DialogTrigger asChild>
                                                            <Button type="button" variant="outline" size="sm">
                                                                Registrar fe de vida
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent>
                                                            <DialogHeader>
                                                                <DialogTitle>Registrar fe de vida</DialogTitle>
                                                            </DialogHeader>
                                                            <form
                                                                className="space-y-4"
                                                                onSubmit={(event) => {
                                                                    event.preventDefault();
                                                                    lifeProof.post(`/catalogs/concessionaire/${item.id}/life-proof`, {
                                                                        preserveScroll: true,
                                                                        onSuccess: () => setOpenLifeProof(false),
                                                                    });
                                                                }}
                                                            >
                                                                <div className="space-y-2">
                                                                    <Label htmlFor="life_proof_at">Fecha de comparecencia</Label>
                                                                    <Input
                                                                        id="life_proof_at"
                                                                        type="date"
                                                                        max={new Intl.DateTimeFormat('en-CA', {
                                                                            timeZone: 'America/Caracas',
                                                                            year: 'numeric',
                                                                            month: '2-digit',
                                                                            day: '2-digit',
                                                                        }).format(new Date())}
                                                                        value={lifeProof.data.life_proof_at}
                                                                        onChange={(event) => lifeProof.setData('life_proof_at', event.target.value)}
                                                                    />
                                                                    {lifeProof.errors.life_proof_at && (
                                                                        <p className="text-destructive text-sm">{lifeProof.errors.life_proof_at}</p>
                                                                    )}
                                                                </div>
                                                                <div className="flex justify-end gap-2">
                                                                    <Button type="button" variant="ghost" onClick={() => setOpenLifeProof(false)}>
                                                                        Cancelar
                                                                    </Button>
                                                                    <Button type="submit" disabled={lifeProof.processing}>
                                                                        Registrar
                                                                    </Button>
                                                                </div>
                                                            </form>
                                                        </DialogContent>
                                                    </Dialog>
                                                )}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Última fecha</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {formatDate(((item as any).last_life_proof_at as string | null) ?? null)}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Próxima citación</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {formatDate(((item as any).life_proof_due_on as string | null) ?? null)}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Estado</dt>
                                                    <dd className="mt-1">
                                                        <Badge
                                                            className={
                                                                (item as any).life_proof_status === 'current'
                                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300'
                                                                    : 'bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-300'
                                                            }
                                                        >
                                                            {(item as any).life_proof_status === 'current'
                                                                ? 'Vigente'
                                                                : (item as any).life_proof_status === 'missing'
                                                                  ? 'Sin registro'
                                                                  : 'Requiere citación'}
                                                        </Badge>
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

                            {/* Contratos */}
                            <TabsContent value="contratos">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Historial de contratos</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {Array.isArray((item as any).contracts_history) && (item as any).contracts_history.length > 0 ? (
                                            <ol className="relative ml-2 border-l pl-6">
                                                {(item as any).contracts_history.map((c: any, idx: number) => (
                                                    <li key={`ct-${idx}`} className="mb-6">
                                                        <div
                                                            className={`absolute -left-[9px] mt-1 h-3 w-3 rounded-full ${String(c.status_code).toUpperCase() === 'TERM' ? 'bg-red-500' : 'bg-emerald-500'}`}
                                                        />
                                                        <div className="text-sm">
                                                            <strong>
                                                                <Link
                                                                    href={`/catalogs/contract/${c.id}`}
                                                                    className="text-blue-600 hover:underline dark:text-blue-400"
                                                                >
                                                                    {String(c.number ?? c.id)}
                                                                </Link>
                                                            </strong>{' '}
                                                            — {String(c.status ?? c.status_code ?? '')}
                                                            <div className="text-muted-foreground mt-1 text-xs">
                                                                {(() => {
                                                                    const fmt = (d?: string | null) => {
                                                                        if (!d) return '—';
                                                                        try {
                                                                            return new Date(d).toLocaleDateString('es-ES', {
                                                                                year: 'numeric',
                                                                                month: 'long',
                                                                                day: 'numeric',
                                                                            });
                                                                        } catch {
                                                                            return '—';
                                                                        }
                                                                    };
                                                                    return (
                                                                        <>
                                                                            {fmt(String(c.start_date ?? ''))} →{' '}
                                                                            {c.end_date ? fmt(String(c.end_date)) : '—'}
                                                                        </>
                                                                    );
                                                                })()}
                                                            </div>
                                                        </div>
                                                    </li>
                                                ))}
                                            </ol>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">— Sin contratos asociados —</p>
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
