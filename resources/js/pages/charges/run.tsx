import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';

interface ChargesRunPageProps extends PageProps {
    options: {
        types: Array<{ value: string; label: string }>;
        markets: Array<{ id: number; name: string }>;
    };
    can?: Record<string, boolean>;
}

export default function ChargesRunPage({ options, can: _can }: ChargesRunPageProps) {
    const page = usePage<{ flash?: { success?: string } }>();
    const success = page.props?.flash?.success ?? '';

    const form = useForm({
        type: options.types[0]?.value ?? 'RENT_EUR_M2',
        market_id: '' as string | number,
        period: '',
        date: '',
        idempotency_key: '',
    });

    // Period is monthly for ALL, M2, FIXED and CONDO
    const requiresPeriod = ['ALL', 'RENT_EUR_M2', 'RENT_EUR_FIXED', 'CONDO_USD'].includes(form.data.type as string);
    // We no longer require a specific date for FIXED in this page
    const requiresDate = false;
    // Market required for ALL, M2 and CONDO
    const requiresMarket = ['ALL', 'RENT_EUR_M2', 'CONDO_USD'].includes(form.data.type as string);

    // Month input value mapping (YYYY-MM)
    const monthValue = React.useMemo(() => {
        const v = (form.data.period as string) || '';
        return v ? v.slice(0, 7) : '';
    }, [form.data.period]);

    const missingPeriod = requiresPeriod && !((form.data.period as string) && (form.data.period as string).length >= 10);
    const missingMarket = requiresMarket && !((form.data.market_id as string) && String(form.data.market_id).length > 0);
    const canSubmit = !form.processing && !missingPeriod && !missingMarket;

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('charges.run.execute'));
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Inicio', href: '/dashboard' },
                { title: 'Cargos', href: '' },
                { title: 'Ejecutar', href: '' },
            ]}
        >
            <Head title="Ejecutar generación de cargos" />
            <div className="mx-auto max-w-3xl p-4">
                {success && <div className="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">{success}</div>}

                <form onSubmit={onSubmit} className="space-y-4 rounded-lg border p-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium">Tipo de cargo</label>
                            <select
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value)}
                            >
                                {options.types.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Mercado</label>
                            <select
                                className="w-full rounded-md border px-3 py-2 text-sm"
                                value={form.data.market_id}
                                onChange={(e) => form.setData('market_id', e.target.value)}
                            >
                                <option value="">Selecciona…</option>
                                {options.markets.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                            {missingMarket && <p className="mt-1 text-xs text-red-600">El mercado es requerido.</p>}
                        </div>
                        {requiresPeriod && (
                            <div>
                                <label className="mb-1 block text-sm font-medium">Periodo (YYYY-MM)</label>
                                <Input
                                    type="month"
                                    value={monthValue}
                                    onChange={(e) => form.setData('period', e.target.value ? `${e.target.value}-01` : '')}
                                />
                                {missingPeriod && <p className="mt-1 text-xs text-red-600">El periodo es requerido.</p>}
                            </div>
                        )}
                        {requiresDate && (
                            <div>
                                <label className="mb-1 block text-sm font-medium">Fecha (Y-m-d)</label>
                                <Input type="date" value={form.data.date as string} onChange={(e) => form.setData('date', e.target.value)} />
                            </div>
                        )}
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium">Idempotency key (opcional)</label>
                            <Input
                                type="text"
                                maxLength={64}
                                value={form.data.idempotency_key as string}
                                onChange={(e) => form.setData('idempotency_key', e.target.value)}
                                placeholder="UUID o cadena única para evitar duplicados"
                            />
                            <p className="text-muted-foreground mt-1 text-xs">
                                Opcional. Si lo dejas vacío, los índices únicos evitarán duplicados igualmente.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button type="submit" disabled={!canSubmit}>
                            Ejecutar ahora
                        </Button>
                        {form.processing && <span className="text-muted-foreground text-sm">Procesando…</span>}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
