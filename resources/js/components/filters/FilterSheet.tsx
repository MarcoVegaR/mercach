import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Filter } from 'lucide-react';
import React from 'react';

interface FilterSheetProps {
    children: React.ReactNode;
    activeFiltersCount: number;
    onApplyFilters: () => void;
    onClearFilters: () => void;
    title?: string;
    description?: string;
}

export function FilterSheet({
    children,
    activeFiltersCount,
    onApplyFilters,
    onClearFilters,
    title = 'Filtros Avanzados',
    description = 'Aplica filtros para refinar tu búsqueda',
}: FilterSheetProps) {
    const [open, setOpen] = React.useState(false);

    const handleApply = () => {
        onApplyFilters();
        setOpen(false);
    };

    return (
        <Sheet modal={false} open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant="outline" className="flex items-center gap-2 border-gray-200 dark:border-gray-700">
                    <Filter className="h-4 w-4 text-fuchsia-600 dark:text-fuchsia-400" />
                    <span>Filtros</span>
                    {activeFiltersCount > 0 && (
                        <Badge
                            variant="secondary"
                            className="ml-1 flex h-5 w-5 items-center justify-center rounded-full bg-indigo-500 p-0 text-xs text-white"
                        >
                            {activeFiltersCount}
                        </Badge>
                    )}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>

                <div className="mt-6 space-y-6">{children}</div>

                <div className="mt-8 flex gap-2">
                    <Button onClick={handleApply} className="flex-1">
                        Aplicar Filtros
                    </Button>
                    <Button variant="outline" onClick={onClearFilters} className="flex-1">
                        Limpiar Todo
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
