import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
// Removed calendar popover due to dialog interaction issues; use native date input
import { FileDropzone } from '@/components/ui/file-dropzone';
import React from 'react';
import { toast } from 'sonner';
import type { ExpRow } from './expenses-columns';

export type ExpenseTypeOpt = { id: number; name: string };

function csrfToken() {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

// Helper function for date formatting (currently unused)
// function toYMD(d?: Date): string {
//   if (!d) return '';
//   const y = d.getFullYear();
//   const m = String(d.getMonth() + 1).padStart(2, '0');
//   const day = String(d.getDate()).padStart(2, '0');
//   return `${y}-${m}-${day}`;
// }

export default function ExpenseFormDialog({
    open,
    onOpenChange,
    periodId,
    usedExpenseTypeIds = [],
    options = [],
    mode = 'create',
    row,
    onSaved,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    periodId: number;
    usedExpenseTypeIds?: number[];
    options?: ExpenseTypeOpt[];
    mode?: 'create' | 'edit';
    row?: ExpRow | null;
    onSaved: (totals?: { expenses_count: number; total_usd_minor: number }) => void;
}) {
    const [expenseTypeId, setExpenseTypeId] = React.useState<string>('');
    const [amountUsd, setAmountUsd] = React.useState<string>('');
    const [expenseDate, setExpenseDate] = React.useState<string>('');
    const [invoiceNumber, setInvoiceNumber] = React.useState<string>('');
    const [note, setNote] = React.useState<string>('');
    const [attachment, setAttachment] = React.useState<File | null>(null);
    const [submitting, setSubmitting] = React.useState(false);

    React.useEffect(() => {
        if (open) {
            if (mode === 'edit' && row) {
                setExpenseTypeId(String(row.expense_type_id));
                setAmountUsd(((row.amount_usd_minor || 0) / 100).toFixed(2));
                setExpenseDate(row.expense_date ?? '');
                setInvoiceNumber(row.invoice_number ?? '');
                setNote(row.note ?? '');
                setAttachment(null);
            } else {
                setExpenseTypeId('');
                setAmountUsd('');
                setExpenseDate('');
                setInvoiceNumber('');
                setNote('');
                setAttachment(null);
            }
        }
    }, [open, mode, row]);

    // Bank-style amount input
    const onAmountChange = (raw: string) => {
        const digits = String(raw).replace(/[^0-9]/g, '');
        if (!digits) {
            setAmountUsd('');
            return;
        }
        const whole = digits.slice(0, -2) || '0';
        const cents = digits.slice(-2).padStart(2, '0');
        setAmountUsd(`${String(parseInt(whole, 10))}.${cents}`);
    };

    const availableTypes = React.useMemo(() => {
        const current = Number(expenseTypeId || 0);
        const used = new Set<number>(usedExpenseTypeIds);
        return options.filter((o) => current === o.id || !used.has(o.id));
    }, [options, usedExpenseTypeIds, expenseTypeId]);

    const submit = async () => {
        const typeIdToSend = expenseTypeId || (mode === 'edit' && row ? String(row.expense_type_id) : '');
        if (!typeIdToSend || !amountUsd) {
            toast.error('Tipo y monto son obligatorios');
            return;
        }
        setSubmitting(true);
        try {
            const fd = new FormData();
            // Ensure integer string
            fd.append('expense_type_id', String(Number(typeIdToSend)));
            fd.append('amount_usd', amountUsd);
            if (expenseDate) fd.append('expense_date', expenseDate);
            if (invoiceNumber) fd.append('invoice_number', invoiceNumber);
            if (note) fd.append('note', note);
            if (attachment) fd.append('attachment', attachment);

            const url = mode === 'edit' && row ? `/condo/expenses/${row.id}` : `/condo/periods/${periodId}/expenses`;
            // Use POST + method override for updates to ensure Laravel processes multipart form fields
            if (mode === 'edit') {
                fd.append('_method', 'PUT');
            }
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                credentials: 'same-origin',
                body: fd,
            });
            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                const msg = body?.message || 'No se pudo guardar el gasto';
                toast.error(msg);
                return;
            }
            const body = await res.json().catch(() => ({}) as any);
            toast.success('Gasto guardado');
            onOpenChange(false);
            onSaved((body as any)?.totals);
        } catch {
            toast.error('No se pudo guardar el gasto');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{mode === 'edit' ? 'Editar gasto' : 'Nuevo gasto'}</DialogTitle>
                    <DialogDescription>Complete los campos obligatorios y opcionales.</DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    <div className="space-y-1">
                        <Label htmlFor="expense_type">Tipo de gasto</Label>
                        <Select value={expenseTypeId} onValueChange={setExpenseTypeId}>
                            <SelectTrigger id="expense_type" className="w-full">
                                <SelectValue placeholder="Seleccionar tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableTypes.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="amount_usd">Monto USD</Label>
                            <Input id="amount_usd" value={amountUsd} onChange={(e) => onAmountChange(e.target.value)} placeholder="0.00" />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="expense_date">Fecha</Label>
                            <Input
                                id="expense_date"
                                type="date"
                                value={expenseDate}
                                onChange={(e) => setExpenseDate(e.target.value)}
                                className="w-full"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="invoice">Factura</Label>
                            <Input id="invoice" value={invoiceNumber} onChange={(e) => setInvoiceNumber(e.target.value)} placeholder="Opcional" />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="note">Nota</Label>
                            <Textarea id="note" value={note} onChange={(e) => setNote(e.target.value)} placeholder="Opcional" />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label>Comprobante (PDF/JPG/PNG)</Label>
                        <FileDropzone
                            onFileSelect={setAttachment}
                            file={attachment}
                            accept="application/pdf,image/jpeg,image/png"
                            maxSize="10 MB"
                            preview={false}
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={submitting}>
                        Cancelar
                    </Button>
                    <Button onClick={submit} disabled={submitting} isLoading={submitting} loadingText="Guardando…">
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
