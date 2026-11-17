import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { FormActions } from '@/components/forms/form-actions';
import { DataTable } from '@/components/index/DataTable';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, useRemember } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type FormEvent } from 'react';

interface Props {
    issuanceToken: string;
    today: string;
    concessionaires: { id: number; full_name: string; document_number: string; missing_fields?: string[] }[];
    auth?: { can?: Record<string, boolean> };
    locals: { id: number; code: string; area_m2: string }[];
    trades: { id: number; name: string }[];
    contracts?: {
        id: number;
        number: string;
        status: string | null;
        concessionaire_ids: number[];
        local_ids: number[];
        local_codes: string[];
        trade_category_id: number;
        trade_name: string | null;
    }[];
    filters: { q?: string; from?: string; to?: string; tab?: string; per_page?: number };
    agreements: {
        data: { id: number; number: string; name: string; local: string; created_at: string; issuer: string | null }[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        per_page?: number;
    };
}

export default function Agreements({ issuanceToken, today, concessionaires, locals, trades, contracts = [], filters, agreements, auth }: Props) {
    const form = useForm('agreement-generator', {
        concessionaire_id: '',
        additional_concessionaire_ids: [] as string[],
        source_contract_id: '',
        local_id: '',
        trade_category_id: '',
        signature_date: today,
        start_date: '',
        issuance_token: issuanceToken,
    });
    const [tab, setTab] = useRemember(filters.tab === 'history' || filters.q || filters.from || filters.to ? 'history' : 'new', 'agreement-tab');
    const [mode, setMode] = useState(form.data.source_contract_id ? 'existing' : 'new');
    const [checkingProfiles, setCheckingProfiles] = useState(false);
    const search = useForm({
        q: filters.q ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        tab: 'history',
        per_page: agreements.per_page ?? 10,
    });
    const columns: ColumnDef<Props['agreements']['data'][number]>[] = [
        {
            accessorKey: 'number',
            header: 'Convenio',
            enableSorting: false,
            cell: ({ row }) => (
                <Link className="font-mono font-medium underline" href={route('agreements.show', row.original.id)}>
                    {row.original.number}
                </Link>
            ),
        },
        ...(['name', 'local', 'created_at', 'issuer'] as const).map((key, index) => ({
            accessorKey: key,
            header: ['Cesionario', 'Local', 'Emisión', 'Emitido por'][index],
            enableSorting: false,
        })),
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <Link className="text-sm font-medium underline" href={route('agreements.show', row.original.id)}>
                    Ver convenio
                </Link>
            ),
        },
    ];
    const associatedContracts = contracts.filter((contract) => contract.concessionaire_ids.includes(Number(form.data.concessionaire_id)));
    const source = associatedContracts.find((contract) => String(contract.id) === form.data.source_contract_id);
    const availableLocals = source ? locals.filter((local) => source.local_ids.includes(local.id)) : locals;
    const copiedSigners = source?.concessionaire_ids.filter((id) => String(id) !== form.data.concessionaire_id).length ?? 0;
    const selectedIds = [form.data.concessionaire_id, ...(form.data.additional_concessionaire_ids ?? [])].filter(Boolean);
    const incompletePeople = concessionaires.filter((person) => selectedIds.includes(String(person.id)) && person.missing_fields?.length);
    const profilesComplete =
        selectedIds.length > 0 && selectedIds.every((id) => concessionaires.find((person) => String(person.id) === id)?.missing_fields?.length === 0);
    const errors = Object.fromEntries(
        Object.entries(form.errors).filter(
            ([key]) => !(incompletePeople.length && ['concessionaire_id', 'additional_concessionaire_ids'].includes(key)),
        ),
    );

    function chooseConcessionaire(value: string) {
        setMode('new');
        form.clearErrors();
        form.setData({
            ...form.data,
            concessionaire_id: value,
            source_contract_id: '',
            local_id: '',
            trade_category_id: '',
            additional_concessionaire_ids: [],
        });
    }

    function chooseSource(value: string) {
        form.clearErrors('source_contract_id');
        const contract = associatedContracts.find((item) => String(item.id) === value);
        form.setData({
            ...form.data,
            source_contract_id: contract ? value : '',
            local_id: contract?.local_ids.length === 1 ? String(contract.local_ids[0]) : '',
            trade_category_id: contract ? String(contract.trade_category_id) : '',
            additional_concessionaire_ids: contract
                ? contract.concessionaire_ids.filter((id) => String(id) !== form.data.concessionaire_id).map(String)
                : [],
        });
    }

    function preview(event: FormEvent) {
        event.preventDefault();
        if (mode === 'existing' && !source) {
            form.setError('source_contract_id', 'Selecciona un contrato de referencia.');
            return;
        }
        form.transform((data) => {
            const payload = { ...data, issuance_token: issuanceToken };
            delete (payload as Record<string, unknown>).end_date;
            return payload;
        });
        form.get(route('agreements.preview'), { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Convenios', href: route('agreements.index') }]}>
            <Head title="Generador de convenios" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
                <header className="flex items-start gap-4">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-bold tracking-tight">Convenios</h1>
                        <p className="text-muted-foreground">Prepara el documento para firma. Emitirlo no registra ni activa un contrato.</p>
                    </div>
                </header>
                <Tabs value={tab} onValueChange={setTab} className="[contain:inline-size]">
                    <TabsList aria-label="Secciones de convenios">
                        <TabsTrigger value="new">Nuevo convenio</TabsTrigger>
                        <TabsTrigger value="history">Historial de emisiones</TabsTrigger>
                    </TabsList>
                    <TabsContent value="new" forceMount className="data-[state=inactive]:hidden">
                        <section className="bg-card flex flex-col gap-6 rounded-2xl border p-6 shadow-sm lg:p-7" aria-labelledby="new-agreement">
                            <div className="flex flex-col gap-1">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <h2 id="new-agreement" className="text-lg font-semibold">
                                        Preparar convenio
                                    </h2>
                                    <Badge variant="secondary">En preparación · Sin emitir</Badge>
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    Actualiza primero los datos del cesionario y los catálogos. El número se asignará al emitir.
                                </p>
                            </div>
                            <form onSubmit={preview} className="flex flex-col gap-8">
                                {Object.keys(errors).length > 0 && <ErrorSummary errors={errors} />}
                                <fieldset className="flex flex-col gap-5">
                                    <legend className="mb-4 font-semibold">Cesionario y referencia contractual</legend>
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <Field id="concessionaire_id" label="Cesionario principal" required>
                                            <Combobox
                                                id="concessionaire_id"
                                                ariaLabel="Cesionario principal"
                                                value={form.data.concessionaire_id}
                                                onChange={(value) => chooseConcessionaire(String(value))}
                                                options={concessionaires.map((person) => ({
                                                    value: String(person.id),
                                                    label: `${person.full_name} · ${person.document_number}`,
                                                }))}
                                                placeholder="Buscar cesionario"
                                            />
                                        </Field>
                                        <fieldset className="flex flex-col gap-3">
                                            <legend className="mb-2 text-sm font-medium">¿Cómo deseas preparar el convenio?</legend>
                                            <label className="flex cursor-pointer items-start gap-3 rounded-lg border p-3">
                                                <input
                                                    type="radio"
                                                    name="preparationMode"
                                                    value="existing"
                                                    checked={mode === 'existing'}
                                                    disabled={!form.data.concessionaire_id || associatedContracts.length === 0}
                                                    onChange={() => {
                                                        setMode('existing');
                                                        chooseSource('');
                                                    }}
                                                    className="accent-primary mt-1"
                                                />
                                                <span className="text-sm">
                                                    <strong>Usar datos de un contrato existente</strong>
                                                    <span className="text-muted-foreground block">
                                                        Recupera su local, rubro y firmantes para revisarlos.
                                                    </span>
                                                </span>
                                            </label>
                                            <label className="flex cursor-pointer items-start gap-3 rounded-lg border p-3">
                                                <input
                                                    type="radio"
                                                    name="preparationMode"
                                                    value="new"
                                                    checked={mode === 'new'}
                                                    onChange={() => {
                                                        setMode('new');
                                                        chooseSource('');
                                                    }}
                                                    className="accent-primary mt-1"
                                                />
                                                <span className="text-sm">
                                                    <strong>Seleccionar datos nuevos</strong>
                                                    <span className="text-muted-foreground block">
                                                        Elige el local, rubro y firmantes del documento.
                                                    </span>
                                                </span>
                                            </label>
                                        </fieldset>
                                        {mode === 'existing' && (
                                            <Field id="source_contract_id" label="Contrato de referencia" error={form.errors.source_contract_id}>
                                                <Combobox
                                                    id="source_contract_id"
                                                    ariaLabel="Contrato de referencia"
                                                    value={form.data.source_contract_id}
                                                    disabled={!form.data.concessionaire_id}
                                                    onChange={(value) => chooseSource(String(value))}
                                                    options={[
                                                        ...associatedContracts.map((contract) => ({
                                                            value: String(contract.id),
                                                            label: `${contract.number} · ${contract.local_codes.join(', ')} · ${contract.trade_name ?? 'Sin rubro'} · ${contract.status ?? ''}`,
                                                        })),
                                                    ]}
                                                />
                                                <p className="text-muted-foreground text-xs">
                                                    {source
                                                        ? 'Se copiaron el rubro y los cesionarios del contrato. Selecciona uno de sus locales y revisa los firmantes.'
                                                        : associatedContracts.length > 0
                                                          ? 'Puedes reutilizar los datos de un contrato existente o elegir un local y rubro nuevos.'
                                                          : form.data.concessionaire_id
                                                            ? 'No tiene contratos registrados con locales activos. Selecciona un local y un rubro para preparar datos nuevos.'
                                                            : 'Selecciona un cesionario para consultar sus contratos; también puedes preparar datos nuevos.'}
                                                </p>
                                            </Field>
                                        )}
                                        {form.data.concessionaire_id && associatedContracts.length === 0 && (
                                            <p className="text-muted-foreground text-sm">
                                                No hay contratos de referencia con locales activos para este cesionario.
                                            </p>
                                        )}
                                    </div>
                                    {source && (
                                        <p role="status" className="bg-muted rounded-lg p-3 text-sm">
                                            Se recuperaron el rubro <strong>{source.trade_name}</strong> y {copiedSigners}{' '}
                                            {copiedSigners === 1 ? 'cesionario adicional' : 'cesionarios adicionales'} del contrato {source.number}.
                                            Revisa los firmantes antes de continuar. El contrato de referencia no se modificará.
                                        </p>
                                    )}
                                </fieldset>
                                <fieldset className="flex flex-col gap-5 border-t pt-6">
                                    <legend className="font-semibold">Datos del convenio</legend>
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <Field id="local_id" label="Local" required error={form.errors.local_id}>
                                            <Combobox
                                                id="local_id"
                                                ariaLabel="Local"
                                                value={form.data.local_id}
                                                onChange={(value) => form.setData('local_id', String(value))}
                                                options={availableLocals.map((local) => ({
                                                    value: String(local.id),
                                                    label: `${local.code} · ${local.area_m2} m²`,
                                                }))}
                                                placeholder="Buscar local"
                                            />
                                        </Field>
                                        <Field id="trade_category_id" label="Rubro autorizado" required error={form.errors.trade_category_id}>
                                            <Combobox
                                                id="trade_category_id"
                                                ariaLabel="Rubro autorizado"
                                                value={form.data.trade_category_id}
                                                disabled={!!source}
                                                onChange={(value) => form.setData('trade_category_id', String(value))}
                                                options={trades.map((trade) => ({ value: String(trade.id), label: trade.name }))}
                                                placeholder="Buscar rubro"
                                            />
                                        </Field>
                                        <Field id="signature_date" label="Fecha prevista de firma" required error={form.errors.signature_date}>
                                            <Input
                                                id="signature_date"
                                                type="date"
                                                required
                                                value={form.data.signature_date}
                                                onChange={(event) => form.setData('signature_date', event.target.value)}
                                            />
                                        </Field>
                                        <Field id="start_date" label="Inicio de vigencia" required error={form.errors.start_date}>
                                            <Input
                                                id="start_date"
                                                type="date"
                                                required
                                                value={form.data.start_date ?? ''}
                                                onChange={(event) => form.setData('start_date', event.target.value)}
                                            />
                                        </Field>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        La fecha de inicio se establece por separado. El vencimiento se calcula veinte años después de la fecha de
                                        firma y se muestra en la vista previa.
                                    </p>
                                </fieldset>
                                <fieldset className="flex flex-col gap-4 border-t pt-6">
                                    <legend className="font-semibold">Cesionarios adicionales</legend>
                                    <Field id="additional_concessionaire_ids" label="Firmantes adicionales (opcional)">
                                        <Combobox
                                            id="additional_concessionaire_ids"
                                            ariaLabel="Firmantes adicionales (opcional)"
                                            multiple
                                            disabled={!form.data.concessionaire_id}
                                            value={form.data.additional_concessionaire_ids}
                                            onChange={(value) =>
                                                form.setData('additional_concessionaire_ids', Array.isArray(value) ? value : [value])
                                            }
                                            options={concessionaires
                                                .filter((person) => String(person.id) !== form.data.concessionaire_id)
                                                .map((person) => ({
                                                    value: String(person.id),
                                                    label: `${person.full_name} · ${person.document_number}`,
                                                }))}
                                            placeholder="Seleccionar cesionarios adicionales"
                                        />
                                    </Field>
                                </fieldset>
                                {incompletePeople.length > 0 && (
                                    <section className="flex flex-col gap-3" aria-label="Perfiles pendientes">
                                        {incompletePeople.map((person) => (
                                            <div
                                                key={person.id}
                                                role="alert"
                                                className="border-destructive/50 bg-destructive/5 flex flex-col gap-3 rounded-lg border p-4"
                                            >
                                                <div>
                                                    <h3 className="font-semibold">Perfil incompleto: {person.full_name}</h3>
                                                    <p className="text-muted-foreground text-sm">
                                                        Completa estos datos antes de revisar el convenio:
                                                    </p>
                                                </div>
                                                <ul className="list-inside list-disc text-sm">
                                                    {person.missing_fields?.map((field) => <li key={field}>{field}</li>)}
                                                </ul>
                                                {auth?.can?.['catalogs.concessionaire.update'] ? (
                                                    <a
                                                        className="w-fit text-sm font-medium underline"
                                                        href={route('catalogs.concessionaire.edit', person.id)}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        Completar perfil en otra pestaña
                                                    </a>
                                                ) : (
                                                    <p className="text-sm">
                                                        Solicita la actualización a un usuario autorizado para editar cesionarios.
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                        <div className="flex flex-wrap items-center gap-3">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={checkingProfiles}
                                                onClick={() => {
                                                    setCheckingProfiles(true);
                                                    router.reload({
                                                        only: ['concessionaires'],
                                                        onSuccess: () => form.clearErrors('concessionaire_id', 'additional_concessionaire_ids'),
                                                        onFinish: () => setCheckingProfiles(false),
                                                    });
                                                }}
                                            >
                                                {checkingProfiles ? 'Comprobando…' : 'Volver a comprobar perfiles'}
                                            </Button>
                                            <p className="text-muted-foreground text-xs">
                                                Después de guardar el perfil, vuelve aquí y comprueba los datos. Tu selección se conserva.
                                            </p>
                                        </div>
                                    </section>
                                )}
                                {profilesComplete && (
                                    <p role="status" className="text-sm text-emerald-700 dark:text-emerald-400">
                                        Datos de perfil completos. Se validarán nuevamente al revisar el convenio.
                                    </p>
                                )}
                                <FormActions align="between" className="flex-wrap items-center">
                                    <p className="text-muted-foreground text-sm">El número se asignará únicamente al emitir.</p>
                                    <Button type="submit" disabled={form.processing || incompletePeople.length > 0} isLoading={form.processing}>
                                        {form.processing ? 'Preparando vista previa…' : 'Revisar documento'}
                                    </Button>
                                </FormActions>
                            </form>
                        </section>
                    </TabsContent>
                    <TabsContent value="history">
                        <section className="flex flex-col gap-4" aria-labelledby="agreement-history">
                            <div className="flex items-center justify-between gap-3">
                                <h2 id="agreement-history" className="text-lg font-semibold">
                                    Historial de emisiones
                                </h2>
                                <span className="text-muted-foreground text-sm">
                                    {agreements.total} {agreements.total === 1 ? 'convenio' : 'convenios'}
                                </span>
                            </div>
                            <form
                                className="flex flex-wrap items-end gap-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    search.get(route('agreements.index'), { preserveState: true, preserveScroll: true });
                                }}
                            >
                                <Field id="q" label="Número, cesionario o local" error={search.errors.q}>
                                    <Input
                                        id="q"
                                        maxLength={100}
                                        value={search.data.q}
                                        onChange={(event) => search.setData('q', event.target.value)}
                                        placeholder="Buscar en el historial"
                                    />
                                </Field>
                                <Field id="from" label="Emitidos desde" error={search.errors.from}>
                                    <Input
                                        id="from"
                                        type="date"
                                        value={search.data.from}
                                        onChange={(event) => search.setData('from', event.target.value)}
                                    />
                                </Field>
                                <Field id="to" label="Hasta" error={search.errors.to}>
                                    <Input
                                        id="to"
                                        type="date"
                                        value={search.data.to}
                                        onChange={(event) => search.setData('to', event.target.value)}
                                    />
                                </Field>
                                <Button variant="outline" disabled={search.processing}>
                                    Filtrar
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        const cleared = { q: '', from: '', to: '', tab: 'history', per_page: agreements.per_page ?? 10 };
                                        search.setData(cleared);
                                        router.get(route('agreements.index'), cleared, { preserveState: true, preserveScroll: true });
                                    }}
                                >
                                    Limpiar
                                </Button>
                            </form>
                            <DataTable
                                className="min-w-0 overflow-hidden [&>div:last-child]:overflow-x-auto"
                                columns={columns}
                                data={agreements.data}
                                rowCount={agreements.total}
                                pageIndex={agreements.current_page - 1}
                                pageSize={agreements.per_page ?? 10}
                                onPageChange={(index) =>
                                    router.get(
                                        route('agreements.index'),
                                        { ...filters, tab: 'history', page: index + 1, per_page: agreements.per_page ?? 10 },
                                        { preserveState: true, preserveScroll: true },
                                    )
                                }
                                onPageSizeChange={(size) => {
                                    search.setData('per_page', size);
                                    router.get(
                                        route('agreements.index'),
                                        { ...filters, tab: 'history', page: 1, per_page: size },
                                        { preserveState: true, preserveScroll: true },
                                    );
                                }}
                                sorting={[]}
                                onSortingChange={() => {}}
                                enableRowSelection={false}
                                enableColumnVisibility={false}
                                enableGlobalFilter={false}
                                emptyState={
                                    <p className="text-muted-foreground p-6 text-center">
                                        No hay convenios para mostrar. Prepara uno nuevo o ajusta los filtros.
                                    </p>
                                }
                            />
                        </section>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
