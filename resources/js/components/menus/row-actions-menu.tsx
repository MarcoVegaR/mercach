import { MoreVertical, Pencil, Trash2 } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type ActionItem = {
    key: string;
    label: React.ReactNode;
    onSelect?: (ev: Event) => void | Promise<void>;
    disabled?: boolean;
    destructive?: boolean;
    icon?: React.ComponentType<{ className?: string }>;
};

export interface RowActionsMenuProps {
    items: ActionItem[];
    className?: string;
    triggerAriaLabel?: string;
    trigger?: React.ReactNode;
}

export function RowActionsMenu({ items, className, triggerAriaLabel = 'Abrir acciones de fila', trigger }: RowActionsMenuProps) {
    const defaultTrigger = (
        <Button variant="ghost" size="icon" aria-label={triggerAriaLabel}>
            <MoreVertical />
        </Button>
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>{trigger ?? defaultTrigger}</DropdownMenuTrigger>
            <DropdownMenuContent align="end" className={cn('min-w-[12rem]', className)}>
                <DropdownMenuGroup>
                    {items.map((item, _idx) => {
                        const Icon = item.icon;
                        const isDestructive = !!item.destructive;
                        return (
                            <DropdownMenuItem
                                key={item.key}
                                disabled={item.disabled}
                                onSelect={(e) => {
                                    // Radix passes a CustomEvent-like; prevent page navigation
                                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                    const ev = (e as unknown as any).nativeEvent ?? (e as unknown as any);
                                    item.onSelect?.(ev);
                                }}
                                className={cn(
                                    isDestructive && 'text-destructive focus:text-destructive data-[highlighted]:text-destructive',
                                    isDestructive && 'focus:bg-destructive/10 data-[highlighted]:bg-destructive/10',
                                )}
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

// Convenience preset with common actions (Edit / Delete)
export function RowActionsMenuBasic({ onEdit, onDelete, disabledDelete }: { onEdit?: () => void; onDelete?: () => void; disabledDelete?: boolean }) {
    const items: ActionItem[] = [
        {
            key: 'edit',
            label: 'Editar',
            onSelect: () => onEdit?.(),
            icon: Pencil,
        },
        {
            key: 'delete',
            label: 'Eliminar',
            onSelect: () => onDelete?.(),
            destructive: true,
            disabled: disabledDelete,
            icon: Trash2,
        },
    ];

    return <RowActionsMenu items={items} />;
}
