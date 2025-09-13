import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import * as React from 'react';

export interface SortableHeaderProps {
    children: React.ReactNode;
    sortDirection?: 'asc' | 'desc' | false;
    onSort?: () => void;
    className?: string;
    disabled?: boolean;
}

export function SortableHeader({ children, sortDirection, onSort, className, disabled = false }: SortableHeaderProps) {
    const getSortIcon = () => {
        if (sortDirection === 'asc') return <ArrowUp className="ml-2 h-4 w-4" />;
        if (sortDirection === 'desc') return <ArrowDown className="ml-2 h-4 w-4" />;
        // Hidden by default; visible on hover
        return <ArrowUpDown className="ml-2 h-4 w-4 opacity-0 transition-opacity group-hover:opacity-100" />;
    };

    const getAriaSort = (): 'ascending' | 'descending' | 'none' => {
        if (sortDirection === 'asc') return 'ascending';
        if (sortDirection === 'desc') return 'descending';
        return 'none';
    };

    if (!onSort || disabled) {
        return (
            <th className={cn('px-3 py-3 text-left', className)} aria-sort={sortDirection ? getAriaSort() : undefined}>
                {children}
            </th>
        );
    }

    return (
        <th className={cn('px-3 py-3 text-left', className)} aria-sort={getAriaSort()}>
            <Button variant="ghost" onClick={onSort} className="group h-auto p-0 font-medium hover:bg-transparent">
                <span className="flex items-center">
                    {children}
                    {getSortIcon()}
                </span>
            </Button>
        </th>
    );
}
