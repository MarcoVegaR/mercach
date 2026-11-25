import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Info, Loader2, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CollectionNoticeType } from './columns';

type Result = { status: 'loading' | 'not_applicable' | 'error' } | { status: 'ready'; url: string };

interface Props {
    concessionaireId: number | string;
    name: string;
    noticeType: CollectionNoticeType;
    onClose: () => void;
}

export function CollectionNoticeDialog({ concessionaireId, name, noticeType, onClose }: Props) {
    const [result, setResult] = useState<Result>({ status: 'loading' });
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        let pdfUrl: string | undefined;
        setResult({ status: 'loading' });

        async function prepareNotice() {
            try {
                const params = new URLSearchParams({ notice_type: noticeType, 'ids[]': String(concessionaireId) });
                const response = await fetch(`${route('catalogs.concessionaire.collection-notices')}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal,
                });
                if (controller.signal.aborted) return;
                if (response.status === 422 && response.headers.get('Content-Type')?.includes('application/json')) {
                    const data: { code?: string } = await response.json();
                    if (controller.signal.aborted) return;
                    if (data.code === 'collection_notice_not_applicable') {
                        setResult({ status: 'not_applicable' });
                        return;
                    }
                }
                if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) {
                    throw new Error('notice_generation_failed');
                }
                const blob = await response.blob();
                if (controller.signal.aborted) return;
                pdfUrl = URL.createObjectURL(blob);
                setResult({ status: 'ready', url: pdfUrl });
            } catch {
                if (!controller.signal.aborted) setResult({ status: 'error' });
            }
        }

        void prepareNotice();
        return () => {
            controller.abort();
            if (pdfUrl) URL.revokeObjectURL(pdfUrl);
        };
    }, [concessionaireId, noticeType, attempt]);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {result.status === 'not_applicable' && <Info className="text-muted-foreground size-5 shrink-0" />}
                        {result.status === 'not_applicable' ? 'No corresponde emitir el aviso de cobro' : 'Aviso de cobro'}
                    </DialogTitle>
                    <DialogDescription>
                        {result.status === 'not_applicable'
                            ? `${name} no tiene puestos con deuda vencida cobrable. No se generará ningún documento.`
                            : `${name} · ${noticeType === 'ordinary' ? 'Deuda ordinaria' : 'Convenio de pago'}`}
                    </DialogDescription>
                </DialogHeader>
                {result.status === 'loading' && (
                    <p role="status" className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Loader2 className="size-4 animate-spin motion-reduce:animate-none" />
                        Preparando el aviso de cobro…
                    </p>
                )}
                {result.status === 'ready' && (
                    <p role="status" className="text-muted-foreground text-sm">
                        El aviso está listo para abrir o imprimir.
                    </p>
                )}
                {result.status === 'error' && (
                    <p role="alert" className="text-destructive text-sm">
                        No se pudo generar el aviso de cobro. Verifica tu conexión y vuelve a intentarlo.
                    </p>
                )}
                <DialogFooter>
                    <Button variant={result.status === 'not_applicable' ? 'default' : 'outline'} onClick={onClose}>
                        {result.status === 'not_applicable' ? 'Entendido' : 'Cerrar'}
                    </Button>
                    {result.status === 'error' && <Button onClick={() => setAttempt((value) => value + 1)}>Reintentar</Button>}
                    {result.status === 'ready' && (
                        <Button onClick={() => window.open(result.url, '_blank', 'noopener,noreferrer')}>
                            <Printer className="size-4" />
                            Abrir PDF
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
