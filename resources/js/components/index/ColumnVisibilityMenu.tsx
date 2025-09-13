import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ColumnVisibilityState } from '@/lib/table-types';
import { cn } from '@/lib/utils';
import { Settings2 } from 'lucide-react';

export interface ColumnVisibilityMenuProps {
    columns: Array<{
        id: string;
        label: string;
        canHide?: boolean;
    }>;
    columnVisibility: ColumnVisibilityState;
    onColumnVisibilityChange: (visibility: ColumnVisibilityState) => void;
    className?: string;
}

export function ColumnVisibilityMenu({ columns, columnVisibility, onColumnVisibilityChange, className }: ColumnVisibilityMenuProps) {
    const handleToggleColumn = (columnId: string, checked: boolean) => {
        onColumnVisibilityChange({
            ...columnVisibility,
            [columnId]: checked,
        });
    };

    const visibleCount = columns.filter((col) => columnVisibility[col.id] !== false).length;

    const hideableColumns = columns.filter((col) => col.canHide !== false);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className={cn('h-8 border-dashed', className)}>
                    <Settings2 className="mr-2 h-4 w-4" />
                    Columnas
                    {visibleCount < columns.length && <span className="bg-muted ml-1 rounded px-1 text-xs">{visibleCount}</span>}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[200px]">
                <DropdownMenuLabel>Mostrar columnas</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {hideableColumns.map((column) => {
                    const isVisible = columnVisibility[column.id] !== false;
                    return (
                        <DropdownMenuItem
                            key={column.id}
                            className="flex items-center space-x-2"
                            onSelect={(e) => {
                                e.preventDefault();
                                handleToggleColumn(column.id, !isVisible);
                            }}
                        >
                            <Checkbox checked={isVisible} onCheckedChange={(checked) => handleToggleColumn(column.id, !!checked)} />
                            <span className="flex-1">{column.label}</span>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
