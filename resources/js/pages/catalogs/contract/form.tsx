import { ErrorSummary } from '@/components/form/ErrorSummary';
import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { FieldError } from '@/components/forms/field-error';
import { FormActions } from '@/components/forms/form-actions';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker, type DatePickerValue } from '@/components/ui/date-picker';
import { FileDropzone } from '@/components/ui/file-dropzone';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Banknote, FileText, Hash, Layers, Store, Tag, User, Users } from 'lucide-react';
import React, { useEffect, useMemo, useRef } from 'react';
import { toast } from 'sonner';

type FormMode = 'create' | 'edit';

interface ModelShape {
    id?: number | string;
    number?: string | null;
    contract_type_id?: string | number | null;
    contract_status_id?: string | number | null;
    contract_modality_id?: string | number | null;
    trade_category_id?: string | number | null;
    start_date?: string | null;
    end_date?: string | null;
    billing_day?: number | string | null;
    monthly_price_eur?: number | null;
    is_active?: boolean | null;
    updated_at?: string | null;
    // for edit prefill
    local_ids?: Array<number | string> | null;
    concessionaire_primary_id?: number | string | null;
    concessionaire_additional_ids?: Array<number | string> | null;
}

interface PageProps {
    mode: FormMode;
    model?: ModelShape;
    options?: {
        contract_types: Array<{ id: number; name: string }>;
        contract_statuses: Array<{ id: number; name: string; code?: string }>;
        contract_modalities: Array<{ id: number; name: string; code?: string }>;
        trade_categories: Array<{ id: number; name: string }>;
        concessionaires: Array<{ id: number; name: string; document_number?: string; document_type_code?: string }>;
        locals: Array<{ id: number; name: string }>;
    };
}

