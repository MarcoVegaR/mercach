import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import * as SelectPrimitive from '@radix-ui/react-select';
import { ChevronDown, ChevronUp } from 'lucide-react';
import * as React from 'react';

// Non-portalled SelectContent for use inside Dialogs to avoid focus conflicts
const DialogSelectContent = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof SelectPrimitive.Content>
>(({ className, children, position = 'popper', ...props }, ref) => (
    // No Portal wrapper - renders inline to avoid aria-hidden conflicts
    <SelectPrimitive.Content
        ref={ref}
        className={cn(
            'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 relative z-50 max-h-96 min-w-[8rem] overflow-hidden rounded-md border shadow-md',
            position === 'popper' &&
                'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
            className,
        )}
        position={position}
        {...props}
    >
        <SelectPrimitive.ScrollUpButton className="flex cursor-default items-center justify-center py-1">
            <ChevronUp className="h-4 w-4" />
        </SelectPrimitive.ScrollUpButton>
        <SelectPrimitive.Viewport
            className={cn('p-1', position === 'popper' && 'h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)]')}
        >
            {children}
        </SelectPrimitive.Viewport>
        <SelectPrimitive.ScrollDownButton className="flex cursor-default items-center justify-center py-1">
            <ChevronDown className="h-4 w-4" />
        </SelectPrimitive.ScrollDownButton>
    </SelectPrimitive.Content>
));

export type ExportFormat = 'csv' | 'json' | 'pdf';

export interface ExportDialogProps {
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    title?: React.ReactNode;
    description?: React.ReactNode;
    confirmLabel?: React.ReactNode;
    cancelLabel?: React.ReactNode;
    initialFormat?: ExportFormat;
    availableFormats?: ExportFormat[];
    onExport: (format: ExportFormat) => Promise<void> | void;
    toastMessages?: {
        loading: React.ReactNode;
        success: React.ReactNode | ((data: unknown) => React.ReactNode);
        error: React.ReactNode | ((err: unknown) => React.ReactNode);
    };
    focusAfterClose?: React.RefObject<HTMLElement | null>;
}

export function ExportDialog({
    trigger,
    open: controlledOpen,
    onOpenChange,
    title = 'Exportar',
    description = 'Elige el formato para exportar los datos actuales.',
    confirmLabel = 'Exportar',
    cancelLabel = 'Cancelar',
    initialFormat = 'csv',
    availableFormats = ['csv', 'json', 'pdf'],
    onExport,
    toastMessages,
    focusAfterClose,
}: ExportDialogProps) {
    const [open, setOpen] = React.useState(false);
    const isControlled = controlledOpen !== undefined;
    const actualOpen = isControlled ? controlledOpen : open;
    const setActualOpen = (v: boolean) => (isControlled ? onOpenChange?.(v) : setOpen(v));

    const [format, setFormat] = React.useState<ExportFormat>(initialFormat);
    const [pending, setPending] = React.useState(false);
    const previousElementRef = React.useRef<HTMLElement | null>(null);

    React.useEffect(() => {
        if (actualOpen) setFormat(initialFormat);
    }, [actualOpen, initialFormat]);

    async function handleConfirm() {
        try {
            setPending(true);
            const p = Promise.resolve(onExport(format));
            if (toastMessages) {
                await toast.promise(p, toastMessages);
            } else {
                await p;
            }
            setActualOpen(false);
        } catch {
            // toast.promise already handled if provided
        } finally {
            setPending(false);
        }
    }

    return (
        <Dialog open={actualOpen} onOpenChange={setActualOpen}>
            {trigger ? <DialogTrigger asChild>{trigger}</DialogTrigger> : null}
            <DialogContent
                onOpenAutoFocus={(_e) => {
                    // Capture the currently focused element before dialog opens
                    previousElementRef.current = document.activeElement as HTMLElement | null;
                    // Let dialog focus its first focusable element by default
                }}
                onCloseAutoFocus={(e) => {
                    // Prevent default focus restoration and manually focus a stable element
                    e.preventDefault();
                    // Use requestAnimationFrame to ensure the element exists and is ready
                    requestAnimationFrame(() => {
                        // Try focusAfterClose first (the "Acciones" button)
                        if (focusAfterClose?.current && document.contains(focusAfterClose.current)) {
                            focusAfterClose.current.focus();
                        }
                        // Fallback to previously focused element if it still exists
                        else if (previousElementRef.current && document.contains(previousElementRef.current)) {
                            previousElementRef.current.focus();
                        }
                        // Last resort: focus body to prevent orphaned focus state
                        else {
                            document.body.focus();
                        }
                    });
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? <DialogDescription>{description}</DialogDescription> : null}
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="export-format" className="sr-only">
                        Formato
                    </Label>
                    <Select value={format} onValueChange={(v) => setFormat(v as ExportFormat)}>
                        <SelectTrigger id="export-format" aria-label="Formato">
                            <SelectValue placeholder="Selecciona formato" />
                        </SelectTrigger>
                        <DialogSelectContent>
                            {availableFormats.map((f) => (
                                <SelectItem key={f} value={f}>
                                    {f.toUpperCase()}
                                </SelectItem>
                            ))}
                        </DialogSelectContent>
                    </Select>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary" disabled={pending}>
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button onClick={handleConfirm} disabled={pending} isLoading={pending} loadingText={confirmLabel}>
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
