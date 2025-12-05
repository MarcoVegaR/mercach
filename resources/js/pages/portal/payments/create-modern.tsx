import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm, usePage } from '@inertiajs/react';
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
    Phone,
    Smartphone,
    User,
    Wallet,
} from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface CompanyAccountOption {
    id: number;
    label: string;
    allow_transfer: boolean;
    allow_pmov: boolean;
    allow_debit: boolean;
}
interface Bank {
    id: number;
    name: string;
}
interface PhoneAreaCode {
    id: number;
    code: string;
}

type Props = {
    options: {
        companyBankAccounts: CompanyAccountOption[];
        banks: Bank[];
        phoneAreaCodes: PhoneAreaCode[];
    };
    defaults: { payer_document_type?: string; payer_document_number?: string };
};

export default function PortalPaymentCreateModern({ options, defaults }: Props) {
    const page = usePage<{ flash?: { success?: string; error?: string; warning?: string; info?: string } }>();
    const flash = (page.props as any)?.flash ?? {};
    const [step, setStep] = React.useState(1);
    const [method, setMethod] = React.useState<'TRANSFER' | 'PMOV' | 'DEB' | null>(null);
    const verifyToastRef = React.useRef<string | number | null>(null);
    const [fxRateUsd, setFxRateUsd] = React.useState<string | null>(null);
    const [fxRateEur, setFxRateEur] = React.useState<string | null>(null);

    const todayCaracas = React.useMemo(
        () =>
            new Intl.DateTimeFormat('en-CA', {
                timeZone: 'America/Caracas',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()),
        [],
    );

    const bankOptions = React.useMemo(() => (options?.banks ?? []).map((b) => ({ value: String(b.id), label: b.name })), [options?.banks]);

    const { data, setData, processing, post, errors } = useForm({
        company_bank_account_id: '',
        method: '',
        origin_bank_id: '',
        payer_document_type: defaults?.payer_document_type || '',
        payer_document_number: defaults?.payer_document_number || '',
        payer_account_number: '',
        payer_phone_area_code: '',
        payer_phone_number: '',
        reference: '',
        amount_bs_minor: 0,
        paid_on: todayCaracas,
        fx_rate_id: '' as any,
    });

    const filteredCompanyAccounts = React.useMemo(() => {
        const list = options?.companyBankAccounts ?? [];
        if (!method) return list;
        return list.filter((acc) => {
            if (method === 'TRANSFER') return acc.allow_transfer;
            if (method === 'PMOV') return acc.allow_pmov;
            if (method === 'DEB') return acc.allow_debit;
            return true;
        });
    }, [options?.companyBankAccounts, method]);

    // Auto-select / reset company bank account based on method
    React.useEffect(() => {
        if (!method) return;
        const allowed = filteredCompanyAccounts;
        const selected = String(data.company_bank_account_id || '');
        const isAllowed = allowed.some((acc) => String(acc.id) === selected);
        if (!isAllowed) {
            const first = allowed.length === 1 ? String(allowed[0].id) : '';
            setData('company_bank_account_id', first);
        }
    }, [method, filteredCompanyAccounts, data.company_bank_account_id, setData]);

    // Amount handling
    const [amountMajor, setAmountMajor] = React.useState<string>('0.00');
    const handleAmountChange = (raw: string) => {
        const digits = String(raw).replace(/\D+/g, '');
        const intVal = digits === '' ? 0 : Number(digits);
        const major = (intVal / 100).toFixed(2);
        setAmountMajor(major);
        setData('amount_bs_minor', intVal);
    };

    // Select method and go to step 2
    const selectMethod = (m: 'TRANSFER' | 'PMOV' | 'DEB') => {
        setMethod(m);
        setData('method', m);
        setStep(2);
    };

    // Submit form
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const m = String(method || '').toUpperCase();
        const refDigits = String(data.reference ?? '').replace(/\D+/g, '');
        if (m !== 'EXO' && !data.company_bank_account_id) {
            toast.error('Selecciona la cuenta receptora.');
            return;
        }
        if (m !== 'EXO' && (refDigits.length < 6 || refDigits.length > 8)) {
            toast.error('La referencia debe tener entre 6 y 8 dígitos.');
            return;
        }

        post(route('portal.payments.store'), {
            onStart: () => {
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

    // Show flash toasts when present
    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error('Validación fallida', { description: flash.error });
        if (flash?.warning) toast.warning?.(flash.warning);
        if (flash?.info) toast.message?.(flash.info);
    }, [flash?.success, flash?.error, flash?.warning, flash?.info]);

    // Friendly error mapping for bank gateway errors
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

    // Fetch FX rates when date changes
    React.useEffect(() => {
        const paid = (data.paid_on || '').trim();
        if (!paid) return;
        const fetchFx = async (cur: 'USD' | 'EUR') => {
            const qs = new URLSearchParams({ currency: cur, paid_on: paid });
            const res = await fetch(`/portal/pagos/resolve-fx?${qs.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const json = await res.json();
            const rate = typeof json.rate_to_ves !== 'undefined' && json.rate_to_ves !== null ? Number(json.rate_to_ves) : null;
            if (cur === 'USD') {
                if (typeof json.fx_rate_id !== 'undefined') setData('fx_rate_id', json.fx_rate_id ?? '');
                setFxRateUsd(rate !== null && !isNaN(rate) ? rate.toFixed(2) : null);
            } else {
                setFxRateEur(rate !== null && !isNaN(rate) ? rate.toFixed(2) : null);
            }
        };
        Promise.allSettled([fetchFx('USD'), fetchFx('EUR')]).catch(() => {});
    }, [data.paid_on, setData]);

    return (
        <div className="from-background to-muted/20 dark:from-background dark:to-muted/10 min-h-screen bg-gradient-to-br">
            <div className="container mx-auto max-w-4xl px-4 py-12">
                {/* Header */}
                <div className="mb-8">
                    <Link href="/portal">
                        <Button variant="ghost" size="sm" className="mb-4 gap-2">
                            <ArrowLeft className="h-4 w-4" />
                            Volver al portal
                        </Button>
                    </Link>

                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h1 className="text-foreground text-4xl font-bold tracking-tight">Registrar un pago</h1>
                            <p className="text-muted-foreground mt-2 text-lg">Ingresa los datos de tu transferencia o pago móvil</p>
                        </div>
                    </div>

                    {/* Error Alert */}
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

                    {/* Progress steps */}
                    <div className="mb-8 flex items-center gap-4">
                        <div className={`flex items-center gap-2 ${step >= 1 ? 'text-blue-600' : 'text-slate-400'}`}>
                            <div
                                className={`flex h-8 w-8 items-center justify-center rounded-full font-semibold ${
                                    step >= 1 ? 'bg-blue-600 text-white' : 'bg-slate-200'
                                }`}
                            >
                                {step > 1 ? <Check className="h-5 w-5" /> : '1'}
                            </div>
                            <span className="hidden text-sm font-medium sm:inline">Método</span>
                        </div>

                        <div className={`h-0.5 flex-1 ${step >= 2 ? 'bg-blue-600' : 'bg-slate-200'}`} />

                        <div className={`flex items-center gap-2 ${step >= 2 ? 'text-blue-600' : 'text-slate-400'}`}>
                            <div
                                className={`flex h-8 w-8 items-center justify-center rounded-full font-semibold ${
                                    step >= 2 ? 'bg-blue-600 text-white' : 'bg-slate-200'
                                }`}
                            >
                                {step > 2 ? <Check className="h-5 w-5" /> : '2'}
                            </div>
                            <span className="hidden text-sm font-medium sm:inline">Datos</span>
                        </div>

                        <div className={`h-0.5 flex-1 ${step >= 3 ? 'bg-blue-600' : 'bg-slate-200'}`} />

                        <div className={`flex items-center gap-2 ${step >= 3 ? 'text-blue-600' : 'text-slate-400'}`}>
                            <div
                                className={`flex h-8 w-8 items-center justify-center rounded-full font-semibold ${
                                    step >= 3 ? 'bg-blue-600 text-white' : 'bg-slate-200'
                                }`}
                            >
                                3
                            </div>
                            <span className="hidden text-sm font-medium sm:inline">Confirmar</span>
                        </div>
                    </div>
                </div>

                {/* Step 1: Choose method */}
                {step === 1 && (
                    <div className="space-y-6">
                        <Alert className="border-blue-200 bg-blue-50/50">
                            <Info className="h-4 w-4 text-blue-600" />
                            <AlertDescription className="text-blue-900">
                                Selecciona el método que utilizaste para realizar tu pago. Verificaremos automáticamente la transacción con el banco.
                            </AlertDescription>
                        </Alert>

                        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {/* Transfer Card */}
                            <button onClick={() => selectMethod('TRANSFER')} className="group text-left">
                                <Card className="h-full cursor-pointer border-2 transition-all hover:border-blue-500 hover:shadow-xl">
                                    <CardContent className="pt-8 pb-6">
                                        <div className="flex flex-col items-center text-center">
                                            <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 transition-transform group-hover:scale-110">
                                                <Building2 className="h-10 w-10 text-white" />
                                            </div>
                                            <h3 className="text-foreground mb-2 text-2xl font-bold">Transferencia</h3>
                                            <p className="text-muted-foreground mb-4 text-sm">Pago desde tu cuenta bancaria a nuestra cuenta</p>
                                            <Badge variant="secondary" className="text-xs">
                                                Verificación automática
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            </button>

                            {/* Pago Móvil Card */}
                            <button onClick={() => selectMethod('PMOV')} className="group text-left">
                                <Card className="h-full cursor-pointer border-2 transition-all hover:border-green-500 hover:shadow-xl">
                                    <CardContent className="pt-8 pb-6">
                                        <div className="flex flex-col items-center text-center">
                                            <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-green-600 transition-transform group-hover:scale-110">
                                                <Smartphone className="h-10 w-10 text-white" />
                                            </div>
                                            <h3 className="text-foreground mb-2 text-2xl font-bold">Pago Móvil</h3>
                                            <p className="text-muted-foreground mb-4 text-sm">Pago desde tu app de banca móvil con C2P</p>
                                            <Badge variant="secondary" className="text-xs">
                                                Verificación automática
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            </button>
                        </div>
                    </div>
                )}

                {/* Step 2: Form data */}
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
                                                <CardDescription>Completa la información de tu transferencia bancaria</CardDescription>
                                            </div>
                                        </>
                                    ) : method === 'PMOV' ? (
                                        <>
                                            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
                                                <Smartphone className="h-5 w-5 text-green-600" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg">Datos del pago móvil</CardTitle>
                                                <CardDescription>Completa la información de tu pago móvil C2P</CardDescription>
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
                                                <Wallet className="h-5 w-5 text-amber-600" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg">Datos del pago con débito</CardTitle>
                                                <CardDescription>Selecciona la cuenta destino asociada al POS</CardDescription>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-6">
                                {/* Cuenta receptora */}
                                <div>
                                    <Label className="mb-2 flex items-center gap-2">
                                        <CreditCard className="text-muted-foreground h-4 w-4" />
                                        ¿A qué cuenta hiciste el pago?
                                    </Label>
                                    <Select value={String(data.company_bank_account_id)} onValueChange={(v) => setData('company_bank_account_id', v)}>
                                        <SelectTrigger className="h-12">
                                            <SelectValue placeholder="Selecciona la cuenta destino" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filteredCompanyAccounts.map((o) => (
                                                <SelectItem key={o.id} value={String(o.id)}>
                                                    {o.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.company_bank_account_id && <p className="mt-1 text-xs text-red-600">{errors.company_bank_account_id}</p>}
                                </div>

                                <div className="grid gap-6 md:grid-cols-2">
                                    {/* Banco origen */}
                                    <div>
                                        <Label className="mb-2 flex items-center gap-2">
                                            <Building2 className="text-muted-foreground h-4 w-4" />
                                            Tu banco
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

                                    {/* Referencia */}
                                    <div>
                                        <Label className="mb-2 flex items-center gap-2">
                                            <Hash className="text-muted-foreground h-4 w-4" />
                                            Número de referencia
                                        </Label>
                                        <Input
                                            value={data.reference}
                                            onChange={(e) =>
                                                setData('reference', e.target.value.replace(/\D+/g, '').slice(0, method === 'TRANSFER' ? 8 : 6))
                                            }
                                            placeholder={method === 'TRANSFER' ? 'Ingresa los últimos 8 dígitos' : 'Ingresa los últimos 6 dígitos'}
                                            className="h-12"
                                            maxLength={method === 'TRANSFER' ? 8 : 6}
                                            required
                                        />
                                        <p className="mt-1 text-xs text-slate-500">
                                            {method === 'TRANSFER'
                                                ? 'Para transferencias, ingresa los 8 últimos dígitos de la referencia de tu comprobante.'
                                                : method === 'PMOV'
                                                  ? 'Para pago móvil, ingresa los 6 últimos dígitos de la referencia de tu comprobante.'
                                                  : 'Para débito, ingresa la referencia del voucher (6 dígitos).'}
                                        </p>
                                        {errors.reference && <p className="mt-1 text-xs text-red-600">{errors.reference}</p>}
                                    </div>
                                </div>

                                {/* Conditionally show account or phone */}
                                {method === 'TRANSFER' ? (
                                    <div>
                                        <Label className="mb-2 flex items-center gap-2">
                                            <CreditCard className="h-4 w-4 text-slate-500" />
                                            Tu cuenta bancaria (20 dígitos)
                                        </Label>
                                        <Input
                                            value={data.payer_account_number}
                                            onChange={(e) => setData('payer_account_number', e.target.value.replace(/\D+/g, '').slice(0, 20))}
                                            placeholder="01020123456789012345"
                                            className="h-12"
                                            maxLength={20}
                                            required
                                        />
                                        {errors.payer_account_number && <p className="mt-1 text-xs text-red-600">{errors.payer_account_number}</p>}
                                    </div>
                                ) : method === 'PMOV' ? (
                                    <div className="space-y-3">
                                        <Label className="flex items-center gap-2">
                                            <Phone className="text-muted-foreground h-4 w-4" />
                                            Tu teléfono (pago móvil)
                                        </Label>
                                        <div className="flex gap-3">
                                            <div className="w-32">
                                                <Select
                                                    value={data.payer_phone_area_code || undefined}
                                                    onValueChange={(v) => setData('payer_phone_area_code', v)}
                                                    required
                                                >
                                                    <SelectTrigger className="h-12">
                                                        <SelectValue placeholder="Código" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {(options?.phoneAreaCodes ?? []).map((area) => (
                                                            <SelectItem key={area.id} value={area.code}>
                                                                {area.code}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <p className="text-muted-foreground mt-1 text-xs">Código</p>
                                            </div>
                                            <div className="flex-1">
                                                <Input
                                                    value={data.payer_phone_number}
                                                    onChange={(e) => setData('payer_phone_number', e.target.value.replace(/\D+/g, '').slice(0, 7))}
                                                    placeholder="1234567"
                                                    className="h-12"
                                                    maxLength={7}
                                                    required
                                                />
                                                <p className="text-muted-foreground mt-1 text-xs">Número (7 dígitos)</p>
                                            </div>
                                        </div>
                                        {(errors.payer_phone_area_code || errors.payer_phone_number) && (
                                            <p className="mt-1 text-xs text-red-600">{errors.payer_phone_area_code || errors.payer_phone_number}</p>
                                        )}
                                    </div>
                                ) : (
                                    <Alert className="border-slate-200 bg-slate-50/80 text-slate-700">
                                        <AlertDescription className="text-sm">
                                            Para pagos con débito solo selecciona la cuenta destino asociada al POS y la referencia del voucher. No
                                            necesitas indicar teléfono ni cuenta.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="grid gap-6 md:grid-cols-2">
                                    {/* Monto */}
                                    <div>
                                        <Label className="mb-2 flex items-center gap-2">
                                            <Banknote className="text-muted-foreground h-4 w-4" />
                                            Monto pagado (Bs)
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
                                            <Calendar className="text-muted-foreground h-4 w-4" />
                                            Fecha del pago
                                        </Label>
                                        <Input
                                            type="date"
                                            value={data.paid_on}
                                            onChange={(e) => setData('paid_on', e.target.value)}
                                            className="h-12"
                                            required
                                        />
                                        {errors.paid_on && <p className="mt-1 text-xs text-red-600">{errors.paid_on}</p>}
                                    </div>
                                </div>

                                {/* Document info (pre-filled, read-only style) */}
                                <div className="border-t pt-6">
                                    <Label className="text-muted-foreground mb-3 flex items-center gap-2">
                                        <User className="h-4 w-4" />
                                        Datos del pagador (pre-cargados)
                                    </Label>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <Label className="text-muted-foreground text-xs">Tipo de documento</Label>
                                            <Select
                                                value={String(data.payer_document_type || '')}
                                                onValueChange={(v) => setData('payer_document_type', v)}
                                            >
                                                <SelectTrigger className="bg-muted h-10">
                                                    <SelectValue placeholder="Seleccionar tipo" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="V">V</SelectItem>
                                                    <SelectItem value="E">E</SelectItem>
                                                    <SelectItem value="J">J</SelectItem>
                                                    <SelectItem value="G">G</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {errors.payer_document_type && <p className="mt-1 text-xs text-red-600">{errors.payer_document_type}</p>}
                                        </div>
                                        <div>
                                            <Label className="text-muted-foreground text-xs">Número de documento</Label>
                                            <Input
                                                value={data.payer_document_number}
                                                onChange={(e) => setData('payer_document_number', e.target.value.replace(/\D+/g, '').slice(0, 12))}
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

                                {/* FX rates info */}
                                {(fxRateUsd || fxRateEur) && (
                                    <Alert className="border-border bg-muted/50">
                                        <Info className="h-4 w-4" />
                                        <AlertDescription className="text-sm">
                                            <div className="mb-1 font-medium">Tasa de cambio del día:</div>
                                            <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1">
                                                {fxRateUsd && <span>USD: Bs {fxRateUsd}</span>}
                                                {fxRateEur && <span>EUR: Bs {fxRateEur}</span>}
                                            </div>
                                            {data.amount_bs_minor > 0 &&
                                                (() => {
                                                    const amountBs = data.amount_bs_minor / 100;
                                                    const usd = fxRateUsd ? amountBs / Number(fxRateUsd) : null;
                                                    const eur = fxRateEur ? amountBs / Number(fxRateEur) : null;
                                                    return (
                                                        <div className="mt-2 font-medium text-blue-600">
                                                            Equivalente aproximado:
                                                            {usd && ` $${usd.toFixed(2)}`}
                                                            {usd && eur && ' • '}
                                                            {eur && ` €${eur.toFixed(2)}`}
                                                        </div>
                                                    );
                                                })()}
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        <div className="flex items-center justify-between">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setStep(1);
                                    setMethod(null);
                                    setData('method', '');
                                }}
                                className="gap-2"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Cambiar método
                            </Button>

                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                                className="gap-2 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800"
                            >
                                {processing ? 'Verificando...' : 'Registrar pago'}
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}

PortalPaymentCreateModern.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
