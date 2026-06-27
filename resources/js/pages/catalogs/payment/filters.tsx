import { FilterBadges } from '@/components/filters/FilterBadges';
import { FilterSheet } from '@/components/filters/FilterSheet';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { BadgeCheck, CreditCard, Wallet } from 'lucide-react';
import React from 'react';

export type Filters = {
    status?: 'REGISTERED' | 'CONFIRMED' | 'APPLIED' | null;
    method?: string | null;
    has_available?: boolean | null;
};

export const defaultFilters: Filters = {
    status: null,
    method: null,
    has_available: null,
};

export type PaymentTypeOption = {
    code: string;
    name: string;
};

interface PaymentFiltersProps {
    value: Filters;
    onChange: (filters: Filters) => void;
    paymentTypes?: PaymentTypeOption[];
}

export function PaymentFilters({ value, onChange, paymentTypes = [] }: PaymentFiltersProps) {
    const [local, setLocal] = React.useState<Filters>(value);

    React.useEffect(() => {
        setLocal(value);
    }, [value]);

    const activeFiltersCount = React.useMemo(() => {
        let c = 0;
        if (local.status) c++;
        if (local.method) c++;
        if (local.has_available) c++;
        return c;
    }, [local]);

    const applyFilters = () => {
        onChange(local);
    };

    const clearFilters = () => {
        const cleared: Filters = {
            status: null,
            method: null,
            has_available: null,
        };
        setLocal(cleared);
        onChange(cleared);
    };

    const badges: Array<{
        key: string;
        label: string;
        onRemove: () => void;
        icon?: React.ReactNode;
    }> = [];

    if (value.status) {
        const map: Record<string, string> = {
            REGISTERED: 'Registrado',
            CONFIRMED: 'Confirmado',
            APPLIED: 'Aplicado',
        };
        badges.push({
            key: 'status',
            label: `Estado: ${map[String(value.status)] ?? value.status}`,
            onRemove: () => onChange({ ...value, status: null }),
            icon: <BadgeCheck className="h-3 w-3 text-indigo-600 dark:text-indigo-400" />,
        });
    }

    if (value.method) {
        const label = paymentTypes.find((paymentType) => paymentType.code === value.method)?.name ?? value.method;
        badges.push({
            key: 'method',
            label: `Tipo: ${label}`,
            onRemove: () => onChange({ ...value, method: null }),
            icon: <CreditCard className="h-3 w-3 text-sky-600 dark:text-sky-400" />,
        });
    }

    if (value.has_available) {
        badges.push({
            key: 'has_available',
            label: 'Con saldo disponible',
            onRemove: () => onChange({ ...value, has_available: null }),
            icon: <Wallet className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />,
        });
    }

    return (
        <div className="flex items-center gap-2">
            <FilterSheet
                activeFiltersCount={activeFiltersCount}
                onApplyFilters={applyFilters}
                onClearFilters={clearFilters}
                title="Filtros de Pagos"
                description="Aplica filtros específicos para el listado de pagos"
            >
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {/* Estado */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <BadgeCheck className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                            <Label htmlFor="status">Estado</Label>
                        </div>
                        <Select
                            value={local.status ?? 'all'}
                            onValueChange={(val) =>
                                setLocal({
                                    ...local,
                                    status: val === 'all' ? null : (val as Filters['status']),
                                })
                            }
                        >
                            <SelectTrigger id="status" className="w-full">
                                <SelectValue placeholder="Seleccionar estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="REGISTERED">Registrado</SelectItem>
                                <SelectItem value="CONFIRMED">Confirmado</SelectItem>
                                <SelectItem value="APPLIED">Aplicado</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Tipo de pago */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <CreditCard className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                            <Label htmlFor="method">Tipo de pago</Label>
                        </div>
                        <Select
                            value={local.method ?? 'all'}
                            onValueChange={(val) =>
                                setLocal({
                                    ...local,
                                    method: val === 'all' ? null : val,
                                })
                            }
                        >
                            <SelectTrigger id="method" className="w-full">
                                <SelectValue placeholder="Seleccionar tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {paymentTypes.map((paymentType) => (
                                    <SelectItem key={paymentType.code} value={paymentType.code}>
                                        {paymentType.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Saldo disponible */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Wallet className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            <Label htmlFor="has_available">Saldo disponible</Label>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <Checkbox
                                id="has_available"
                                checked={!!local.has_available}
                                onCheckedChange={(val) =>
                                    setLocal({
                                        ...local,
                                        has_available: val === true ? true : null,
                                    })
                                }
                            />
                            <span>Solo pagos con saldo pendiente</span>
                        </label>
                    </div>
                </div>
            </FilterSheet>

            <FilterBadges badges={badges} />
        </div>
    );
}