export default function FormPage(props: PageProps) {
    const mode: FormMode = props.mode ?? 'create';
    const initial = useMemo(() => props.model ?? {}, [props.model]);
    const opts: NonNullable<PageProps['options']> = props.options ?? {
        contract_types: [],
        contract_statuses: [],
        contract_modalities: [],
        trade_categories: [],
        concessionaires: [],
        locals: [],
    };

    const form = useForm({
        number: initial.number ?? '',
        contract_type_id: initial.contract_type_id ? String(initial.contract_type_id) : '',
        contract_modality_id: initial.contract_modality_id ? String(initial.contract_modality_id) : '',
        trade_category_id: initial.trade_category_id ? String(initial.trade_category_id) : '',
        start_date: initial.start_date ?? '',
        end_date: initial.end_date ?? '',
        billing_day: initial.billing_day ?? '',
        monthly_price_eur: initial.monthly_price_eur ?? null,
        primary_concessionaire_id: initial.concessionaire_primary_id ? String(initial.concessionaire_primary_id) : '',
        additional_concessionaire_ids: (initial.concessionaire_additional_ids ?? []).map((v: any) => String(v)),
        local_ids: (initial.local_ids ?? []).map((v: any) => String(v)),
        pdf: null as File | null,
        is_active: Boolean(initial.is_active ?? true),
        _version: mode === 'edit' ? (initial.updated_at ?? null) : null,
    });

    const breadcrumbs = [
        { title: 'Catálogos', href: '/catalogs' },
        { title: 'Contratos', href: '/catalogs/contract' },
        { title: mode === 'edit' ? 'Editar' : 'Crear', href: '' },
    ];

    // Helpers to handle YYYY-MM-DD safely in local time to avoid timezone shifts
    const parseYMD = (s?: string | null): Date | undefined => {
        if (!s) return undefined;
        const parts = String(s).slice(0, 10).split('-');
        if (parts.length !== 3) return undefined;
        const [y, m, d] = parts.map((n) => parseInt(n, 10));
        if (!y || !m || !d) return undefined;
        return new Date(y, m - 1, d);
    };
    const toYMD = (d?: Date | null): string => {
        if (!d) return '';
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };

    const startDateObj = useMemo(() => parseYMD(form.data.start_date as any), [form.data.start_date]);
    const endDateObj = useMemo(() => parseYMD(form.data.end_date as any), [form.data.end_date]);
    // Note: 'today' not used; if needed for future constraints, re-enable.

    // Merge selected locals (from edit) into options so chips render even if not available currently
    const localOptions = useMemo(() => {
        const available = (opts.locals ?? []).map((c) => ({ value: String(c.id), label: c.name })) as Array<{
            value: string;
            label: string;
            group?: string;
        }>;
        const selectedIds = new Set((form.data.local_ids ?? []) as string[]);
        const presentIds = new Set(available.map((o) => o.value));
        const fromInitial = ((initial as any).locals_selected ?? []) as Array<{ id: number | string; name: string }>;
        const missingSelected = fromInitial
            .filter((l) => selectedIds.has(String(l.id)) && !presentIds.has(String(l.id)))
            .map((l) => ({ value: String(l.id), label: l.name, group: 'Asignados (no disponibles)' }));
        return [...missingSelected, ...available];
    }, [opts.locals, form.data.local_ids, initial]);

    const firstErrorRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (Object.keys(form.errors).length > 0) {
            firstErrorRef.current?.focus();
        }
    }, [form.errors]);

    function handleCancel() {
        router.visit('/catalogs/contract', { preserveScroll: true });
    }

    const selectedModality = useMemo(() => {
        const id = form.data.contract_modality_id ? Number(form.data.contract_modality_id) : null;
        return id ? opts.contract_modalities.find((m) => m.id === id) : undefined;
    }, [form.data.contract_modality_id, opts.contract_modalities]);

    const isFixed = useMemo(() => (selectedModality?.code ?? '').toUpperCase() === 'TFIJA', [selectedModality]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        // Client-side date validation: start_date <= end_date
        try {
            const s = parseYMD(form.data.start_date as any);
            const eDate = parseYMD(form.data.end_date as any);
            if (s && eDate && s.getTime() > eDate.getTime()) {
                toast.error('La fecha de inicio no puede ser posterior a la fecha de fin');
                return;
            }
        } catch (_e) {
            void _e;
        }

        const transformOnce = (data: typeof form.data) => {
            const payload: Record<string, any> = { ...data };
            payload.number = String(data.number ?? '')
                .toUpperCase()
                .trim();
            payload.contract_type_id = data.contract_type_id ? Number(data.contract_type_id) : '';
            payload.contract_modality_id = data.contract_modality_id ? Number(data.contract_modality_id) : '';
            payload.trade_category_id = data.trade_category_id ? Number(data.trade_category_id) : '';
            payload.billing_day = data.billing_day === '' || data.billing_day === null ? '' : Number(data.billing_day);
            payload.monthly_price_eur =
                data.monthly_price_eur === null || (data as any).monthly_price_eur === '' ? '' : Number(data.monthly_price_eur);
            payload.primary_concessionaire_id = data.primary_concessionaire_id ? Number(data.primary_concessionaire_id) : '';
            payload.additional_concessionaire_ids = Array.isArray(data.additional_concessionaire_ids)
                ? data.additional_concessionaire_ids.map((v) => Number(v)).filter((v) => v > 0 && v !== Number(payload.primary_concessionaire_id))
                : [];
            payload.local_ids = Array.isArray(data.local_ids) ? data.local_ids.map((v) => Number(v)) : [];
            if (!data.pdf) delete payload.pdf;
            return payload;
        };

        if (mode === 'create') {
            form.transform(transformOnce);
            form.post(route('catalogs.contract.store'), { forceFormData: true, preserveScroll: true });
        } else {
            const id = initial.id;
            if (id === undefined || id === null || String(id) === '') {
                toast.error('ID inválido para editar');
                return;
            }
            form.transform((data) => ({ ...transformOnce(data), _method: 'put' }));
            form.post(route('catalogs.contract.update', id as any), { forceFormData: true, preserveScroll: true });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'edit' ? 'Editar Contrato' : 'Crear Contrato'} />
            <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 dark:from-gray-900 dark:via-gray-950 dark:to-gray-900">
                <div className="py-8">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100">{mode === 'edit' ? 'Editar' : 'Crear'} Contrato</h1>

                        <TooltipProvider delayDuration={300}>
                            <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-2xl border p-6 shadow-sm lg:p-7">
                                {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} className="mb-2" />}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field id="number" label="Número" error={form.errors.number} tooltip="Código o número del contrato (único)">
                                        <Input
                                            name="number"
                                            value={form.data.number}
                                            onChange={(e) => form.setData('number', e.target.value)}
                                            onBlur={() => form.setData('number', (form.data.number ?? '').toUpperCase().trim())}
                                            maxLength={40}
                                            leadingIcon={Hash}
                                            leadingIconClassName="text-blue-600"
                                        />
                                    </Field>

                                    <Field
                                        id="contract_type_id"
                                        label="Tipo de contrato"
                                        error={form.errors.contract_type_id}
                                        tooltip="Clasificación general del contrato"
                                    >
                                        <Select
                                            value={form.data.contract_type_id ?? ''}
                                            onValueChange={(val) => form.setData('contract_type_id', val)}
                                        >
                                            <SelectTrigger
                                                id="contract_type_id"
                                                className="w-full"
                                                leadingIcon={FileText}
                                                leadingIconClassName="text-indigo-600"
                                            >
                                                <SelectValue placeholder="Seleccionar tipo" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {opts.contract_types.map((m) => (
                                                    <SelectItem key={m.id} value={String(m.id)}>
                                                        {m.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        id="primary_concessionaire_id"
                                        label="Firmante principal"
                                        error={(form.errors as any).primary_concessionaire_id}
                                        tooltip="Concesionario responsable principal del contrato"
                                    >
                                        <Select
                                            value={form.data.primary_concessionaire_id ?? ''}
                                            onValueChange={(val) => {
                                                form.setData('primary_concessionaire_id', val);
                                                // Remove from additional if previously selected
                                                if (val) {
                                                    form.setData(
                                                        'additional_concessionaire_ids',
                                                        (form.data.additional_concessionaire_ids ?? []).filter((x: string) => x !== val),
                                                    );
                                                }
                                            }}
                                        >
                                            <SelectTrigger
                                                id="primary_concessionaire_id"
                                                className="w-full"
                                                leadingIcon={User}
                                                leadingIconClassName="text-emerald-600"
                                            >
                                                <SelectValue placeholder="Seleccionar firmante principal" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {opts.concessionaires.map((m) => (
                                                    <SelectItem key={m.id} value={String(m.id)}>
                                                        {(m.document_type_code ? m.document_type_code + ' ' : '') +
                                                            (m.document_number ? m.document_number + ' - ' : '') +
                                                            m.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        id="contract_modality_id"
                                        label="Modalidad"
                                        error={form.errors.contract_modality_id}
                                        tooltip="Esquema de contratación (p.ej., Tasa fija)"
                                    >
                                        <Select
                                            value={form.data.contract_modality_id ?? ''}
                                            onValueChange={(val) => form.setData('contract_modality_id', val)}
                                        >
                                            <SelectTrigger
                                                id="contract_modality_id"
                                                className="w-full"
                                                leadingIcon={Layers}
                                                leadingIconClassName="text-purple-600"
                                            >
                                                <SelectValue placeholder="Seleccionar modalidad" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {opts.contract_modalities.map((m) => (
                                                    <SelectItem key={m.id} value={String(m.id)}>
                                                        {m.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        id="trade_category_id"
                                        label="Rubro"
                                        error={form.errors.trade_category_id}
                                        tooltip="Rubro o categoría del comercio"
                                    >
                                        <Combobox
                                            options={opts.trade_categories.map((m) => ({ value: String(m.id), label: m.name }))}
                                            value={form.data.trade_category_id ?? ''}
                                            onChange={(v) => form.setData('trade_category_id', Array.isArray(v) ? (v[0] ?? '') : v)}
                                            placeholder="Seleccionar rubro"
                                            searchPlaceholder="Buscar rubro..."
                                            emptyText="Sin resultados"
                                            leadingIcon={Tag}
                                            leadingIconClassName="text-rose-600"
                                        />
                                    </Field>

                                    <Field
                                        id="start_date"
                                        label="Fecha de inicio"
                                        error={form.errors.start_date}
                                        tooltip="Fecha de inicio del contrato"
                                    >
                                        <DatePicker
                                            id="start_date"
                                            mode="single"
                                            value={startDateObj}
                                            onChange={(v: DatePickerValue) => form.setData('start_date', toYMD(v as Date))}
                                            placeholder="Seleccionar fecha"
                                            buttonClassName="w-full justify-between"
                                        />
                                    </Field>

                                    <Field
                                        id="end_date"
                                        label="Fecha de fin"
                                        error={form.errors.end_date}
                                        tooltip="Fecha de fin del contrato (opcional)"
                                    >
                                        <DatePicker
                                            id="end_date"
                                            mode="single"
                                            value={endDateObj}
                                            onChange={(v: DatePickerValue) => form.setData('end_date', toYMD(v as Date))}
                                            placeholder="Seleccionar fecha"
                                            buttonClassName="w-full justify-between"
                                            minDate={startDateObj ?? undefined}
                                        />
                                    </Field>

                                    {isFixed && (
                                        <>
                                            <Field
                                                id="billing_day"
                                                label="Día de facturación (1-31)"
                                                error={form.errors.billing_day}
                                                tooltip="Día del mes para emitir la factura (1-31)"
                                            >
                                                <Input
                                                    name="billing_day"
                                                    type="number"
                                                    min={1}
                                                    max={31}
                                                    value={form.data.billing_day ?? ''}
                                                    onChange={(e) => form.setData('billing_day', e.target.value)}
                                                />
                                            </Field>

                                            <Field
                                                id="monthly_price_eur"
                                                label="Precio mensual (€)"
                                                error={form.errors.monthly_price_eur}
                                                tooltip="Importe mensual pactado (obligatorio en modalidad TFIJA)"
                                            >
                                                <Input
                                                    name="monthly_price_eur"
                                                    type="number"
                                                    step="0.01"
                                                    value={form.data.monthly_price_eur ?? ''}
                                                    onChange={(e) =>
                                                        form.setData('monthly_price_eur', e.target.value === '' ? null : Number(e.target.value))
                                                    }
                                                    leadingIcon={Banknote}
                                                    leadingIconClassName="text-emerald-600"
                                                    placeholder="Ej: 250.00"
                                                />
                                            </Field>
                                        </>
                                    )}

                                    <Field
                                        id="additional_concessionaire_ids"
                                        label="Firmantes adicionales"
                                        error={(form.errors as any).additional_concessionaire_ids}
                                        tooltip="Concesionarios adicionales (búsqueda por documento o nombre)"
                                    >
                                        <Combobox
                                            options={opts.concessionaires
                                                .filter((c) => String(c.id) !== (form.data.primary_concessionaire_id ?? ''))
                                                .map((c) => ({
                                                    value: String(c.id),
                                                    label: `${c.document_type_code ? c.document_type_code + ' ' : ''}${c.document_number ?? ''}${c.document_number ? ' - ' : ''}${c.name}`,
                                                }))}
                                            value={form.data.additional_concessionaire_ids}
                                            onChange={(v) => form.setData('additional_concessionaire_ids', Array.isArray(v) ? v : [v])}
                                            multiple
                                            placeholder="Seleccionar firmantes adicionales"
                                            searchPlaceholder="Buscar firmante..."
                                            emptyText="Sin resultados"
                                            leadingIcon={Users}
                                            leadingIconClassName="text-sky-600"
                                        />
                                    </Field>

                                    <Field
                                        id="local_ids"
                                        label="Locales"
                                        error={(form.errors as any).local_ids}
                                        tooltip="Locales asignados al contrato (solo disponibles)"
                                    >
                                        <Combobox
                                            options={localOptions}
                                            value={form.data.local_ids}
                                            onChange={(v) => form.setData('local_ids', Array.isArray(v) ? v : [v])}
                                            multiple
                                            placeholder="Seleccionar locales disponibles"
                                            searchPlaceholder="Buscar local..."
                                            emptyText={
                                                (opts.locals?.length ?? 0) === 0 && (form.data.local_ids?.length ?? 0) === 0
                                                    ? 'No hay locales disponibles'
                                                    : 'Sin coincidencias'
                                            }
                                            leadingIcon={Store}
                                            leadingIconClassName="text-amber-600"
                                        />
                                    </Field>

                                    <Field
                                        id="pdf"
                                        label="Contrato (PDF)"
                                        error={(form.errors as any).pdf}
                                        tooltip="Archivo PDF opcional del contrato (máx. 10 MB)"
                                    >
                                        <FileDropzone
                                            onFileSelect={(file) => form.setData('pdf', file)}
                                            file={form.data.pdf as any}
                                            existingFileUrl={(initial as any).pdf_path ? `/${(initial as any).pdf_path}` : undefined}
                                            existingFileName={
                                                (initial as any).pdf_path
                                                    ? String((initial as any).pdf_path)
                                                          .split('/')
                                                          .pop()
                                                    : undefined
                                            }
                                            accept="application/pdf"
                                            maxSize="10 MB"
                                            preview={false}
                                            placeholder="Seleccionar archivo PDF"
                                        />
                                    </Field>
                                </div>

                                {mode === 'edit' && (
                                    <Field id="is_active" label="Estado activo" error={form.errors.is_active}>
                                        <ActiveField
                                            checked={!!form.data.is_active}
                                            onChange={(v) => form.setData('is_active', v)}
                                            canToggle={true}
                                            activeLabel="Registro activo"
                                            inactiveLabel="Registro inactivo"
                                        />
                                        <FieldError message={form.errors.is_active} />
                                    </Field>
                                )}

                                <p className="text-muted-foreground text-xs">Los campos marcados con * son obligatorios</p>

                                <FormActions
                                    onCancel={handleCancel}
                                    isSubmitting={form.processing}
                                    isDirty={true}
                                    submitText={mode === 'create' ? 'Crear' : 'Actualizar'}
                                />
                            </form>
                        </TooltipProvider>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
