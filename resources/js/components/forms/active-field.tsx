import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export type ActiveFieldProps = {
    checked: boolean;
    onChange: (v: boolean) => void;
    canToggle?: boolean;
    activeLabel?: string;
    inactiveLabel?: string;
    permissionHint?: string;
    name?: string;
};

export function ActiveField({
    checked,
    onChange,
    canToggle = true,
    activeLabel = 'Activo',
    inactiveLabel = 'Inactivo',
    permissionHint,
    name = 'is_active',
}: ActiveFieldProps) {
    const ariaDisabled = !canToggle;
    return (
        <div>
            <div className="flex items-center space-x-2">
                <Switch
                    name={name}
                    checked={checked}
                    onCheckedChange={(v) => onChange(!!v)}
                    disabled={ariaDisabled}
                    aria-disabled={ariaDisabled}
                    aria-label="Estado activo"
                />
                <Label htmlFor={name} className="cursor-pointer">
                    {checked ? activeLabel : inactiveLabel}
                </Label>
            </div>
            {ariaDisabled && permissionHint && <p className="text-muted-foreground mt-2 text-sm">{permissionHint}</p>}
        </div>
    );
}
