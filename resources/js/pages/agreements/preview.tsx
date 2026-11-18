import { ConfirmAlert } from '@/components/dialogs/confirm-alert';
import { ErrorSummary } from '@/components/form/ErrorSummary';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { AgreementSummary, type AgreementSnapshot } from './summary';

interface Props {
    pdfUrl: string;
    wordUrl: string;
    snapshot: AgreementSnapshot;
    values: {
        concessionaire_id: string;
        additional_concessionaire_ids?: string[];
        source_contract_id?: string;
        local_id: string;
        trade_category_id: string;
        signature_date: string;
        start_date: string;
        issuance_token: string;
        preview_hash: string;
    };
}

export default function Preview({ pdfUrl, wordUrl, values, snapshot }: Props) {
    const form = useForm(values);

    function issue(): Promise<void> {
        return new Promise((resolve, reject) => {
            form.post(route('agreements.store'), {
                onSuccess: () => resolve(),
                onError: () => reject(new Error('validation_failed')),
                onCancel: () => reject(new Error('cancelled')),
                onFinish: () => reject(new Error('request_finished_without_success')),
            });
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Convenios', href: route('agreements.index') },
                { title: 'Revisar', href: '' },
            ]}
        >
            <Head title="Revisar convenio" />
            <ShowLayout
                className="[&_aside]:order-first lg:[&_aside]:order-last"
                header={
                    <div className="flex flex-col gap-2">
                        <Badge variant="secondary" className="w-fit">
                            Vista previa · Sin correlativo
                        </Badge>
                        <h1 className="text-2xl font-bold tracking-tight">Revisar convenio</h1>
                        <p className="text-muted-foreground text-sm">Verifica los datos y el documento antes de emitir.</p>
                    </div>
                }
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('agreements.index')}>Volver y corregir</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={wordUrl} download>
                                Descargar Word
                            </a>
                        </Button>
                        <ConfirmAlert
                            trigger={<Button disabled={form.processing}>Emitir convenio</Button>}
                            title="Emitir convenio definitivo"
                            description={
                                <span className="flex flex-col gap-3">
                                    <span>Se asignará el siguiente correlativo disponible.</span>
                                    <span>
                                        El PDF y los datos utilizados se conservarán sin posibilidad de modificación. Cualquier corrección requerirá
                                        una nueva emisión.
                                    </span>
                                    <span>La emisión no registra un contrato ni acredita su firma.</span>
                                    {Object.keys(form.errors).length > 0 && (
                                        <span role="alert" className="text-destructive">
                                            {Object.values(form.errors).join(' ')}
                                        </span>
                                    )}
                                </span>
                            }
                            confirmLabel="Emitir convenio"
                            cancelLabel="Seguir revisando"
                            confirmDestructive={false}
                            onConfirm={issue}
                        />
                    </div>
                }
                aside={
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Verificar datos</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5">
                            <AgreementSummary snapshot={snapshot} />
                            <p className="text-muted-foreground text-xs">
                                Perfiles comprobados al preparar esta vista previa. Se verificará nuevamente que los datos no hayan cambiado al
                                emitir.
                            </p>
                        </CardContent>
                    </Card>
                }
            >
                <div className="flex flex-col gap-4">
                    {Object.keys(form.errors).length > 0 && <ErrorSummary errors={form.errors} />}
                    <p className="text-muted-foreground text-xs">
                        Word es una copia editable. La distribución puede variar en Word o LibreOffice; el PDF conserva el formato original.
                    </p>
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-semibold">Documento completo</h2>
                        <a href={pdfUrl} target="_blank" rel="noopener noreferrer" className="text-sm underline">
                            Abrir PDF en otra pestaña
                        </a>
                    </div>
                    <iframe title="Vista previa del convenio" src={pdfUrl} className="h-[75vh] w-full rounded-xl border bg-white" />
                </div>
            </ShowLayout>
        </AppLayout>
    );
}
