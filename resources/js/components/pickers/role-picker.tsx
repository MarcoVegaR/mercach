import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';
import RoleForm from '@/pages/roles/form';
import { router } from '@inertiajs/react';
import { Check, Plus, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    is_active: boolean;
    permissions_count?: number;
}

interface RolePickerProps {
    value?: number | number[];
    onChange: (value: number | number[] | undefined) => void;
    options?: Role[];
    multiple?: boolean;
    placeholder?: string;
    className?: string;
    disabled?: boolean;
    allowCreate?: boolean;
    canCreate?: boolean;
    createOptions?: {
        guards: Array<{ value: string; label: string }>;
        permissions: Array<{ value: number; label: string; name: string; guard: string }>;
    };
}

export function RolePicker({
    value,
    onChange,
    options = [],
    multiple = false,
    placeholder = 'Seleccionar rol(es)',
    className,
    disabled = false,
    allowCreate = false,
    canCreate = false,
    createOptions,
}: RolePickerProps) {
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [roles, setRoles] = useState<Role[]>(options);

    // Update roles when options prop changes
    useEffect(() => {
        setRoles(options);
    }, [options]);

    // Filter roles based on search
    const filteredRoles = roles.filter((role) => role.name.toLowerCase().includes(search.toLowerCase()));

    // Get selected roles
    const selectedIds = multiple ? (value as number[]) || [] : value ? [value as number] : [];

    const selectedRoles = roles.filter((role) => selectedIds.includes(role.id));

    // Handle role selection
    const handleSelect = (roleId: number) => {
        if (multiple) {
            const current = selectedIds;
            const updated = current.includes(roleId) ? current.filter((id) => id !== roleId) : [...current, roleId];
            onChange(updated);
        } else {
            onChange(roleId);
            setIsOpen(false);
        }
    };

    // Handle remove selection
    const handleRemove = (roleId: number) => {
        if (multiple) {
            const updated = selectedIds.filter((id) => id !== roleId);
            onChange(updated);
        } else {
            // Clear selection for single mode
            onChange(undefined);
        }
    };

    // Handle create success
    const handleCreateSuccess = () => {
        setIsCreateModalOpen(false);

        // Reload roles
        router.reload({
            only: ['options'],
            preserveUrl: true,
            onSuccess: () => {
                toast.success('Rol creado y agregado a la selección');
            },
        });
    };

    return (
        <>
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogTrigger asChild>
                    <Button
                        variant="outline"
                        className={cn('w-full justify-between', !selectedRoles.length && 'text-muted-foreground', className)}
                        disabled={disabled}
                    >
                        <span className="flex-1 overflow-hidden text-left">
                            {selectedRoles.length > 0 ? selectedRoles.map((r) => r.name).join(', ') : placeholder}
                        </span>
                        <span className="ml-2 flex items-center gap-1">
                            {selectedRoles.length > 0 && <span className="text-muted-foreground text-xs">({selectedRoles.length})</span>}
                            <Search className="h-4 w-4" />
                        </span>
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Seleccionar Rol{multiple && 'es'}</DialogTitle>
                        <DialogDescription>Busca y selecciona {multiple ? 'los roles' : 'un rol'} de la lista</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {/* Search input */}
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input placeholder="Buscar roles..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9" />
                        </div>

                        {/* Roles list */}
                        <ScrollArea className="h-[300px] rounded-md border p-4">
                            {filteredRoles.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <p className="text-muted-foreground text-sm">No se encontraron roles</p>
                                    {allowCreate && canCreate && (
                                        <Button
                                            variant="link"
                                            size="sm"
                                            onClick={() => {
                                                setIsOpen(false);
                                                setIsCreateModalOpen(true);
                                            }}
                                            className="mt-2"
                                        >
                                            <Plus className="mr-1 h-4 w-4" />
                                            Crear nuevo rol
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {filteredRoles.map((role) => {
                                        const isSelected = selectedIds.includes(role.id);
                                        return (
                                            <div
                                                key={role.id}
                                                className={cn(
                                                    'flex items-start space-x-2 rounded-md p-2 transition-colors',
                                                    isSelected && 'bg-accent',
                                                    'hover:bg-accent/50 cursor-pointer',
                                                )}
                                                onClick={() => handleSelect(role.id)}
                                            >
                                                {multiple ? (
                                                    <Checkbox
                                                        checked={isSelected}
                                                        onCheckedChange={() => handleSelect(role.id)}
                                                        onClick={(e) => e.stopPropagation()}
                                                        className="data-[state=checked]:border-primary data-[state=checked]:bg-primary focus-visible:ring-primary/50 border-2 border-slate-400 shadow-sm hover:border-slate-500 dark:border-slate-500 dark:hover:border-slate-400"
                                                    />
                                                ) : (
                                                    <div className="flex h-5 w-5 items-center justify-center">
                                                        {isSelected && <Check className="text-primary h-4 w-4" />}
                                                    </div>
                                                )}
                                                <div className="flex-1 space-y-1">
                                                    <Label className="cursor-pointer text-sm font-medium">{role.name}</Label>
                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                        <span>{role.guard_name}</span>
                                                        {role.permissions_count !== undefined && (
                                                            <>
                                                                <span>•</span>
                                                                <span>{role.permissions_count} permisos</span>
                                                            </>
                                                        )}
                                                        {!role.is_active && (
                                                            <>
                                                                <span>•</span>
                                                                <span className="text-yellow-600">Inactivo</span>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}

                                    {allowCreate && canCreate && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setIsOpen(false);
                                                setIsCreateModalOpen(true);
                                            }}
                                            className="w-full justify-start"
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Crear nuevo rol
                                        </Button>
                                    )}
                                </div>
                            )}
                        </ScrollArea>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Selected roles display (for multiple selection) */}
            {multiple && selectedRoles.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                    {selectedRoles.map((role) => (
                        <div key={role.id} className="bg-secondary inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                            <span>{role.name}</span>
                            <button
                                type="button"
                                onClick={() => handleRemove(role.id)}
                                className="hover:bg-secondary-foreground/20 ml-1 rounded-sm"
                                disabled={disabled}
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Create modal */}
            {allowCreate && canCreate && createOptions && (
                <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                    <DialogContent className="sm:max-w-[600px]">
                        <DialogHeader>
                            <DialogTitle>Crear nuevo rol</DialogTitle>
                            <DialogDescription>Define un nuevo rol con sus permisos correspondientes</DialogDescription>
                        </DialogHeader>
                        <div className="mt-4">
                            <RoleForm mode="create" options={createOptions} can={{ 'roles.create': true }} onSaved={handleCreateSuccess} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
