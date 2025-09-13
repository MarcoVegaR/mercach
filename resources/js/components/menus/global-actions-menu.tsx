import { ChevronDown, Download, Trash2 } from 'lucide-react';
import * as React from 'react';

import type { ActionItem } from '@/components/menus/row-actions-menu';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export interface GlobalActionsMenuProps {
    items: ActionItem[];
    className?: string;
    triggerAriaLabel?: string;
    triggerLabel?: React.ReactNode;
    disabled?: boolean;
    triggerRef?: React.RefObject<HTMLButtonElement | null>;
    onItemSelect?: (key: string) => void;
}

export function GlobalActionsMenu({
    items,
    className,
    triggerAriaLabel = 'Abrir acciones',
    triggerLabel = 'Acciones',
    disabled,
    triggerRef,
    onItemSelect,
}: GlobalActionsMenuProps) {
    const [open, setOpen] = React.useState(false);

    const trigger = (
        <Button variant="outline" aria-label={triggerAriaLabel} disabled={disabled} ref={triggerRef}>
            {triggerLabel}
            <ChevronDown />
        </Button>
    );

    const handleItemSelect = (item: ActionItem, e: Event) => {
        // For dialog-opening items, close dropdown first to avoid focus conflicts
        if (item.key === 'export') {
            e.preventDefault();
            // 1) Close dropdown immediately to avoid aria-hidden conflicts
            setOpen(false);
            // 2) Open dialog in next frame after dropdown is closed
            requestAnimationFrame(() => {
                item.onSelect?.(e);
            });
        } else {
            // Normal item, let dropdown close naturally
            item.onSelect?.(e);
        }

        onItemSelect?.(item.key);
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent align="end" className={cn('min-w-[14rem]', className)}>
                <DropdownMenuGroup>
                    {items.map((item) => {
                        const Icon = item.icon;
                        return (
                            <DropdownMenuItem
                                key={item.key}
                                disabled={disabled || item.disabled}
                                onSelect={(e) => {
                                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                    const ev = (e as unknown as any).nativeEvent ?? (e as unknown as any);
                                    handleItemSelect(item, ev);
                                }}
                                className={cn(item.destructive && 'text-destructive focus:text-destructive data-[highlighted]:text-destructive')}
                            >
                                {Icon ? <Icon className="size-4" /> : null}
                                {item.label}
                            </DropdownMenuItem>
                        );
                    })}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

// Convenience preset for typical global actions
export function GlobalActionsMenuBasic({
    onExport,
    onDeleteSelected,
    disableDelete,
    ref,
}: {
    onExport?: () => void;
    onDeleteSelected?: () => void;
    disableDelete?: boolean;
    ref?: React.RefObject<HTMLButtonElement | null>;
}) {
    const items: ActionItem[] = [
        { key: 'export', label: 'Exportar…', onSelect: () => onExport?.(), icon: Download },
        {
            key: 'delete',
            label: 'Eliminar seleccionados…',
            onSelect: () => onDeleteSelected?.(),
            destructive: true,
            disabled: disableDelete,
            icon: Trash2,
        },
    ];
    return <GlobalActionsMenu items={items} triggerRef={ref} />;
}
