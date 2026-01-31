import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatDateShort } from '@/lib/date-utils';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    CreditCard,
    Filter,
    Loader2,
    MapPin,
    RefreshCw,
    Wallet,
    Zap,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

// -----------------------------------------------------------------------------
// Types
// -----------------------------------------------------------------------------
type Charge = {
    charge_id: number;
    period: string;
    due_on: string;
    outstanding_bs_minor: number;
    outstanding_currency_minor?: number;
    amount_minor?: number;
    kind: string;
    currency?: string;
    local_id?: number;
    local_label?: string | null;
};

type PaymentVM = {
    id: number;
    paid_on?: string;
    debtor_type?: string;
    debtor_id?: number;
    local_id?: number;
    status?: string;
    amount_bs_minor?: number;
    applied_bs_minor?: number;
    available_bs_minor?: number;
};

type Props = {
    payment: PaymentVM;
    customerCreditBsMinor?: number;
    onApplied?: () => void;
};

type LocalGroup = {
    id: number;
    label: string;
    charges: Charge[];
    totalDebt: number;
    overdueDebt: number;
    overdueCount: number;
    _eurMinor: number;
    _usdMinor: number;
    _vesMinor: number;
};

type FxRates = {
    EUR?: number | null;
    USD?: number | null;
};

type SelectedItem = {
    charge_id: number;
    amount_bs_minor: number;
};

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
function fmtMinor(minor?: number | null): string {
    if (typeof minor !== 'number') return '—';
    return `Bs ${(minor / 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtDate(dateStr?: string): string {
    return formatDateShort(dateStr);
}

function formatPeriod(period: string): string {
    try {
        const [year, month] = period.split('-');
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${months[parseInt(month, 10) - 1]} ${year}`;
    } catch {
        return period;
    }
}

function formatChargeKind(kind: string): string {
    const k = (kind || '').toUpperCase();
    if (k === 'FINE') return 'Cargo por multa';
    if (k === 'ADJ') return 'Cargo por ajuste';

    const map: Record<string, string> = {
        RENT_EUR_M2: 'Alquiler m²',
        RENT_EUR_FIXED: 'Alquiler fijo',
        CONDO_USD: 'Condominio',
    };

    return map[k] || kind.replace(/_/g, ' ');
}

function cleanLocalLabel(label: string | null | undefined): string {
    if (!label) return 'Sin local';
    const parts = label
        .split(/\s*[•·-]\s*/)
        .map((p) => p.trim())
        .filter(Boolean);
    const unique = [...new Set(parts)];
    return unique.join(' - ') || label;
}

// Convert currency to Bs using rate (sum-then-convert for accuracy)
// Uses Math.round to match backend FxConversionHelper::toVes() behavior
function fxToBsMinor(amountMinor: number, rateToVes?: number | null): number {
    if (!rateToVes || rateToVes <= 0) return 0;
    const rateMinor = Math.round(rateToVes * 100);
    if (rateMinor <= 0) return 0;
    return Math.round((amountMinor * rateMinor) / 100);
}

function isOverdue(dueOn: string | undefined, paidOn: string | undefined): boolean {
    if (!dueOn || !paidOn) return false;
    try {
        return new Date(dueOn) < new Date(paidOn);
    } catch {
        return false;
    }
}

function maxAllowedForCharge(charge: Charge): number {
    const max = Math.max(0, charge.outstanding_bs_minor || 0);
    const ccy = (charge.currency || 'VES').toUpperCase();
    if (ccy === 'EUR' || ccy === 'USD') {
        return max + 1;
    }
    return max;
}

function getCookie(name: string): string {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return decodeURIComponent(parts.pop()!.split(';').shift() || '');
    return '';
}

// -----------------------------------------------------------------------------
// Amount Input Component - bank-style auto-decimals (same as payment form)
// Typing digits auto-formats: "12345" -> "123.45"
// -----------------------------------------------------------------------------
function AmountInput({
    value,
    maxValue,
    onChange,
    isSelected,
    hasError,
}: {
    value: number; // in minor (cents)
    maxValue: number; // in minor (cents)
    onChange: (minor: number) => void;
    isSelected: boolean;
    hasError: boolean;
}) {
    // Local display value (major format: "123.45")
    const [displayValue, setDisplayValue] = React.useState(() => (value > 0 ? (value / 100).toFixed(2) : '0.00'));

    // Sync from parent when value changes externally
    React.useEffect(() => {
        setDisplayValue(value > 0 ? (value / 100).toFixed(2) : '0.00');
    }, [value]);

    // Bank-style: extract digits, divide by 100, format
    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const raw = e.target.value;
        const digits = raw.replace(/\D+/g, ''); // Only digits
        const intVal = digits === '' ? 0 : Number(digits);
        const capped = Math.min(intVal, maxValue); // Cap to max
        const major = (capped / 100).toFixed(2);
        setDisplayValue(major);
        onChange(capped);
    };

    const handleMax = () => {
        onChange(maxValue);
        setDisplayValue((maxValue / 100).toFixed(2));
    };

    const handleClear = () => {
        onChange(0);
        setDisplayValue('0.00');
    };

    return (
        <div className="flex items-center gap-1">
            <div className="relative">
                <span className="pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-xs text-slate-400">Bs</span>
                <Input
                    type="text"
                    inputMode="numeric"
                    value={displayValue}
                    onChange={handleChange}
                    placeholder="0.00"
                    className={cn(
                        'h-10 w-28 pr-2 pl-7 text-right font-mono text-base',
                        isSelected && !hasError && 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/20',
                        hasError && 'border-red-500 bg-red-50 text-red-700',
                    )}
                />
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-10 px-3 text-xs font-medium"
                onClick={handleMax}
                title="Aplicar monto máximo"
            >
                Máx
            </Button>
            {isSelected && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-10 w-10 p-0 text-slate-400 hover:text-red-500"
                    onClick={handleClear}
                    title="Limpiar"
                >
                    ×
                </Button>
            )}
        </div>
    );
}

