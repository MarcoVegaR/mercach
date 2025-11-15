import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Calendar, FilePlus2, Pencil } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface Item {
    id: number | string;
    // Dynamic shape depends on module
    [key: string]: unknown;
}

interface ShowProps extends PageProps {
    item: Item;
    hasEditRoute?: boolean;
    canDelete?: boolean;
    allowedActions?: {
        canEdit?: boolean;
        canDelete?: boolean;
        canConfirm?: boolean;
        canTerminate?: boolean;
        canExtend?: boolean;
        canSign?: boolean;
    };
}

export default function ShowPage() {
    const { item, hasEditRoute, canDelete: _canDelete, allowedActions } = usePage<ShowProps>().props;
    const [activeTab, setActiveTab] = React.useState<'detalles' | 'documentos' | 'historial'>('detalles');
    const [openConfirm, setOpenConfirm] = React.useState(false);
    const [openTerminate, setOpenTerminate] = React.useState(false);
    const [openExtend, setOpenExtend] = React.useState(false);
    const [openSign, setOpenSign] = React.useState(false);
    const [extendDate, setExtendDate] = React.useState('');
    const [extendFile, setExtendFile] = React.useState<File | null>(null);
    const [signNumber, setSignNumber] = React.useState<string>('');
    const [signEndDate, setSignEndDate] = React.useState<string>('');
    const [signFile, setSignFile] = React.useState<File | null>(null);
    const minExtendDate = React.useMemo(() => {
        const end = (item as any).end_date as string | null | undefined;
        if (!end) return undefined as string | undefined;
        try {
            const d = new Date(end);
            d.setDate(d.getDate() + 1);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        } catch {
            return undefined;
        }
    }, [item]);

    const formatDate = (date?: string | null) => {
        if (!date) return '—';
        try {
            return new Date(date).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
        } catch {
            return '—';
        }
    };

    const formatDateTime = (date?: string | null) => {
        if (!date) return '—';
        try {
            return new Date(date).toLocaleString('es-ES', { dateStyle: 'long', timeStyle: 'short' });
        } catch {
            return '—';
        }
    };

    const formatCurrency = (raw: number | string | null | undefined) => {
        if (raw === null || raw === undefined || raw === '') return '—';
        const val = typeof raw === 'string' ? parseFloat(raw) : raw;
        if (typeof val !== 'number' || isNaN(val)) return String(raw);
        try {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(val);
        } catch {
            return String(val);
        }
    };

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Contratos', href: '/catalogs/contract' },
        { title: String((item as any).number ?? (item as any).id), href: '' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Contrato: ${String((item as any).number ?? (item as any).id)}`} />

            <ShowLayout
                header={
                    <div className="flex items-center gap-4">
                        <Link href="/catalogs/contract" className="text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight">Contrato</h1>
                            <Badge variant="secondary" className="px-2 py-0.5 font-mono text-xs">
                                {String((item as any).number ?? (item as any).id)}
                            </Badge>
                            {(() => {
                                const code = String((item as any).contract_status_code ?? '').toUpperCase();
                                const name = String(((item as any).contract_status ?? code) || '');
                                let cls = 'bg-muted text-foreground/80';
                                if (code === 'BORR') cls = 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-300';
                                else if (code === 'VIG') cls = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300';
                                else if (code === 'EXT') cls = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300';
                                else if (code === 'TERM') cls = 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-300';
                                else if (code === 'VENC') cls = 'bg-amber-100 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300';
                                return name ? <Badge className={`px-2.5 py-0.5 text-xs font-semibold ${cls}`}>{name}</Badge> : null;
                            })()}
                            {(() => {
                                const code = String((item as any).contract_status_code ?? '').toUpperCase();
                                const signedAt = (item as any).signed_at as string | null | undefined;
                                if (code === 'VIG' && !signedAt) {
                                    return (
                                        <Badge className="bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/10 dark:text-amber-300">
                                            Provisional
                                        </Badge>
                                    );
                                }
                                return null;
                            })()}
                            {(() => {
                                const code = String((item as any).contract_status_code ?? '').toUpperCase();
                                const end = (item as any).end_date as string | null | undefined;
                                if (code === 'VENC' && end) {
                                    try {
                                        const d = new Date(end);
                                        const now = new Date();
                                        const diff = Math.max(0, Math.ceil((now.getTime() - d.getTime()) / (1000 * 60 * 60 * 24)));
                                        return (
                                            <Badge className="bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">
                                                Vencido hace {diff} día(s)
                                            </Badge>
                                        );
                                    } catch {
                                        return null;
                                    }
                                }
                                return null;
                            })()}
                        </div>
                    </div>
                }
                actions={
                    <div className="flex gap-2">
                        {hasEditRoute && (allowedActions?.canEdit ?? false) && (
                            <Button onClick={() => router.visit(`/catalogs/contract/${item.id}/edit`)}>
                                <Pencil className="h-4 w-4" />
                                Editar
                            </Button>
                        )}
                        {(allowedActions?.canConfirm ?? false) && (
                            <ConfirmAlert
                                open={openConfirm}
                                onOpenChange={setOpenConfirm}
                                trigger={<Button type="button">Confirmar</Button>}
                                title="Confirmar contrato"
                                description="Pasará a Vigente y ocupará sus locales asignados."
                                confirmLabel="Confirmar"
                                onConfirm={async () => {
                                    await new Promise<void>((resolve, reject) => {
                                        router.patch(
                                            `/catalogs/contract/${item.id}/confirm`,
                                            {},
                                            {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    try {
                                                        window.dispatchEvent(new CustomEvent('data:locals:changed'));
                                                    } catch (_e) {
                                                        void _e;
                                                    }
                                                    resolve();
                                                },
                                                onError: () => reject(new Error('confirm_failed')),
                                            },
                                        );
                                    });
                                }}
                            />
                        )}
                        {(allowedActions?.canTerminate ?? false) && (
                            <ConfirmAlert
                                open={openTerminate}
                                onOpenChange={setOpenTerminate}
                                trigger={
                                    <Button variant="destructive" type="button">
                                        Terminar
                                    </Button>
                                }
                                title="Terminar contrato"
                                description="Liberará sus locales y pasará a Terminado."
                                confirmLabel="Terminar"
                                onConfirm={async () => {
                                    await new Promise<void>((resolve, reject) => {
                                        router.patch(
                                            `/catalogs/contract/${item.id}/terminate`,
                                            {},
                                            {
                                                preserveState: false,
                                                preserveScroll: true,
                                                onSuccess: () => resolve(),
                                                onError: () => reject(new Error('terminate_failed')),
                                            },
                                        );
                                    });
                                }}
                            />
                        )}
                        {(allowedActions?.canExtend ?? false) && (
                            <Button type="button" variant="outline" onClick={() => setOpenExtend(true)} className="gap-1">
                                <FilePlus2 className="h-4 w-4 text-emerald-600" />
                                Prorrogar
                            </Button>
                        )}
                        {(allowedActions?.canSign ?? false) && (
                            <Button type="button" variant="outline" onClick={() => setOpenSign(true)} className="gap-1">
                                Firmar
                            </Button>
                        )}
                        {(allowedActions?.canDelete ?? false) && (
                            <ConfirmAlert
                                trigger={
                                    <Button variant="destructive" type="button">
                                        Eliminar
                                    </Button>
                                }
                                title="Eliminar contrato"
                                description={`¿Está seguro de eliminar "${String((item as any).number ?? (item as any).id)}"? Esta acción no se puede deshacer.`}
                                confirmLabel="Eliminar"
                                onConfirm={async () => {
                                    await new Promise<void>((resolve, reject) => {
                                        router.delete(`/catalogs/contract/${item.id}`, {
                                            preserveState: false,
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                resolve();
                                                router.visit('/catalogs/contract');
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
                            onValueChange={(v) => setActiveTab(v as 'detalles' | 'documentos' | 'historial')}
                            className="space-y-4"
                        >
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="detalles">Detalles</TabsTrigger>
                                <TabsTrigger value="documentos">Documentos</TabsTrigger>
                                <TabsTrigger value="historial">Historial</TabsTrigger>
                            </TabsList>

                            <TabsContent value="detalles">
                                <ShowSection id="overview" title="Información Básica">
                                    <Card>
                                        <CardContent className="pt-6">
                                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Número</dt>
                                                    <dd className="mt-1 font-mono text-sm">{String((item as any).number ?? '—')}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Tipo</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {String((item as any).contract_type ?? (item as any).contract_type_id ?? '—')}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Estado del contrato</dt>
                                                    <dd className="mt-1">
                                                        {(() => {
                                                            const code = String((item as any).contract_status_code ?? '').toUpperCase();
                                                            const name = String(((item as any).contract_status ?? code) || '');
                                                            let cls = 'bg-muted text-foreground/80';
                                                            if (code === 'BORR')
                                                                cls = 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-300';
                                                            else if (code === 'VIG')
                                                                cls = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300';
                                                            else if (code === 'EXT')
                                                                cls = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300';
                                                            else if (code === 'TERM')
                                                                cls = 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-300';
                                                            else if (code === 'VENC')
                                                                cls = 'bg-amber-100 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300';
                                                            return name ? (
                                                                <Badge className={`px-2.5 py-0.5 text-xs font-semibold ${cls}`}>{name}</Badge>
                                                            ) : (
                                                                <span className="text-sm">—</span>
                                                            );
                                                        })()}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Modalidad</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {String((item as any).contract_modality ?? (item as any).contract_modality_id ?? '—')}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Rubro</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {String((item as any).trade_category ?? (item as any).trade_category_id ?? '—')}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Fecha inicio</dt>
                                                    <dd className="mt-1 text-sm">{formatDate((item as any).start_date as string | null)}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Fecha fin</dt>
                                                    <dd className="mt-1 text-sm">{formatDate((item as any).end_date as string | null)}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Fecha firma</dt>
                                                    <dd className="mt-1 text-sm">{formatDateTime((item as any).signed_at as string | null)}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Día facturación</dt>
                                                    <dd className="mt-1 text-sm">{String((item as any).billing_day ?? '—')}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Precio mensual (€)</dt>
                                                    <dd className="mt-1 text-sm">{formatCurrency((item as any).monthly_price_eur)}</dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground text-sm font-medium">Contrato (PDF)</dt>
                                                    <dd className="mt-1 text-sm">
                                                        {(() => {
                                                            const p = (item as any).pdf_path as string | undefined;
                                                            if (!p) return '—';
                                                            const href = `/catalogs/contract/${String((item as any).id)}/download`;
                                                            const name = p.split('/').pop();
                                                            return (
                                                                <a
                                                                    href={href}
                                                                    className="text-blue-600 hover:underline dark:text-blue-400"
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    {name ?? 'Abrir PDF'}
                                                                </a>
                                                            );
                                                        })()}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </CardContent>
                                    </Card>
                                </ShowSection>

                                <Card className="mt-6">
                                    <CardContent className="pt-6">
                                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <dt className="text-muted-foreground text-sm font-medium">
                                                    <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                    Creado
                                                </dt>
                                                <dd className="mt-1 text-sm">{formatDate((item as any).created_at ?? null)}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground text-sm font-medium">
                                                    <Calendar className="mr-1 inline h-4 w-4 text-green-500" />
                                                    Última actualización
                                                </dt>
                                                <dd className="mt-1 text-sm">{formatDate((item as any).updated_at ?? null)}</dd>
                                            </div>
                                        </dl>
                                    </CardContent>
                                </Card>

                                <Card className="mt-6">
                                    <CardHeader>
                                        <CardTitle className="text-base">Locales asociados</CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-2">
                                        {Array.isArray((item as any).locals_selected) && (item as any).locals_selected.length > 0 ? (
                                            <ul className="list-inside list-disc space-y-1">
                                                {(item as any).locals_selected.map((l: any) => (
                                                    <li key={String(l.id)}>
                                                        <Link
                                                            href={`/catalogs/local/${l.id}`}
                                                            className="text-blue-600 hover:underline dark:text-blue-400"
                                                        >
                                                            {String(l.name ?? l.id)}
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">— Sin locales asociados —</p>
                                        )}
                                    </CardContent>
                                </Card>

                                <Card className="mt-6">
                                    <CardHeader>
                                        <CardTitle className="text-base">Cesionarios asociados</CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-2">
                                        {Array.isArray((item as any).concessionaires_selected) &&
                                        (item as any).concessionaires_selected.length > 0 ? (
                                            <ul className="space-y-2">
                                                {(item as any).concessionaires_selected.map((c: any) => {
                                                    const docCode = String(c.document_type_code ?? '');
                                                    const docNum = String(c.document_number ?? '');
                                                    const docDisp = docCode && docNum ? `${docCode}-${docNum}` : `${docCode}${docNum}`;
                                                    return (
                                                        <li key={String(c.id)} className="flex items-center justify-between gap-2">
                                                            <div>
                                                                <Link
                                                                    href={`/catalogs/concessionaire/${c.id}`}
                                                                    className="text-blue-600 hover:underline dark:text-blue-400"
                                                                >
                                                                    {String(c.name ?? c.id)}
                                                                </Link>
                                                                {docDisp ? (
                                                                    <span className="text-muted-foreground ml-2 text-xs">{docDisp}</span>
                                                                ) : null}
                                                            </div>
                                                            {c.is_primary ? (
                                                                <Badge variant="secondary" className="text-[10px]">
                                                                    Titular
                                                                </Badge>
                                                            ) : null}
                                                        </li>
                                                    );
                                                })}
                                            </ul>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">— Sin concesionarios asociados —</p>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Historial de prórrogas */}
                                <Card className="mt-6">
                                    <CardHeader>
                                        <CardTitle className="text-base">Prórrogas</CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-2">
                                        {Array.isArray((item as any).extensions) && (item as any).extensions.length > 0 ? (
                                            <ul className="space-y-1 text-sm">
                                                {(item as any).extensions.map((e: any) => (
                                                    <li key={String(e.id)} className="flex items-center justify-between gap-2">
                                                        <span>
                                                            {formatDate(String(e.from_end_date))} →{' '}
                                                            <strong>{formatDate(String(e.to_end_date))}</strong>
                                                        </span>
                                                        {e.pdf_path ? (
                                                            <a
                                                                href={`/catalogs/contract/${(item as any).id}/extensions/${String(e.id)}/download`}
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                            >
                                                                {e.pdf_file ?? 'PDF'}
                                                            </a>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">— Sin prórrogas registradas —</p>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="documentos">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Contrato en PDF</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {(() => {
                                            const p = (item as any).pdf_path as string | undefined;
                                            if (!p) return <p className="text-muted-foreground text-sm">— No hay contrato disponible —</p>;

                                            const href = `/catalogs/contract/${String((item as any).id)}/download`;
                                            const isPdf = String(p).toLowerCase().endsWith('.pdf');
                                            if (isPdf) {
                                                return (
                                                    <object
                                                        data={`${href}#toolbar=1&navpanes=0&scrollbar=1`}
                                                        type="application/pdf"
                                                        className="h-[600px] w-full rounded-md border"
                                                    >
                                                        <p className="text-muted-foreground text-sm">
                                                            No se pudo incrustar el PDF. Puedes abrirlo en una nueva pestaña:{' '}
                                                            <a href={href} target="_blank" rel="noopener noreferrer" className="underline">
                                                                Abrir documento
                                                            </a>
                                                            .
                                                        </p>
                                                    </object>
                                                );
                                            }
                                            return (
                                                <div className="flex flex-col items-center gap-3">
                                                    <a
                                                        href={href}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        Abrir documento
                                                    </a>
                                                </div>
                                            );
                                        })()}
                                    </CardContent>
                                </Card>

                                {/* Extensiones en PDF */}
                                <Card className="mt-6">
                                    <CardHeader>
                                        <CardTitle className="text-base">Prórrogas (PDF)</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {Array.isArray((item as any).extensions) && (item as any).extensions.length > 0 ? (
                                            <ul className="space-y-1 text-sm">
                                                {(item as any).extensions.map((e: any) => (
                                                    <li key={String(e.id)} className="flex items-center justify-between gap-2">
                                                        <span>
                                                            {String(e.from_end_date)} → <strong>{String(e.to_end_date)}</strong>
                                                        </span>
                                                        {e.pdf_path ? (
                                                            <a
                                                                href={e.pdf_path.startsWith('/') ? e.pdf_path : `/${e.pdf_path}`}
                                                                className="text-blue-600 hover:underline dark:text-blue-400"
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                {e.pdf_file ?? 'PDF'}
                                                            </a>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">— No hay prórrogas —</p>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Historial (timeline) */}
                            <TabsContent value="historial">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Historial de estados</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {Array.isArray((item as any).status_history) && (item as any).status_history.length > 0 ? (
                                            <ol className="relative ml-2 border-l pl-6">
                                                {/* Always show created (BORR) */}
                                                <li className="mb-6">
                                                    <div className="absolute -left-[9px] mt-1 h-3 w-3 rounded-full bg-slate-400" />
                                                    <div className="text-sm">
                                                        <strong>Borrador</strong> — {formatDate((item as any).created_at as string | null)}
                                                    </div>
                                                </li>
                                                {(item as any).status_history
                                                    .filter((h: any) => String(h.to_code ?? '').toUpperCase() !== 'BORR')
                                                    .map((h: any, idx: number) => {
                                                        const to = String(h.to_code ?? '').toUpperCase();
                                                        const dateStr = formatDate(String(h.occurred_at ?? ''));
                                                        let label = to;
                                                        if (to === 'BORR') label = 'Borrador';
                                                        else if (to === 'VIG') label = 'Vigente';
                                                        else if (to === 'EXT') label = 'Extendido';
                                                        else if (to === 'TERM') label = 'Terminado';
                                                        else if (to === 'VENC') label = 'Vencido';
                                                        return (
                                                            <li key={`hist-${idx}`} className="mb-6">
                                                                <div
                                                                    className={`absolute -left-[9px] mt-1 h-3 w-3 rounded-full ${to === 'EXT' ? 'bg-emerald-500' : to === 'VIG' ? 'bg-emerald-400' : to === 'TERM' ? 'bg-red-500' : to === 'VENC' ? 'bg-amber-500' : 'bg-slate-400'}`}
                                                                />
                                                                <div className="text-sm">
                                                                    <strong>{label}</strong> — {dateStr}
                                                                    {to === 'EXT' &&
                                                                    Array.isArray((item as any).extensions) &&
                                                                    (item as any).extensions.length > 0 ? (
                                                                        <div className="text-muted-foreground mt-1 text-xs">
                                                                            {/* Show last extension info as context */}
                                                                            {(() => {
                                                                                const e = (item as any).extensions[
                                                                                    (item as any).extensions.length - 1
                                                                                ];
                                                                                if (!e) return null;
                                                                                return (
                                                                                    <span>
                                                                                        {formatDate(String(e.from_end_date))} →{' '}
                                                                                        <strong>{formatDate(String(e.to_end_date))}</strong>
                                                                                        {e.pdf_path ? (
                                                                                            <>
                                                                                                {' '}
                                                                                                <a
                                                                                                    href={`/catalogs/contract/${(item as any).id}/extensions/${String(e.id)}/download`}
                                                                                                    className="ml-2 text-blue-600 hover:underline dark:text-blue-400"
                                                                                                >
                                                                                                    PDF
                                                                                                </a>
                                                                                            </>
                                                                                        ) : null}
                                                                                    </span>
                                                                                );
                                                                            })()}
                                                                        </div>
                                                                    ) : null}
                                                                    {(item as any).days_expired ? (
                                                                        <div className="text-muted-foreground mt-1 text-xs">
                                                                            {`Vencido hace ${String((item as any).days_expired)} días`}
                                                                        </div>
                                                                    ) : null}
                                                                </div>
                                                            </li>
                                                        );
                                                    })}
                                            </ol>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">— Sin historial —</p>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </ShowLayout>

            {/* Dialogo Firmar */}
            <Dialog open={openSign} onOpenChange={setOpenSign}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Firmar contrato</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <label htmlFor={`sign_number_${String((item as any).id)}`} className="text-sm font-medium">
                                Número (opcional)
                            </label>
                            <input
                                id={`sign_number_${String((item as any).id)}`}
                                type="text"
                                className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                value={signNumber}
                                onChange={(e) => setSignNumber(e.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor={`sign_end_${String((item as any).id)}`} className="text-sm font-medium">
                                Fecha fin (opcional)
                            </label>
                            <input
                                id={`sign_end_${String((item as any).id)}`}
                                type="date"
                                className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                value={signEndDate}
                                onChange={(e) => setSignEndDate(e.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor={`sign_pdf_${String((item as any).id)}`} className="text-sm font-medium">
                                Contrato (PDF, opcional)
                            </label>
                            <input
                                id={`sign_pdf_${String((item as any).id)}`}
                                type="file"
                                accept="application/pdf"
                                className="mt-1 w-full text-sm"
                                onChange={(e) => setSignFile(e.target.files?.[0] ?? null)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpenSign(false)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={async () => {
                                const fd = new FormData();
                                if (signNumber) fd.append('number', signNumber);
                                if (signEndDate) fd.append('end_date', signEndDate);
                                if (signFile) fd.append('pdf', signFile);
                                await new Promise<void>((resolve, reject) => {
                                    router.patch(`/catalogs/contract/${(item as any).id}/sign`, fd, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => resolve(),
                                        onError: () => reject(new Error('sign_failed')),
                                    });
                                });
                                setSignNumber('');
                                setSignEndDate('');
                                setSignFile(null);
                                setOpenSign(false);
                            }}
                        >
                            Firmar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Diálogo para prórroga */}
            <Dialog open={openExtend} onOpenChange={setOpenExtend}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Prorrogar contrato</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <label htmlFor={`extend_date_show_${item.id}`} className="text-sm font-medium">
                                Nueva fecha de fin
                            </label>
                            <input
                                id={`extend_date_show_${item.id}`}
                                type="date"
                                min={minExtendDate}
                                className="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                value={extendDate}
                                onChange={(e) => setExtendDate(e.target.value)}
                            />
                        </div>
                        <div>
                            <label htmlFor={`extend_pdf_show_${item.id}`} className="text-sm font-medium">
                                Documento de prórroga (PDF, obligatorio)
                            </label>
                            <input
                                id={`extend_pdf_show_${item.id}`}
                                type="file"
                                accept="application/pdf"
                                className="mt-1 w-full text-sm"
                                onChange={(e) => setExtendFile(e.target.files?.[0] ?? null)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpenExtend(false)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={async () => {
                                if (!extendDate) {
                                    toast.error('Seleccione la nueva fecha de fin');
                                    return;
                                }
                                const curEnd = (item as any).end_date as string | null | undefined;
                                if (curEnd) {
                                    try {
                                        const cur = new Date(curEnd);
                                        const nxt = new Date(extendDate);
                                        if (!(nxt > cur)) {
                                            toast.error('La nueva fecha debe ser posterior a la actual');
                                            return;
                                        }
                                    } catch (_e) {
                                        void _e;
                                    }
                                }
                                if (!extendFile) {
                                    toast.error('Debe adjuntar el PDF de la prórroga');
                                    return;
                                }
                                const fd = new FormData();
                                fd.append('new_end_date', extendDate);
                                if (extendFile) fd.append('extension_pdf', extendFile);
                                await new Promise<void>((resolve, reject) => {
                                    router.post(`/catalogs/contract/${(item as any).id}/extend`, fd, {
                                        preserveState: false,
                                        preserveScroll: true,
                                        onSuccess: () => resolve(),
                                        onError: () => reject(new Error('extend_failed')),
                                    });
                                });
                                setExtendDate('');
                                setExtendFile(null);
                                setOpenExtend(false);
                            }}
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
