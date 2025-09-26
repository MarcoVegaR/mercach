import { Field } from '@/components/form/Field';
import { ActiveField } from '@/components/forms/active-field';
import { Input } from '@/components/ui/input';
import { Hash, Tag } from 'lucide-react';
import React from 'react';

export function CatalogCodeField({
    value,
    onChange,
    error,
    inputRef,
    autoFocus,
    maxLength = 20,
}: {
    value: string;
    onChange: (v: string) => void;
    error?: string;
    inputRef?: React.RefObject<HTMLInputElement> | React.MutableRefObject<HTMLInputElement | null>;
    autoFocus?: boolean;
    maxLength?: number;
}) {
    return (
        <Field id="code" label="Código" error={error} tooltip="Código único alfanumérico sin espacios" required>
            <Input
                name="code"
                ref={inputRef as React.Ref<HTMLInputElement>}
                autoFocus={autoFocus}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                maxLength={maxLength}
                className="font-mono"
                leadingIcon={Hash}
                leadingIconClassName="text-amber-600"
                placeholder="Ej: COD123"
            />
        </Field>
    );
}

export function CatalogNameField({
    value,
    onChange,
    error,
    label = 'Nombre',
    maxLength = 160,
    tooltip,
}: {
    value: string;
    onChange: (v: string) => void;
    error?: string;
    label?: string;
    maxLength?: number;
    tooltip?: string;
}) {
    return (
        <Field id="name" label={label} error={error} tooltip={tooltip ?? 'Nombre oficial o descriptivo visible para los usuarios'} required>
            <Input
                name="name"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                maxLength={maxLength}
                leadingIcon={Tag}
                leadingIconClassName="text-teal-600"
                placeholder="Ej: Nombre descriptivo"
            />
        </Field>
    );
}

export function CatalogIsActiveField({
    checked,
    onChange,
    error,
    activeLabel = 'Registro activo',
    inactiveLabel = 'Registro inactivo',
}: {
    checked: boolean;
    onChange: (v: boolean) => void;
    error?: string;
    activeLabel?: string;
    inactiveLabel?: string;
}) {
    return (
        <Field id="is_active" label="Estado activo" error={error} tooltip="Controla si el registro aparece en listados y búsquedas">
            <ActiveField checked={checked} onChange={onChange} canToggle={true} activeLabel={activeLabel} inactiveLabel={inactiveLabel} />
        </Field>
    );
}