// -----------------------------------------------------------------------------
// Component
// -----------------------------------------------------------------------------
export function PaymentApplyAdmin({ payment, customerCreditBsMinor = 0, onApplied }: Props) {
    // State
    const [loading, setLoading] = React.useState(true);
    const [submitting, setSubmitting] = React.useState(false);
    const [confirmOpen, setConfirmOpen] = React.useState(false);
    const [confirmPayload, setConfirmPayload] = React.useState<{
        items: SelectedItem[];
        useCredit: boolean;
        summary: unknown;
    } | null>(null);
    const [charges, setCharges] = React.useState<Charge[]>([]);
    const [fxRates, setFxRates] = React.useState<FxRates>({});
    const [selectedItems, setSelectedItems] = React.useState<Record<number, number>>({}); // charge_id -> amount
    const [useCredit, setUseCredit] = React.useState(customerCreditBsMinor > 0);
    const [errors, setErrors] = React.useState<string[]>([]);
    const [rowIssues, setRowIssues] = React.useState<Record<number, string | null>>({});
    const [expandedLocals, setExpandedLocals] = React.useState<Set<number>>(new Set());
    const [showFilters, setShowFilters] = React.useState(false);
    const [filters, setFilters] = React.useState<{
        currency?: string;
        kind?: string;
        overdue_only?: boolean;
    }>({});

    const prefillChargeIds = React.useMemo((): number[] => {
        if (typeof window === 'undefined') return [];
        const qs = new URLSearchParams(window.location.search);
        const raw = (qs.get('charge_ids') || '').trim();
        if (!raw) return [];
        return raw
            .split(',')
            .map((x) => Number(String(x).trim()))
            .filter((n) => Number.isFinite(n) && n > 0);
    }, []);
    const prefillAppliedRef = React.useRef(false);

    const isConfirmed = payment.status === 'CONFIRMED';
    const availablePayment = payment.available_bs_minor ?? 0;
    const totalAvailable = availablePayment + (useCredit ? customerCreditBsMinor : 0);

    // Fetch charges on mount
    const fetchCharges = React.useCallback(async () => {
        if (!payment.paid_on) return;
        setLoading(true);
        setErrors([]);
        try {
            const isLocal = !!payment.local_id;
            const qs = new URLSearchParams({
                debtor_type: isLocal ? 'LOCAL' : String(payment.debtor_type ?? ''),
                debtor_id: isLocal ? String(payment.local_id ?? '') : String(payment.debtor_id ?? ''),
                paid_on: String(payment.paid_on ?? ''),
            });
            if (filters.currency) qs.set('currency', filters.currency);
            if (filters.kind) qs.set('kind', filters.kind);
            if (filters.overdue_only) qs.set('overdue_only', '1');

            const res = await fetch(`/payments/open-charges?${qs.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('open_charges_failed');
            const json = await res.json();
            const chargesData: Charge[] = Array.isArray(json.items) ? json.items : [];
            setCharges(chargesData);
            // Store FX rates for sum-then-convert
            if (json.fx_rates) {
                setFxRates(json.fx_rates);
            }
            setSelectedItems({});
            setRowIssues({});

            if (chargesData.length > 0) {
                toast.success(`${chargesData.length} cargos pendientes encontrados`);
            } else {
                toast.info('No hay cargos pendientes');
            }
        } catch {
            setErrors(['No se pudieron cargar los cargos pendientes.']);
            toast.error('Error al cargar cargos');
        } finally {
            setLoading(false);
        }
    }, [payment, filters]);

    React.useEffect(() => {
        fetchCharges();
    }, [fetchCharges]);

    // Apply handoff selection (from Economic Profile) once, after charges are loaded.
    // Uses sum-then-convert to calculate total, avoiding rounding differences when
    // the backend calculated outstanding with a larger batch of charges.
    React.useEffect(() => {
        if (prefillAppliedRef.current) return;
        if (prefillChargeIds.length === 0) return;
        if (charges.length === 0) return;

        const wanted = new Set(prefillChargeIds);
        const available = totalAvailable;

        // Collect selected charges and accumulate by currency for sum-then-convert
        const selectedCharges: Charge[] = [];
        let eurMinor = 0;
        let usdMinor = 0;
        let vesMinor = 0;

        for (const c of charges) {
            if (!wanted.has(c.charge_id)) continue;
            selectedCharges.push(c);
            const ccy = (c.currency || 'VES').toUpperCase();
            const outCcy = c.outstanding_currency_minor ?? c.outstanding_bs_minor;
            if (ccy === 'EUR') eurMinor += outCcy;
            else if (ccy === 'USD') usdMinor += outCcy;
            else vesMinor += c.outstanding_bs_minor;
        }

        if (selectedCharges.length === 0) {
            prefillAppliedRef.current = true;
            return;
        }

        // Calculate total using sum-then-convert (same as Economic Profile)
        const totalSumThenConvert = fxToBsMinor(eurMinor, fxRates.EUR) + fxToBsMinor(usdMinor, fxRates.USD) + vesMinor;

        // If available is close to or greater than total, distribute available proportionally
        // Tolerance: 1 céntimo per charge to handle batch distribution differences
        const tolerance = selectedCharges.length;
        const diff = available - totalSumThenConvert;
        const exactMatch = diff >= -tolerance && diff <= tolerance;
        const amountToDistribute = exactMatch ? available : Math.min(available, totalSumThenConvert);

        const nextSelected: Record<number, number> = {};
        const localsToExpand = new Set<number>();

        if (exactMatch) {
            const applyCurrencyBatchAdjustment = (currency: 'EUR' | 'USD', rateToVes?: number | null) => {
                const group = selectedCharges
                    .filter((c) => (c.currency || 'VES').toUpperCase() === currency)
                    .map((c) => ({
                        charge_id: c.charge_id,
                        out_minor: c.outstanding_currency_minor ?? 0,
                    }))
                    .filter((x) => x.out_minor > 0);

                if (group.length === 0) return;

                const sumCurrency = group.reduce((sum, x) => sum + x.out_minor, 0);
                const totalBatch = fxToBsMinor(sumCurrency, rateToVes);

                let totalIndividual = 0;
                for (const x of group) {
                    const indiv = fxToBsMinor(x.out_minor, rateToVes);
                    nextSelected[x.charge_id] = (nextSelected[x.charge_id] ?? 0) + indiv;
                    totalIndividual += indiv;
                }

                const d = totalBatch - totalIndividual;
                if (d === 0) return;

                const ids = group.map((x) => x.charge_id).sort((a, b) => a - b);
                const count = ids.length;
                const perCharge = Math.trunc(d / count);
                const remainder = d - perCharge * count;
                for (let i = 0; i < ids.length; i++) {
                    nextSelected[ids[i]] = (nextSelected[ids[i]] ?? 0) + perCharge;
                    if (i < Math.abs(remainder)) {
                        nextSelected[ids[i]] += remainder > 0 ? 1 : -1;
                    }
                }
            };

            applyCurrencyBatchAdjustment('EUR', fxRates.EUR);
            applyCurrencyBatchAdjustment('USD', fxRates.USD);

            for (const c of selectedCharges) {
                const ccy = (c.currency || 'VES').toUpperCase();
                if (ccy === 'VES' || (ccy !== 'EUR' && ccy !== 'USD')) {
                    const amt = Math.max(0, c.outstanding_bs_minor || 0);
                    if (amt > 0) nextSelected[c.charge_id] = (nextSelected[c.charge_id] ?? 0) + amt;
                }
                if (typeof c.local_id === 'number') localsToExpand.add(c.local_id);
            }

            const ids = Object.keys(nextSelected)
                .map((k) => Number(k))
                .filter((k) => Number.isFinite(k))
                .sort((a, b) => a - b);
            const sumNow = ids.reduce((s, id) => s + (nextSelected[id] ?? 0), 0);
            let adjust = amountToDistribute - sumNow;
            if (adjust !== 0 && ids.length > 0) {
                const sign = adjust > 0 ? 1 : -1;
                adjust = Math.abs(adjust);
                const chargeById = new Map<number, Charge>();
                for (const c of selectedCharges) {
                    chargeById.set(c.charge_id, c);
                }

                let applied = 0;
                let cursor = 0;
                while (applied < adjust && cursor < ids.length * adjust) {
                    const id = ids[cursor % ids.length];
                    const current = nextSelected[id] ?? 0;
                    const charge = chargeById.get(id);
                    const maxAllowed = charge ? maxAllowedForCharge(charge) : undefined;

                    if (sign > 0) {
                        if (maxAllowed !== undefined && current >= maxAllowed) {
                            cursor++;
                            continue;
                        }
                        nextSelected[id] = current + 1;
                        applied++;
                    } else {
                        if (current <= 0) {
                            cursor++;
                            continue;
                        }
                        nextSelected[id] = current - 1;
                        applied++;
                    }

                    cursor++;
                }
            }

            setSelectedItems(nextSelected);
            if (localsToExpand.size > 0) setExpandedLocals(localsToExpand);
            prefillAppliedRef.current = true;

            const count = Object.keys(nextSelected).length;
            if (count > 0) {
                toast.message('Selección cargada desde Perfil Económico', { description: `${count} cargo(s) preseleccionados.` });
            }

            return;
        }

        // Distribute proportionally among selected charges
        let distributed = 0;
        for (let i = 0; i < selectedCharges.length; i++) {
            const c = selectedCharges[i];
            const ccy = (c.currency || 'VES').toUpperCase();
            const outCcy = c.outstanding_currency_minor ?? c.outstanding_bs_minor;

            const chargeBs =
                ccy === 'EUR' ? fxToBsMinor(outCcy, fxRates.EUR) : ccy === 'USD' ? fxToBsMinor(outCcy, fxRates.USD) : c.outstanding_bs_minor;

            let share: number;
            if (i === selectedCharges.length - 1) {
                share = amountToDistribute - distributed;
            } else {
                share = totalSumThenConvert > 0 ? Math.round((chargeBs / totalSumThenConvert) * amountToDistribute) : 0;
            }

            const max = Math.max(0, c.outstanding_bs_minor || 0);
            const amt = Math.min(max, Math.max(0, share));
            if (amt > 0) {
                nextSelected[c.charge_id] = amt;
                distributed += amt;
            }
            if (typeof c.local_id === 'number') localsToExpand.add(c.local_id);
        }

        setSelectedItems(nextSelected);
        if (localsToExpand.size > 0) setExpandedLocals(localsToExpand);
        prefillAppliedRef.current = true;

        const count = Object.keys(nextSelected).length;
        if (count > 0) {
            toast.message('Selección cargada desde Perfil Económico', { description: `${count} cargo(s) preseleccionados.` });
        }
    }, [charges, prefillChargeIds, totalAvailable, fxRates]);

    // Group charges by local and accumulate by currency for sum-then-convert
    const localGroups = React.useMemo((): LocalGroup[] => {
        const groups = new Map<number, LocalGroup>();

        for (const charge of charges) {
            const lid = charge.local_id ?? 0;
            const existing = groups.get(lid);
            const chargeIsOverdue = isOverdue(charge.due_on, payment.paid_on);
            const currency = (charge.currency || 'VES').toUpperCase();
            const outstandingCcy = charge.outstanding_currency_minor ?? charge.outstanding_bs_minor;

            if (existing) {
                existing.charges.push(charge);
                // Accumulate by currency for sum-then-convert
                if (currency === 'EUR') existing._eurMinor += outstandingCcy;
                else if (currency === 'USD') existing._usdMinor += outstandingCcy;
                else existing._vesMinor += charge.outstanding_bs_minor;
                if (chargeIsOverdue) {
                    existing.overdueCount++;
                }
            } else {
                groups.set(lid, {
                    id: lid,
                    label: cleanLocalLabel(charge.local_label) || (lid ? `Local ${lid}` : 'Cargos del cesionario'),
                    charges: [charge],
                    totalDebt: 0, // Will be recalculated below
                    overdueDebt: 0, // Will be recalculated below
                    overdueCount: chargeIsOverdue ? 1 : 0,
                    _eurMinor: currency === 'EUR' ? outstandingCcy : 0,
                    _usdMinor: currency === 'USD' ? outstandingCcy : 0,
                    _vesMinor: currency === 'VES' || !currency ? charge.outstanding_bs_minor : 0,
                });
            }
        }

        // Recalculate totalDebt using sum-then-convert for consistency
        for (const group of groups.values()) {
            const eurBs = fxToBsMinor(group._eurMinor, fxRates.EUR);
            const usdBs = fxToBsMinor(group._usdMinor, fxRates.USD);
            group.totalDebt = eurBs + usdBs + group._vesMinor;
            // For overdue, use same logic (simplified - total is close enough for display)
            group.overdueDebt = group.overdueCount > 0 ? group.totalDebt : 0;
        }

        // Sort charges within each group by due_on (FIFO)
        for (const group of groups.values()) {
            group.charges.sort((a, b) => {
                const da = (a.due_on || a.period || '').toString();
                const db = (b.due_on || b.period || '').toString();
                return da.localeCompare(db);
            });
        }

        // Sort groups: those with overdue first, then by label
        return Array.from(groups.values()).sort((a, b) => {
            if (a.overdueCount > 0 && b.overdueCount === 0) return -1;
            if (a.overdueCount === 0 && b.overdueCount > 0) return 1;
            return a.label.localeCompare(b.label);
        });
    }, [charges, payment.paid_on, fxRates]);

    // Calculate totals - use the actual selected amounts from backend
    // The backend already distributed rounding differences in outstanding_bs_minor,
    // so we should use those values directly instead of recalculating
    const { sumRequested, selectedCount } = React.useMemo(() => {
        const total = Object.values(selectedItems).reduce((sum, v) => sum + (v || 0), 0);
        const count = Object.keys(selectedItems).filter((k) => (selectedItems[Number(k)] || 0) > 0).length;
        return { sumRequested: total, selectedCount: count };
    }, [selectedItems]);

    const remainingAfter = totalAvailable - sumRequested;
    const isOverBudget = sumRequested > totalAvailable;

    const adjustFxRemainder = React.useCallback(
        (items: Record<number, number>): Record<number, number> => {
            const ids = Object.keys(items)
                .map((k) => Number(k))
                .filter((k) => Number.isFinite(k) && (items[k] ?? 0) > 0);
            if (ids.length === 0) return items;

            for (const cid of ids) {
                const charge = charges.find((c) => c.charge_id === cid);
                if (!charge) return items;
                const maxAllowed = maxAllowedForCharge(charge);
                if ((items[cid] ?? 0) !== maxAllowed && (items[cid] ?? 0) !== (charge.outstanding_bs_minor || 0)) {
                    return items;
                }
            }

            const sum = ids.reduce((s, id) => s + (items[id] ?? 0), 0);
            const rem = totalAvailable - sum;
            if (rem <= 0) return items;

            const fxIds = charges
                .filter((c) => (items[c.charge_id] ?? 0) > 0)
                .filter((c) => {
                    const ccy = (c.currency || 'VES').toUpperCase();
                    return ccy === 'EUR' || ccy === 'USD';
                })
                .map((c) => c.charge_id)
                .sort((a, b) => a - b);

            if (fxIds.length === 0) return items;
            if (rem > fxIds.length) return items;

            const candidateIds = fxIds.filter((cid) => {
                const charge = charges.find((c) => c.charge_id === cid);
                if (!charge) return false;
                return (items[cid] ?? 0) < maxAllowedForCharge(charge);
            });
            if (rem > candidateIds.length) return items;

            const next = { ...items };
            for (let i = 0; i < rem; i++) {
                const cid = candidateIds[i % candidateIds.length];
                next[cid] = (next[cid] ?? 0) + 1;
            }

            return next;
        },
        [charges, totalAvailable],
    );

    // Get selected amount for a local
    const getLocalSelectedAmount = (group: LocalGroup): number => {
        return group.charges.reduce((sum, c) => sum + (selectedItems[c.charge_id] || 0), 0);
    };

    // Check if all charges of a local are fully selected
    const isLocalFullySelected = (group: LocalGroup): boolean => {
        return group.charges.every((c) => selectedItems[c.charge_id] === c.outstanding_bs_minor);
    };

    // Check if some charges of a local are selected
    const isLocalPartiallySelected = (group: LocalGroup): boolean => {
        const selected = group.charges.some((c) => (selectedItems[c.charge_id] || 0) > 0);
        return selected && !isLocalFullySelected(group);
    };

    // Toggle local expansion
    const toggleExpand = (localId: number) => {
        setExpandedLocals((prev) => {
            const next = new Set(prev);
            if (next.has(localId)) {
                next.delete(localId);
            } else {
                next.add(localId);
            }
            return next;
        });
    };

    // Quick action: Select all charges of a local (FIFO up to available)
    const selectLocalComplete = (group: LocalGroup) => {
        let remaining = totalAvailable - sumRequested + getLocalSelectedAmount(group);
        const newItems = { ...selectedItems };

        for (const charge of group.charges) {
            if (remaining <= 0) {
                newItems[charge.charge_id] = 0;
            } else {
                const take = Math.min(remaining, charge.outstanding_bs_minor);
                newItems[charge.charge_id] = take;
                remaining -= take;
            }
        }

        setSelectedItems(adjustFxRemainder(newItems));
        toast.success(`${group.label}: todos los cargos seleccionados`);
    };

    // Quick action: Select only overdue charges of a local
    const selectLocalOverdue = (group: LocalGroup) => {
        let remaining = totalAvailable - sumRequested + getLocalSelectedAmount(group);
        const newItems = { ...selectedItems };

        for (const charge of group.charges) {
            const chargeIsOverdue = isOverdue(charge.due_on, payment.paid_on);
            if (!chargeIsOverdue) {
                newItems[charge.charge_id] = 0;
            } else if (remaining <= 0) {
                newItems[charge.charge_id] = 0;
            } else {
                const take = Math.min(remaining, charge.outstanding_bs_minor);
                newItems[charge.charge_id] = take;
                remaining -= take;
            }
        }

        setSelectedItems(adjustFxRemainder(newItems));
        toast.success(`${group.label}: cargos vencidos seleccionados`);
    };

    // Clear local selection
    const clearLocalSelection = (group: LocalGroup) => {
        const newItems = { ...selectedItems };
        for (const charge of group.charges) {
            delete newItems[charge.charge_id];
        }
        setSelectedItems(newItems);
    };

    // Toggle individual charge
    const toggleCharge = (charge: Charge, selected: boolean) => {
        const newItems = { ...selectedItems };
        if (selected) {
            const currentSum = sumRequested - (selectedItems[charge.charge_id] || 0);
            const maxTake = Math.min(maxAllowedForCharge(charge), totalAvailable - currentSum);
            newItems[charge.charge_id] = Math.max(0, maxTake);
        } else {
            delete newItems[charge.charge_id];
        }
        setSelectedItems(adjustFxRemainder(newItems));
    };

    // Update charge amount
    const updateChargeAmount = (chargeId: number, amount: number, maxAmount: number) => {
        const capped = Math.max(0, Math.min(amount, maxAmount));
        setSelectedItems((prev) => {
            if (capped <= 0) {
                const { [chargeId]: _, ...rest } = prev;
                return rest;
            }
            return { ...prev, [chargeId]: capped };
        });
    };

    // Quick action: FIFO global (fill all charges oldest first)
    const applyFifoGlobal = () => {
        let remaining = totalAvailable;
        const newItems: Record<number, number> = {};

        // Flatten all charges sorted by due_on
        const allCharges = [...charges].sort((a, b) => {
            const da = (a.due_on || a.period || '').toString();
            const db = (b.due_on || b.period || '').toString();
            return da.localeCompare(db);
        });

        for (const charge of allCharges) {
            if (remaining <= 0) break;
            const take = Math.min(remaining, charge.outstanding_bs_minor);
            if (take > 0) {
                newItems[charge.charge_id] = take;
                remaining -= take;
            }
        }

        setSelectedItems(adjustFxRemainder(newItems));
        toast.success('Distribución automática aplicada (más antiguos primero)');
    };

    // Clear all selections
    const clearAll = () => {
        setSelectedItems({});
        setRowIssues({});
    };

    const postAllocations = async (items: SelectedItem[], useCreditFlag: boolean) => {
        const key = `pay-${payment.id}-${Date.now()}`;
        await new Promise<void>((resolve, reject) => {
            router.post(
                `/payments/${payment.id}/allocations`,
                { items, idempotency_key: key, use_credit: useCreditFlag ? 1 : 0 },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('¡Pago aplicado correctamente!');
                        onApplied?.();
                        resolve();
                        router.visit(`/payments/${payment.id}?tab=allocations`, { preserveScroll: true, replace: true });
                    },
                    onError: (errs) => {
                        setErrors(Object.values(errs).flat() as string[]);
                        reject(new Error('apply_failed'));
                    },
                },
            );
        });
    };

    // Submit allocations
    const handleSubmit = async () => {
        const items: SelectedItem[] = Object.entries(selectedItems)
            .map(([cid, amt]) => ({ charge_id: Number(cid), amount_bs_minor: amt }))
            .filter((x) => x.amount_bs_minor > 0);

        if (items.length === 0) {
            toast.error('Selecciona al menos un cargo');
            return;
        }

        setSubmitting(true);
        setErrors([]);
        setRowIssues({});

        try {
            const xsrf = getCookie('XSRF-TOKEN') || '';

            // Preview first
            const previewRes = await fetch(`/payments/${payment.id}/allocations/preview`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ items, use_credit: useCredit ? 1 : 0 }),
            });

            if (previewRes.status === 419) {
                setErrors(['Tu sesión expiró o el token de seguridad cambió. Recarga la página e intenta de nuevo.']);
                setSubmitting(false);
                return;
            }

            const previewJson = await previewRes.json();

            // Check for row issues
            const rowMap: Record<number, string | null> = {};
            if (previewJson.items && Array.isArray(previewJson.items)) {
                for (const it of previewJson.items) {
                    if (it && typeof it.charge_id === 'number') {
                        rowMap[it.charge_id] = it.valid ? null : it.message || 'Inválido';
                    }
                }
            }
            setRowIssues(rowMap);

            if (!previewRes.ok || previewJson.ok === false) {
                setErrors(previewJson.errors ?? ['Error en la validación.']);
                setSubmitting(false);
                return;
            }

            setSubmitting(false);
            setConfirmPayload({
                items,
                useCredit: useCredit,
                summary: previewJson.summary ?? {},
            });
            setConfirmOpen(true);
        } catch {
            setErrors(['Error al aplicar el pago.']);
            setSubmitting(false);
        }
    };

    // Not confirmed state
    if (!isConfirmed) {
        return (
            <Card className="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-900/20">
                <CardContent className="py-8 text-center">
                    <AlertTriangle className="mx-auto mb-4 h-12 w-12 text-amber-500" />
                    <p className="text-lg font-medium text-amber-900 dark:text-amber-100">Este pago aún no está confirmado</p>
                    <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">
                        Solo los pagos en estado <strong>CONFIRMED</strong> pueden aplicarse a cargos.
                    </p>
                </CardContent>
            </Card>
        );
    }

    // Loading state
    if (loading) {
        return (
            <div className="flex min-h-[300px] items-center justify-center">
                <div className="text-center">
                    <Loader2 className="mx-auto mb-4 h-8 w-8 animate-spin text-blue-600" />
                    <p className="text-muted-foreground">Cargando deudas pendientes...</p>
                </div>
            </div>
        );
    }

    // No charges state
    if (charges.length === 0) {
        return (
            <Card className="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-900/20">
                <CardContent className="py-8 text-center">
                    <CheckCircle2 className="mx-auto mb-4 h-12 w-12 text-green-600" />
                    <p className="text-lg font-medium text-green-900 dark:text-green-100">¡No hay deudas pendientes!</p>
                    <p className="mt-2 text-sm text-green-700 dark:text-green-300">El deudor no tiene cargos pendientes para esta fecha de pago.</p>
                    <Button variant="outline" className="mt-4" onClick={fetchCharges}>
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Recargar
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <ConfirmAlert
                open={confirmOpen}
                onOpenChange={(open) => {
                    setConfirmOpen(open);
                    if (!open) {
                        setConfirmPayload(null);
                    }
                }}
                title="Confirmar aplicación del pago"
                description={
                    <div className="space-y-2">
                        <div className="text-sm text-slate-600 dark:text-slate-300">
                            Estás a punto de aplicar este pago a <strong>{confirmPayload?.items.length ?? 0}</strong> cargo(s).
                        </div>
                        <div className="rounded-md border bg-white/50 p-3 text-sm dark:bg-slate-900/20">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Total a aplicar</span>
                                <span className="font-mono font-semibold">{fmtMinor(sumRequested)}</span>
                            </div>
                            <div className="mt-1 flex items-center justify-between">
                                <span className="text-slate-500">Usar crédito a favor</span>
                                <span className="font-medium">{confirmPayload?.useCredit ? 'Sí' : 'No'}</span>
                            </div>
                            <div className="mt-1 flex items-center justify-between">
                                <span className="text-slate-500">Disponible total</span>
                                <span className="font-mono">{fmtMinor(totalAvailable)}</span>
                            </div>
                            <div className="mt-1 flex items-center justify-between">
                                <span className="text-slate-500">Saldo luego</span>
                                <span className="font-mono">{fmtMinor(remainingAfter)}</span>
                            </div>
                        </div>
                    </div>
                }
                confirmLabel="Aplicar ahora"
                confirmDestructive={false}
                onConfirm={async () => {
                    if (!confirmPayload) return;
                    await postAllocations(confirmPayload.items, confirmPayload.useCredit);
                }}
            />
            {/* Payment Summary Card */}
            <Card className="border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 dark:border-blue-900 dark:from-blue-900/30 dark:to-indigo-900/20">
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-6">
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-600">
                                <Wallet className="h-6 w-6 text-white" />
                            </div>
                            <div>
                                <p className="text-sm text-blue-700 dark:text-blue-300">Disponible</p>
                                <p className="text-2xl font-bold text-blue-900 dark:text-blue-100">{fmtMinor(totalAvailable)}</p>
                            </div>
                        </div>

                        {customerCreditBsMinor > 0 && (
                            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-blue-200 bg-white/50 px-3 py-2 dark:border-blue-800 dark:bg-blue-900/30">
                                <Checkbox checked={useCredit} onCheckedChange={(checked) => setUseCredit(checked === true)} />
                                <span className="text-sm">
                                    Usar crédito: <strong>{fmtMinor(customerCreditBsMinor)}</strong>
                                </span>
                            </label>
                        )}

                        {payment.paid_on && (
                            <div className="ml-auto flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                                <Calendar className="h-4 w-4" />
                                <span>Fecha: {fmtDate(payment.paid_on)}</span>
                            </div>
                        )}
                    </div>
                    {prefillChargeIds.length > 0 && (
                        <div className="mt-3 rounded-md border border-blue-200 bg-white/60 px-3 py-2 text-xs text-blue-800 dark:border-blue-900/60 dark:bg-blue-950/20 dark:text-blue-200">
                            Se cargó una <strong>selección estimada</strong> desde Perfil Económico ({prefillChargeIds.length} cargo(s)). Revisa y
                            ajusta si es necesario.
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Quick Actions Bar */}
            <div className="flex flex-wrap items-center gap-3">
                <Button variant="default" size="sm" onClick={applyFifoGlobal} className="gap-2">
                    <Zap className="h-4 w-4" />
                    Aplicar automático
                </Button>

                <Button variant="outline" size="sm" onClick={clearAll} disabled={selectedCount === 0}>
                    Limpiar selección
                </Button>

                <Button variant="ghost" size="sm" onClick={() => setShowFilters(!showFilters)} className="gap-2">
                    <Filter className="h-4 w-4" />
                    Filtros
                    {showFilters ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                </Button>

                <Button variant="ghost" size="sm" onClick={fetchCharges} className="gap-2">
                    <RefreshCw className="h-4 w-4" />
                    Recargar
                </Button>
            </div>

            {/* Filters (collapsible) */}
            {showFilters && (
                <Card>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label className="mb-2 block text-sm font-medium">Moneda</label>
                                <Select
                                    value={filters.currency ?? 'ALL'}
                                    onValueChange={(v) => setFilters((f) => ({ ...f, currency: v === 'ALL' ? undefined : v }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Todas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ALL">Todas</SelectItem>
                                        <SelectItem value="USD">USD</SelectItem>
                                        <SelectItem value="EUR">EUR</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium">Tipo de cargo</label>
                                <Select
                                    value={filters.kind ?? 'ALL'}
                                    onValueChange={(v) => setFilters((f) => ({ ...f, kind: v === 'ALL' ? undefined : v }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Todos" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ALL">Todos</SelectItem>
                                        <SelectItem value="RENT_EUR_M2">Alquiler m²</SelectItem>
                                        <SelectItem value="RENT_EUR_FIXED">Alquiler fijo</SelectItem>
                                        <SelectItem value="CONDO_USD">Condominio</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end">
                                <label className="flex cursor-pointer items-center gap-2">
                                    <Checkbox
                                        checked={filters.overdue_only ?? false}
                                        onCheckedChange={(checked) => setFilters((f) => ({ ...f, overdue_only: checked === true || undefined }))}
                                    />
                                    <span className="text-sm">Solo vencidos</span>
                                </label>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Errors */}
            {errors.length > 0 && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        {errors.map((e, i) => (
                            <div key={i}>{e}</div>
                        ))}
                    </AlertDescription>
                </Alert>
            )}

            {/* Locals List */}
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <h3 className="flex items-center gap-2 font-medium">
                        <Building2 className="h-5 w-5 text-slate-500" />
                        Locales con deuda ({localGroups.length})
                    </h3>
                    <span className="text-sm text-slate-500">{charges.length} cargos en total</span>
                </div>

                {localGroups.map((group) => {
                    const isExpanded = expandedLocals.has(group.id);
                    const localSelected = getLocalSelectedAmount(group);
                    const isFullySelected = isLocalFullySelected(group);
                    const isPartial = isLocalPartiallySelected(group);
                    const coveragePercent = group.totalDebt > 0 ? Math.round((localSelected / group.totalDebt) * 100) : 0;

                    return (
                        <Collapsible key={group.id} open={isExpanded} onOpenChange={() => toggleExpand(group.id)}>
                            <Card
                                className={cn(
                                    'overflow-hidden transition-all',
                                    isFullySelected && 'border-green-400 bg-green-50/50 dark:border-green-700 dark:bg-green-900/20',
                                    isPartial && 'border-amber-400 bg-amber-50/50 dark:border-amber-700 dark:bg-amber-900/20',
                                )}
                            >
                                {/* Local Header */}
                                <CollapsibleTrigger asChild>
                                    <div className="flex cursor-pointer items-center gap-4 p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        {/* Expand icon */}
                                        <div className="text-slate-400">
                                            {isExpanded ? <ChevronDown className="h-5 w-5" /> : <ChevronRight className="h-5 w-5" />}
                                        </div>

                                        {/* Selection indicator */}
                                        <div
                                            className={cn(
                                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border-2 transition-colors',
                                                isFullySelected
                                                    ? 'border-green-500 bg-green-500 text-white'
                                                    : isPartial
                                                      ? 'border-amber-500 bg-amber-500 text-white'
                                                      : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-800',
                                            )}
                                        >
                                            {(isFullySelected || isPartial) && <Check className="h-4 w-4" />}
                                        </div>

                                        {/* Local info */}
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <MapPin className="h-4 w-4 text-slate-400" />
                                                <span className="font-semibold">{group.label}</span>
                                                {group.overdueCount > 0 && (
                                                    <Badge variant="destructive" className="text-xs">
                                                        {group.overdueCount} vencido{group.overdueCount > 1 ? 's' : ''}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                                {group.charges.length} cargo{group.charges.length > 1 ? 's' : ''} • Deuda total:{' '}
                                                <strong>{fmtMinor(group.totalDebt)}</strong>
                                            </div>
                                        </div>

                                        {/* Coverage / selected */}
                                        {localSelected > 0 && (
                                            <div className="text-right">
                                                <div className="text-lg font-bold text-green-600">{fmtMinor(localSelected)}</div>
                                                <div className="flex items-center gap-2">
                                                    <Progress value={coveragePercent} className="h-1.5 w-16" />
                                                    <span className="text-xs text-slate-500">{coveragePercent}%</span>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CollapsibleTrigger>

                                {/* Expanded content: Quick actions + charges */}
                                <CollapsibleContent>
                                    <div className="border-t bg-slate-50/50 dark:bg-slate-800/30">
                                        {/* Quick actions for this local */}
                                        <div className="flex flex-wrap gap-2 border-b p-3">
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    selectLocalComplete(group);
                                                }}
                                            >
                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                                Pagar todo
                                            </Button>
                                            {group.overdueCount > 0 && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        selectLocalOverdue(group);
                                                    }}
                                                >
                                                    <AlertTriangle className="mr-1 h-3 w-3" />
                                                    Solo vencidos
                                                </Button>
                                            )}
                                            {localSelected > 0 && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        clearLocalSelection(group);
                                                    }}
                                                >
                                                    Limpiar
                                                </Button>
                                            )}
                                        </div>

                                        {/* Charges table */}
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b bg-slate-100/50 dark:bg-slate-800/50">
                                                        <th className="w-10 p-2"></th>
                                                        <th className="p-2 text-left font-medium text-slate-600">Cargo</th>
                                                        <th className="p-2 text-left font-medium text-slate-600">Vence</th>
                                                        <th className="p-2 text-right font-medium text-slate-600">Pendiente</th>
                                                        <th className="p-2 text-right font-medium text-slate-600">A aplicar</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y">
                                                    {group.charges.map((charge) => {
                                                        const chargeIsOverdue = isOverdue(charge.due_on, payment.paid_on);
                                                        const currentAmount = selectedItems[charge.charge_id] || 0;
                                                        const isChargeSelected = currentAmount > 0;
                                                        const issue = rowIssues[charge.charge_id];

                                                        return (
                                                            <tr
                                                                key={charge.charge_id}
                                                                className={cn(
                                                                    'transition-colors',
                                                                    isChargeSelected && 'bg-green-50/50 dark:bg-green-900/10',
                                                                    issue && 'bg-red-50 dark:bg-red-900/20',
                                                                )}
                                                            >
                                                                {/* Checkbox */}
                                                                <td className="p-2 text-center">
                                                                    <Checkbox
                                                                        checked={isChargeSelected}
                                                                        onCheckedChange={(checked) => toggleCharge(charge, checked === true)}
                                                                    />
                                                                </td>

                                                                {/* Cargo info */}
                                                                <td className="p-2">
                                                                    <div className="font-medium">{formatChargeKind(charge.kind)}</div>
                                                                    <div className="text-xs text-slate-500">{formatPeriod(charge.period)}</div>
                                                                </td>

                                                                {/* Due date */}
                                                                <td className="p-2">
                                                                    <div className={cn(chargeIsOverdue && 'font-medium text-red-600')}>
                                                                        {fmtDate(charge.due_on)}
                                                                    </div>
                                                                    {chargeIsOverdue && (
                                                                        <Badge variant="destructive" className="mt-0.5 text-[10px]">
                                                                            VENCIDO
                                                                        </Badge>
                                                                    )}
                                                                </td>

                                                                {/* Outstanding */}
                                                                <td className="p-2 text-right">
                                                                    <div className="font-mono font-medium">
                                                                        {fmtMinor(charge.outstanding_bs_minor)}
                                                                    </div>
                                                                    {charge.currency && (
                                                                        <div className="text-xs text-slate-400">
                                                                            {charge.currency}{' '}
                                                                            {(
                                                                                (charge.outstanding_currency_minor ?? charge.amount_minor ?? 0) / 100
                                                                            ).toFixed(2)}
                                                                        </div>
                                                                    )}
                                                                </td>

                                                                {/* Amount input */}
                                                                <td className="p-2">
                                                                    <div className="flex justify-end">
                                                                        <AmountInput
                                                                            value={currentAmount}
                                                                            maxValue={maxAllowedForCharge(charge)}
                                                                            onChange={(minor) =>
                                                                                updateChargeAmount(
                                                                                    charge.charge_id,
                                                                                    minor,
                                                                                    maxAllowedForCharge(charge),
                                                                                )
                                                                            }
                                                                            isSelected={isChargeSelected}
                                                                            hasError={!!issue}
                                                                        />
                                                                    </div>
                                                                    {issue && <div className="mt-1 text-right text-xs text-red-600">{issue}</div>}
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>
                    );
                })}
            </div>

            {/* Summary and Submit */}
            <Card className={cn('sticky bottom-4', isOverBudget && 'border-red-400 bg-red-50 dark:border-red-700 dark:bg-red-900/20')}>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        {/* Summary stats */}
                        <div className="flex flex-wrap items-center gap-6">
                            <div>
                                <div className="text-xs text-slate-500">Disponible</div>
                                <div className="font-medium">{fmtMinor(totalAvailable)}</div>
                            </div>
                            <div>
                                <div className="text-xs text-slate-500">A aplicar</div>
                                <div className={cn('text-lg font-bold', isOverBudget ? 'text-red-600' : 'text-green-600')}>
                                    {fmtMinor(sumRequested)}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-slate-500">Cargos</div>
                                <div className="font-medium">
                                    {selectedCount} / {charges.length}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-slate-500">Restante</div>
                                <div className={cn('font-medium', remainingAfter < 0 ? 'text-red-600' : 'text-slate-600')}>
                                    {fmtMinor(remainingAfter)}
                                </div>
                            </div>
                            {/* Progress bar */}
                            <div className="hidden w-32 sm:block">
                                <Progress
                                    value={Math.min(100, totalAvailable > 0 ? (sumRequested / totalAvailable) * 100 : 0)}
                                    className={cn('h-2', isOverBudget && '[&>div]:bg-red-500')}
                                />
                            </div>
                        </div>

                        {/* Submit button */}
                        <Button
                            size="lg"
                            onClick={handleSubmit}
                            disabled={submitting || selectedCount === 0 || isOverBudget}
                            className="gap-2 bg-green-600 hover:bg-green-700"
                        >
                            {submitting ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Aplicando...
                                </>
                            ) : (
                                <>
                                    <CreditCard className="h-4 w-4" />
                                    Aplicar pago
                                </>
                            )}
                        </Button>
                    </div>

                    {/* Warnings */}
                    {isOverBudget && (
                        <div className="mt-3 flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                            <AlertCircle className="h-4 w-4" />
                            <span>
                                El monto a aplicar ({fmtMinor(sumRequested)}) excede el disponible ({fmtMinor(totalAvailable)}). Reduce algunas
                                asignaciones.
                            </span>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default PaymentApplyAdmin;
