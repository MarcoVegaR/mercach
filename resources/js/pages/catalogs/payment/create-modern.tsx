import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Banknote,
    Building2,
    Calendar,
    Check,
    CreditCard,
    Hash,
    Info,
    Landmark,
    Phone,
    Smartphone,
    User,
} from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface Bank {
    id: number;
    name: string;
}
interface CompanyAccount {
    id: number;
    label: string;
    supportsPMOV?: boolean;
}
interface Concessionaire {
    id: number;
    name: string;
    document_number?: string;
    document_type_code?: string;
}
interface LocalOpt {
    id: number;
    label: string;
}
interface PhoneAreaCode {
    id: number;
    code: string;
}

type Props = {
    options: {
        companyBankAccounts: CompanyAccount[];
        banks: Bank[];
        phoneAreaCodes: PhoneAreaCode[];
        concessionaires: Concessionaire[];
        locals: LocalOpt[];
        paymentTypes: Array<{ value: string; label: string }>;
    };
    mode?: 'create' | 'edit';
};

export default function PaymentCreateModern({ options }: Props) {
    const page = usePage<{ flash?: { success?: string; error?: string; warning?: string; info?: string } }>();
    const flash = (page.props as any)?.flash ?? {};

    const [step, setStep] = React.useState<0 | 1 | 2>(0);
    const [method, setMethod] = React.useState<'TRANSFER' | 'PMOV' | 'DEB' | null>(null);
    const verifyToastRef = React.useRef<string | number | null>(null);
    const [fxRateUsd, setFxRateUsd] = React.useState<string | null>(null);

    const bankOptions = React.useMemo(() => (options?.banks ?? []).map((b) => ({ value: String(b.id), label: b.name })), [options?.banks]);
    const areaOptions = React.useMemo(
        () => (options?.phoneAreaCodes ?? []).map((a) => ({ value: a.code, label: a.code })),
        [options?.phoneAreaCodes],
    );

    const { data, setData, processing, post, errors } = useForm({
        // Debtor
        debtor_type: 'CONCESSIONAIRE' as 'CONCESSIONAIRE' | 'LOCAL',
        debtor_id: '' as any,
        local_id: '' as any,
        // Payment core
        company_bank_account_id: '' as any,
        method: '' as any,
        origin_bank_id: '' as any,
        payer_document_type: '' as any,
        payer_document_number: '' as any,
        payer_account_number: '' as any,
        payer_phone_area_code: '' as any,
        payer_phone_number: '' as any,
        reference: '' as any,
        amount_bs_minor: 0,
        paid_on: new Date().toISOString().slice(0, 10),
        fx_rate_id: '' as any,
    });

    // Amount handling (bank-style)
    const [amountMajor, setAmountMajor] = React.useState<string>('0.00');
    const handleAmountChange = (raw: string) => {
        const digits = String(raw).replace(/\D+/g, '');
        const intVal = digits === '' ? 0 : Number(digits);
        const major = (intVal / 100).toFixed(2);
        setAmountMajor(major);
        setData('amount_bs_minor', intVal);
    };

    // Submit
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('payments.store'), {
            onStart: () => {
                const m = String(data.method || '').toUpperCase();
                if (m !== 'DEB') verifyToastRef.current = toast.loading('Verificando en banco…');
            },
            onFinish: () => {
                if (verifyToastRef.current != null) {
                    toast.dismiss(verifyToastRef.current as any);
                    verifyToastRef.current = null;
                }
            },
            onError: () => {
                toast.error('Corrige los errores del formulario.');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
    };

    // Flash toasts
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error('Validación fallida', { description: flash.error });
        if (flash?.warning) toast.warning?.(flash.warning);
        if (flash?.info) toast.message?.(flash.info);
    }, [flash?.success, flash?.error, flash?.warning, flash?.info]);

    // Friendly error mapping
    const parsedError = React.useMemo(() => {
        const raw: string | undefined = flash?.error;
        if (!raw) return null;
        const m = raw.match(/C[oó]digo?\s(\d{3,4}).*?–\s([^.]*)/i);
        const code = m?.[1];
        const desc = m?.[2]?.trim();
        if (!code) return { title: 'No se pudo registrar el pago', message: raw };
        const map: Record<string, string> = {
            '706': 'El número de cuenta no corresponde con el banco seleccionado. Verifica los 20 dígitos y el banco.',
            '707': 'La referencia ya fue utilizada. Ingresa una referencia diferente.',
            '708': 'La cuenta destino no es válida. Revisa la cuenta de la empresa seleccionada.',
            '709': 'No encontramos la referencia en tu banco. Verifica número y fecha del pago.',
            '710': 'El monto no coincide con el registrado en tu banco. Verifica el monto y vuelve a intentar.',
        };
        const friendly = map[code] ?? (desc ? desc : raw);
        return { title: 'No se pudo registrar el pago', message: friendly, details: `Código ${code}${desc ? ` – ${desc}` : ''}` };
    }, [flash?.error]);

    // FX resolve on date change
    React.useEffect(() => {
        const paid = (data.paid_on || '').trim();
        if (!paid) return;
        (async () => {
            try {
                const qs = new URLSearchParams({ currency: 'USD', paid_on: paid });
                const res = await fetch(`/payments/resolve-fx?${qs.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const json = await res.json();
                if (typeof json.fx_rate_id !== 'undefined') setData('fx_rate_id', json.fx_rate_id ?? '');
                const rate = typeof json.rate_to_ves !== 'undefined' && json.rate_to_ves !== null ? Number(json.rate_to_ves) : null;
                setFxRateUsd(rate !== null && !isNaN(rate) ? rate.toFixed(2) : null);
            } catch (error) {
                console.error(error);
            }
        })();
    }, [data.paid_on, setData]);

    // Helpers: choose debtor
    const onSelectConcessionaire = (id: number) => {
        setData('debtor_type', 'CONCESSIONAIRE');
        setData('debtor_id', id);
        setData('local_id', '');
        const c = (options?.concessionaires ?? []).find((x) => x.id === id);
        if (c) {
            setData('payer_document_type', c.document_type_code || '');
            setData('payer_document_number', c.document_number || '');
        }
    };
    const onSelectLocal = (id: number) => {
        setData('debtor_type', 'LOCAL');
        setData('debtor_id', id);
        setData('local_id', id);
    };

    // Derived
    const amountBs = Number(data.amount_bs_minor ?? 0) / 100;
    const equivUsd = fxRateUsd ? amountBs / Number(fxRateUsd) : null;
    const isPMOV = String(method || '').toUpperCase() === 'PMOV';
    const isDEB = String(method || '').toUpperCase() === 'DEB';

    return (
        <AppLayout>
            <Head title="Crear Pago" />
            <div className="from-background to-muted/20 dark:from-background dark:to-muted/10 min-h-screen bg-gradient-to-br">
                <div className="container mx-auto max-w-5xl px-4 py-10">
                    <div className="mb-6">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="mb-3 gap-2"
                            onClick={() => (step > 0 ? setStep((s) => (s - 1) as any) : history.back())}
                        >
                            <ArrowLeft className="h-4 w-4" /> Volver
                        </Button>
                        <h1 className="text-foreground text-3xl font-bold">Registrar pago (interno)</h1>
                        <p className="text-muted-foreground mt-1">Flujo moderno con verificación automática para Transferencia y Pago Móvil</p>
                    </div>

                    {flash?.error && (
                        <Alert variant="destructive" className="mb-6">
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>{parsedError?.title ?? 'No se pudo registrar el pago'}</AlertTitle>
                            <AlertDescription className="mt-2">
                                {parsedError?.message ?? flash.error}
                                {parsedError?.details && <div className="text-muted-foreground mt-2 text-xs">{parsedError.details}</div>}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Progress */}
                    <div className="mb-8 flex items-center gap-4">
                        {[0, 1, 2].map((i) => (
                            <React.Fragment key={i}>
                                <div className={`flex items-center gap-2 ${step >= i ? 'text-blue-600' : 'text-slate-400'}`}>
                                    <div
                                        className={`flex h-8 w-8 items-center justify-center rounded-full font-semibold ${step >= i ? 'bg-blue-600 text-white' : 'bg-slate-200'}`}
                                    >
                                        {step > i ? <Check className="h-5 w-5" /> : String(i + 1)}
                                    </div>
                                    <span className="hidden text-sm font-medium sm:inline">{i === 0 ? 'Deudor' : i === 1 ? 'Método' : 'Datos'}</span>
                                </div>
                                {i < 2 && <div className={`h-0.5 flex-1 ${step > i ? 'bg-blue-600' : 'bg-slate-200'}`} />}
                            </React.Fragment>
                        ))}
                    </div>

                    {/* Step 0: Debtor */}
                    {step === 0 && (
                        <div className="space-y-6">
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg">Selecciona el deudor</CardTitle>
                                    <CardDescription>Concesionario o Local al cual se acreditará el pago</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <User className="h-4 w-4" /> Tipo de deudor
                                            </Label>
                                            <Select value={String(data.debtor_type)} onValueChange={(v) => setData('debtor_type', v as any)}>
                                                <SelectTrigger className="h-11">
                                                    <SelectValue placeholder="Seleccionar" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="CONCESSIONAIRE">Concesionario</SelectItem>
                                                    <SelectItem value="LOCAL">Local</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Landmark className="h-4 w-4" /> {data.debtor_type === 'LOCAL' ? 'Local' : 'Concesionario'}
                                            </Label>
                                            {data.debtor_type === 'LOCAL' ? (
                                                <Combobox
                                                    options={(options?.locals ?? []).map((l) => ({ value: String(l.id), label: l.label }))}
                                                    value={data.local_id ? String(data.local_id) : ''}
                                                    onChange={(v) => onSelectLocal(Array.isArray(v) ? Number(v[0]) : Number(v))}
                                                    placeholder="Buscar local..."
                                                    searchPlaceholder="Buscar local..."
                                                />
                                            ) : (
                                                <Combobox
                                                    options={(options?.concessionaires ?? []).map((c) => ({
                                                        value: String(c.id),
                                                        label: `${c.document_type_code ? c.document_type_code + ' ' : ''}${c.document_number ? c.document_number + ' - ' : ''}${c.name}`,
                                                    }))}
                                                    value={data.debtor_id ? String(data.debtor_id) : ''}
                                                    onChange={(v) => onSelectConcessionaire(Array.isArray(v) ? Number(v[0]) : Number(v))}
                                                    placeholder="Buscar concesionario..."
                                                    searchPlaceholder="Buscar concesionario..."
                                                />
                                            )}
                                            {(errors.debtor_id || errors.local_id) && (
                                                <p className="mt-1 text-xs text-red-600">{(errors.debtor_id as any) || (errors.local_id as any)}</p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="button"
                                            className="gap-2"
                                            onClick={() => setStep(1)}
                                            disabled={!data.debtor_id && !data.local_id}
                                        >
                                            Continuar <ArrowRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* Step 1: Method */}
                    {step === 1 && (
                        <div className="space-y-6">
                            <Alert className="border-blue-200 bg-blue-50/50">
                                <Info className="h-4 w-4 text-blue-600" />
                                <AlertDescription className="text-blue-900">
                                    Selecciona el método utilizado. Verificaremos automáticamente con el banco.
                                </AlertDescription>
                            </Alert>
                            <div className="grid gap-6 md:grid-cols-3">
                                {/* Transfer */}
                                <button
                                    onClick={() => {
                                        setMethod('TRANSFER');
                                        setData('method', 'TRANSFER');
                                        setStep(2);
                                    }}
                                    className="group text-left"
                                >
                                    <Card className="h-full cursor-pointer border-2 transition-all hover:border-blue-500 hover:shadow-xl">
                                        <CardContent className="pt-8 pb-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 transition-transform group-hover:scale-110">
                                                    <Building2 className="h-10 w-10 text-white" />
                                                </div>
                                                <h3 className="text-foreground mb-2 text-xl font-bold">Transferencia</h3>
                                                <p className="text-muted-foreground mb-4 text-sm">De tu cuenta bancaria a la nuestra</p>
                                                <Badge variant="secondary" className="text-xs">
                                                    Verificación automática
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </button>
                                {/* PMOV */}
                                <button
                                    onClick={() => {
                                        setMethod('PMOV');
                                        setData('method', 'PMOV');
                                        setStep(2);
                                    }}
                                    className="group text-left"
                                >
                                    <Card className="h-full cursor-pointer border-2 transition-all hover:border-green-500 hover:shadow-xl">
                                        <CardContent className="pt-8 pb-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-green-600 transition-transform group-hover:scale-110">
                                                    <Smartphone className="h-10 w-10 text-white" />
                                                </div>
                                                <h3 className="text-foreground mb-2 text-xl font-bold">Pago Móvil</h3>
                                                <p className="text-muted-foreground mb-4 text-sm">Pago C2P desde tu app bancaria</p>
                                                <Badge variant="secondary" className="text-xs">
                                                    Verificación automática
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </button>
                                {/* DEB */}
                                <button
                                    onClick={() => {
                                        setMethod('DEB');
                                        setData('method', 'DEB');
                                        setData('origin_bank_id', '');
                                        setStep(2);
                                    }}
                                    className="group text-left"
                                >
                                    <Card className="h-full cursor-pointer border-2 transition-all hover:border-slate-500 hover:shadow-xl">
                                        <CardContent className="pt-8 pb-6">
                                            <div className="flex flex-col items-center text-center">
                                                <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-slate-400 to-slate-500 transition-transform group-hover:scale-110">
                                                    <CreditCard className="h-10 w-10 text-white" />
                                                </div>
                                                <h3 className="text-foreground mb-2 text-xl font-bold">Débito (POS)</h3>
                                                <p className="text-muted-foreground mb-4 text-sm">Confirmación automática (sin banco)</p>
                                                <Badge variant="secondary" className="text-xs">
                                                    Auto-confirma
                                                </Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Form */}
                    {step === 2 && method && (
                        <form onSubmit={submit} className="space-y-6">
                            <Card>
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-3">
                                        {method === 'TRANSFER' ? (
                                            <>
                                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                                                    <Building2 className="h-5 w-5 text-blue-600" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Datos de la transferencia</CardTitle>
                                                    <CardDescription>Completa la información</CardDescription>
                                                </div>
                                            </>
                                        ) : method === 'PMOV' ? (
                                            <>
                                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
                                                    <Smartphone className="h-5 w-5 text-green-600" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Datos del pago móvil</CardTitle>
                                                    <CardDescription>Completa la información</CardDescription>
                                                </div>
                                            </>
                                        ) : (
                                            <>
                                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800/40">
                                                    <CreditCard className="h-5 w-5 text-slate-700" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Datos del débito</CardTitle>
                                                    <CardDescription>Confirmación automática</CardDescription>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-6">
                                    {/* Cuenta receptora */}
                                    <div>
                                        <Label className="mb-2 flex items-center gap-2">
                                            <CreditCard className="text-muted-foreground h-4 w-4" /> Cuenta receptora
                                        </Label>
                                        <Select
                                            value={String(data.company_bank_account_id || '')}
                                            onValueChange={(v) => setData('company_bank_account_id', Number(v))}
                                        >
                                            <SelectTrigger className="h-12">
                                                <SelectValue placeholder="Selecciona la cuenta" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(options.companyBankAccounts || [])
                                                    .filter((acc) => (isPMOV ? !!acc.supportsPMOV : true))
                                                    .map((o) => (
                                                        <SelectItem key={o.id} value={String(o.id)}>
                                                            {o.label}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.company_bank_account_id && (
                                            <p className="mt-1 text-xs text-red-600">{errors.company_bank_account_id}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-6 md:grid-cols-2">
                                        {/* Banco origen (no requerido ni visible en DEB) */}
                                        {!isDEB && (
                                            <div>
                                                <Label className="mb-2 flex items-center gap-2">
                                                    <Building2 className="text-muted-foreground h-4 w-4" /> Banco origen
                                                </Label>
                                                <Combobox
                                                    options={bankOptions}
                                                    value={String(data.origin_bank_id || '')}
                                                    onChange={(v) => setData('origin_bank_id', Array.isArray(v) ? (v[0] ?? '') : v)}
                                                    placeholder="¿Desde qué banco?"
                                                    searchPlaceholder="Buscar banco..."
                                                    leadingIcon={Building2}
                                                    className="h-12"
                                                />
                                                {errors.origin_bank_id && <p className="mt-1 text-xs text-red-600">{errors.origin_bank_id}</p>}
                                            </div>
                                        )}

                                        {/* Referencia */}
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Hash className="text-muted-foreground h-4 w-4" /> Referencia
                                            </Label>
                                            <Input
                                                value={String(data.reference || '')}
                                                onChange={(e) => setData('reference', e.target.value.replace(/\D+/g, '').slice(0, 12))}
                                                placeholder="Ej: 123456"
                                                className="h-12"
                                                maxLength={12}
                                                required
                                            />
                                            <p className="mt-1 text-xs text-slate-500">6 a 12 dígitos</p>
                                            {errors.reference && <p className="mt-1 text-xs text-red-600">{errors.reference}</p>}
                                        </div>
                                    </div>

                                    {/* Método-condicional */}
                                    {method === 'TRANSFER' ? (
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <CreditCard className="h-4 w-4 text-slate-500" /> Cuenta del pagador (20 dígitos)
                                            </Label>
                                            <Input
                                                value={String(data.payer_account_number || '')}
                                                onChange={(e) => setData('payer_account_number', e.target.value.replace(/\D+/g, '').slice(0, 20))}
                                                placeholder="01020123456789012345"
                                                className="h-12"
                                                maxLength={20}
                                                required
                                            />
                                            {errors.payer_account_number && (
                                                <p className="mt-1 text-xs text-red-600">{errors.payer_account_number}</p>
                                            )}
                                        </div>
                                    ) : method === 'PMOV' ? (
                                        <div className="space-y-3">
                                            <Label className="flex items-center gap-2">
                                                <Phone className="text-muted-foreground h-4 w-4" /> Teléfono del pagador
                                            </Label>
                                            <div className="flex gap-3">
                                                <div className="w-32">
                                                    <Select
                                                        value={String(data.payer_phone_area_code || '')}
                                                        onValueChange={(v) => setData('payer_phone_area_code', v)}
                                                        required
                                                    >
                                                        <SelectTrigger className="h-12">
                                                            <SelectValue placeholder="Código" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {areaOptions.map((a) => (
                                                                <SelectItem key={a.value} value={a.value}>
                                                                    {a.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <p className="text-muted-foreground mt-1 text-xs">Código</p>
                                                </div>
                                                <div className="flex-1">
                                                    <Input
                                                        value={String(data.payer_phone_number || '')}
                                                        onChange={(e) =>
                                                            setData('payer_phone_number', e.target.value.replace(/\D+/g, '').slice(0, 7))
                                                        }
                                                        placeholder="1234567"
                                                        className="h-12"
                                                        maxLength={7}
                                                        required
                                                    />
                                                    <p className="text-muted-foreground mt-1 text-xs">Número (7 dígitos)</p>
                                                </div>
                                            </div>
                                            {(errors.payer_phone_area_code || errors.payer_phone_number) && (
                                                <p className="mt-1 text-xs text-red-600">
                                                    {(errors as any).payer_phone_area_code || (errors as any).payer_phone_number}
                                                </p>
                                            )}
                                        </div>
                                    ) : null}

                                    <div className="grid gap-6 md:grid-cols-2">
                                        {/* Monto */}
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Banknote className="text-muted-foreground h-4 w-4" /> Monto (Bs)
                                            </Label>
                                            <Input
                                                value={amountMajor}
                                                onChange={(e) => handleAmountChange(e.target.value)}
                                                placeholder="0.00"
                                                className="h-12 text-lg font-semibold"
                                                inputMode="numeric"
                                                required
                                            />
                                            {errors.amount_bs_minor && <p className="mt-1 text-xs text-red-600">{errors.amount_bs_minor}</p>}
                                        </div>

                                        {/* Fecha */}
                                        <div>
                                            <Label className="mb-2 flex items-center gap-2">
                                                <Calendar className="text-muted-foreground h-4 w-4" /> Fecha del pago
                                            </Label>
                                            <Input
                                                type="date"
                                                value={String(data.paid_on || '')}
                                                onChange={(e) => setData('paid_on', e.target.value)}
                                                className="h-12"
                                                required
                                            />
                                            {errors.paid_on && <p className="mt-1 text-xs text-red-600">{errors.paid_on}</p>}
                                        </div>
                                    </div>

                                    {/* Payer document */}
                                    <div className="border-t pt-6">
                                        <Label className="text-muted-foreground mb-3 flex items-center gap-2">
                                            <User className="h-4 w-4" /> Datos del pagador
                                        </Label>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <Label className="text-muted-foreground text-xs">Tipo de documento</Label>
                                                <Select
                                                    value={String(data.payer_document_type || '')}
                                                    onValueChange={(v) => setData('payer_document_type', v)}
                                                >
                                                    <SelectTrigger className="bg-muted h-10">
                                                        <SelectValue placeholder="Seleccionar" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="V">V</SelectItem>
                                                        <SelectItem value="E">E</SelectItem>
                                                        <SelectItem value="J">J</SelectItem>
                                                        <SelectItem value="G">G</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {errors.payer_document_type && (
                                                    <p className="mt-1 text-xs text-red-600">{errors.payer_document_type}</p>
                                                )}
                                            </div>
                                            <div>
                                                <Label className="text-muted-foreground text-xs">Número de documento</Label>
                                                <Input
                                                    value={String(data.payer_document_number || '')}
                                                    onChange={(e) =>
                                                        setData('payer_document_number', e.target.value.replace(/\D+/g, '').slice(0, 12))
                                                    }
                                                    maxLength={12}
                                                    className="bg-muted h-10"
                                                    required
                                                />
                                                {errors.payer_document_number && (
                                                    <p className="mt-1 text-xs text-red-600">{errors.payer_document_number}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* FX info */}
                                    {fxRateUsd && (
                                        <Alert className="border-border bg-muted/50">
                                            <Info className="h-4 w-4" />
                                            <AlertDescription className="text-sm">
                                                <div className="mb-1 font-medium">Tasa USD del día: Bs {fxRateUsd}</div>
                                                {equivUsd !== null && (
                                                    <div className="font-medium text-blue-600">
                                                        Equivalente aproximado: ${equivUsd.toFixed(2)} USD
                                                    </div>
                                                )}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="flex items-center justify-between">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => {
                                        setStep(1);
                                        setMethod(null);
                                        setData('method', '');
                                    }}
                                >
                                    <ArrowLeft className="h-4 w-4" /> Cambiar método
                                </Button>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing}
                                    className="gap-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800"
                                >
                                    {processing ? 'Verificando...' : 'Registrar pago'} <ArrowRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
