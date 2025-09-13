import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Download, Power, PowerOff, Trash2, X } from 'lucide-react';
import * as React from 'react';

export interface BulkActionBarProps {
    selectedCount: number;
    onDeleteSelected?: () => void;
    onActivateSelected?: () => void;
    onDeactivateSelected?: () => void;
    onExportSelected?: () => void;
    onClearSelection?: () => void;
    className?: string;
    actions?: React.ReactNode;
}

export function BulkActionBar({
    selectedCount,
    onDeleteSelected,
    onActivateSelected,
    onDeactivateSelected,
    onExportSelected,
    onClearSelection,
    className,
    actions,
}: BulkActionBarProps) {
    if (selectedCount === 0) {
        return null;
    }

    return (
        <div className={cn('bg-muted/50 flex items-center justify-between gap-2 rounded-md border p-2', className)}>
            <div className="flex items-center gap-2">
                <span className="text-muted-foreground text-sm font-medium">
                    {selectedCount} elemento{selectedCount !== 1 ? 's' : ''} seleccionado{selectedCount !== 1 ? 's' : ''}
                </span>

                <div className="flex items-center gap-1">
                    {onActivateSelected && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onActivateSelected}
                            className="h-8 text-green-600 hover:bg-green-600 hover:text-white"
                        >
                            <Power className="mr-1 h-4 w-4" />
                            Activar
                        </Button>
                    )}

                    {onDeactivateSelected && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onDeactivateSelected}
                            className="h-8 text-amber-600 hover:bg-amber-600 hover:text-white"
                        >
                            <PowerOff className="mr-1 h-4 w-4" />
                            Desactivar
                        </Button>
                    )}

                    {onDeleteSelected && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onDeleteSelected}
                            className="text-destructive hover:bg-destructive hover:text-destructive-foreground h-8"
                        >
                            <Trash2 className="mr-1 h-4 w-4" />
                            Eliminar
                        </Button>
                    )}

                    {onExportSelected && (
                        <Button variant="outline" size="sm" onClick={onExportSelected} className="h-8">
                            <Download className="mr-1 h-4 w-4" />
                            Exportar
                        </Button>
                    )}

                    {actions}
                </div>
            </div>

            {onClearSelection && (
                <Button variant="ghost" size="sm" onClick={onClearSelection} className="h-8 w-8 p-0">
                    <X className="h-4 w-4" />
                    <span className="sr-only">Limpiar selección</span>
                </Button>
            )}
        </div>
    );
}
