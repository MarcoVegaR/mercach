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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/lib/toast';
import * as React from 'react';

export interface ConfirmWithReasonDialogProps {
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    title?: React.ReactNode;
    description?: React.ReactNode;
    confirmLabel?: React.ReactNode;
    cancelLabel?: React.ReactNode;
    reasonLabel?: React.ReactNode;
    reasonPlaceholder?: string;
    reasonRequired?: boolean;
    validateReason?: (reason: string) => string | null;
    onConfirm: (reason: string) => Promise<void> | void;
    confirmDestructive?: boolean;
    toastMessages?: {
        loading: React.ReactNode;
        success: React.ReactNode | ((data: unknown) => React.ReactNode);
        error: React.ReactNode | ((err: unknown) => React.ReactNode);
    };
}

export function ConfirmWithReasonDialog({
    trigger,
    open: controlledOpen,
    onOpenChange,
    title = 'Confirmar acción',
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    reasonLabel = 'Motivo',
    reasonPlaceholder = 'Escribe un motivo…',
    reasonRequired = true,
    validateReason,
    onConfirm,
    confirmDestructive = true,
    toastMessages,
}: ConfirmWithReasonDialogProps) {
    const [open, setOpen] = React.useState(false);
    const isControlled = controlledOpen !== undefined;
    const actualOpen = isControlled ? controlledOpen : open;
    const setActualOpen = (v: boolean) => (isControlled ? onOpenChange?.(v) : setOpen(v));

    const [reason, setReason] = React.useState('');
    const [error, setError] = React.useState<string | null>(null);
    const [pending, setPending] = React.useState(false);
    const inputRef = React.useRef<HTMLInputElement>(null);

    const disabled = pending || (reasonRequired && !reason.trim()) || !!error;

    React.useEffect(() => {
        if (actualOpen) {
            // wait for portal mount before focusing
            const id = setTimeout(() => inputRef.current?.focus(), 0);
            return () => clearTimeout(id);
        }
    }, [actualOpen]);

    function handleValidate(next: string) {
        const custom = validateReason?.(next);
        setError(custom ?? null);
    }

    async function handleConfirm() {
        try {
            setPending(true);
            const p = Promise.resolve(onConfirm(reason));
            if (toastMessages) {
                await toast.promise(p, toastMessages);
            } else {
                await p;
            }
            setActualOpen(false);
            setReason('');
            setError(null);
        } catch {
            // toast.promise already handled if provided
        } finally {
            setPending(false);
        }
    }

    return (
        <Dialog
            open={actualOpen}
            onOpenChange={(v) => {
                setActualOpen(v);
                if (!v) {
                    setReason('');
                    setError(null);
                }
            }}
        >
            {trigger ? <DialogTrigger asChild>{trigger}</DialogTrigger> : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? <DialogDescription>{description}</DialogDescription> : null}
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="confirm-reason" className="sr-only">
                        {reasonLabel}
                    </Label>
                    <Input
                        id="confirm-reason"
                        ref={inputRef}
                        value={reason}
                        onChange={(e) => {
                            const v = e.target.value;
                            setReason(v);
                            handleValidate(v);
                        }}
                        placeholder={reasonPlaceholder}
                        aria-invalid={!!error}
                    />
                    {error ? (
                        <p className="text-destructive text-xs" role="alert">
                            {error}
                        </p>
                    ) : null}
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary" disabled={pending}>
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        variant={confirmDestructive ? 'destructive' : 'default'}
                        onClick={handleConfirm}
                        disabled={disabled}
                        isLoading={pending}
                        loadingText={confirmLabel}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
