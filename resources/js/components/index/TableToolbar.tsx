import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Search, X } from 'lucide-react';
import * as React from 'react';

export interface TableToolbarProps {
    children?: React.ReactNode;
    globalFilter?: string;
    onGlobalFilterChange?: (value: string) => void;
    className?: string;
    searchPlaceholder?: string;
}

export function TableToolbar({ children, globalFilter = '', onGlobalFilterChange, className, searchPlaceholder = 'Buscar...' }: TableToolbarProps) {
    const [searchValue, setSearchValue] = React.useState(globalFilter);

    React.useEffect(() => {
        setSearchValue(globalFilter);
    }, [globalFilter]);

    const handleSearchChange = (value: string) => {
        setSearchValue(value);
        onGlobalFilterChange?.(value);
    };

    const handleClearSearch = () => {
        setSearchValue('');
        onGlobalFilterChange?.('');
    };

    return (
        <div className={cn('flex items-center gap-2 p-1', className)}>
            {/* Global Search */}
            {onGlobalFilterChange && (
                <div className="relative flex items-center">
                    <Search className="text-muted-foreground absolute left-2 h-4 w-4" />
                    <Input
                        placeholder={searchPlaceholder}
                        value={searchValue}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        className="h-8 w-[150px] pl-8 lg:w-[250px]"
                    />
                    {searchValue && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="absolute right-0 h-full px-2 py-0 hover:bg-transparent"
                            onClick={handleClearSearch}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            )}

            {/* Scrollable custom filters/components slot */}
            <div className="min-w-0 flex-1 overflow-x-auto whitespace-nowrap">{children}</div>
        </div>
    );
}
