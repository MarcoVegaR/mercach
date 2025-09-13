import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/lib/toast';
import * as React from 'react';

export interface ConfirmAlertProps {
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    title?: React.ReactNode;
    description?: React.ReactNode;
    confirmLabel?: React.ReactNode;
    cancelLabel?: React.ReactNode;
    confirmDestructive?: boolean;
    onConfirm: (reason?: string) => Promise<void> | void;
    toastMessages?: {
        loading: React.ReactNode;
        success: React.ReactNode | ((data: unknown) => React.ReactNode);
        error: React.ReactNode | ((err: unknown) => React.ReactNode);
    };
    // Reason input for destructive actions
    requireReason?: boolean;
    reasonLabel?: string;
    reasonPlaceholder?: string;
    reasonMinLength?: number;
}

export function ConfirmAlert({
    trigger,
    open: controlledOpen,
    onOpenChange,
    title = '¿Estás seguro?',
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    confirmDestructive = true,
    onConfirm,
    toastMessages,
    requireReason = false,
    reasonLabel = 'Motivo',
    reasonPlaceholder = 'Ingresa el motivo de esta acción...',
    reasonMinLength = 0,
}: ConfirmAlertProps) {
    const [open, setOpen] = React.useState(false);
    const [pending, setPending] = React.useState(false);
    const [reason, setReason] = React.useState('');

    const isControlled = controlledOpen !== undefined;
    const actualOpen = isControlled ? controlledOpen : open;
    const setActualOpen = (v: boolean) => (isControlled ? onOpenChange?.(v) : setOpen(v));

    // Check if reason is valid when required
    const isReasonValid = !requireReason || reason.trim().length >= reasonMinLength;

    async function handleConfirm(e: React.MouseEvent) {
        // prevent AlertDialog from closing until we finish
        e.preventDefault();

        if (!isReasonValid) {
            return;
        }

        try {
            setPending(true);
            const p = Promise.resolve(onConfirm(requireReason ? reason.trim() : undefined));
            if (toastMessages) {
                await toast.promise(p, toastMessages);
            } else {
                await p;
            }
            setActualOpen(false);
            setReason(''); // Reset reason on success
        } catch {
            // toast.promise already handled if provided
            // keep dialog open to allow retry
        } finally {
            setPending(false);
        }
    }

    // Reset reason when dialog closes
    React.useEffect(() => {
        if (!actualOpen) {
            setReason('');
        }
    }, [actualOpen]);

    return (
        <AlertDialog open={actualOpen} onOpenChange={setActualOpen}>
            {trigger ? <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger> : null}
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description ? <AlertDialogDescription>{description}</AlertDialogDescription> : null}
                </AlertDialogHeader>

                {requireReason && (
                    <div className="space-y-2 py-4">
                        <Label htmlFor="reason">{reasonLabel}</Label>
                        <Input
                            id="reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder={reasonPlaceholder}
                            disabled={pending}
                        />
                        {reasonMinLength > 0 && reason.trim().length < reasonMinLength && reason.length > 0 && (
                            <p className="text-destructive text-sm">Mínimo {reasonMinLength} caracteres requeridos</p>
                        )}
                    </div>
                )}

                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <Button variant="secondary" disabled={pending}>
                            {cancelLabel}
                        </Button>
                    </AlertDialogCancel>
                    <AlertDialogAction asChild>
                        <Button
                            variant={confirmDestructive ? 'destructive' : 'default'}
                            onClick={handleConfirm}
                            disabled={pending || !isReasonValid}
                            isLoading={pending}
                            loadingText={confirmLabel}
                        >
                            {confirmLabel}
                        </Button>
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
