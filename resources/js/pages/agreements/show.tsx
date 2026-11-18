import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { AgreementSummary, type AgreementSnapshot } from './summary';

interface Props {
    agreement: {
        id: number;
        number: string;
        created_at: string;
        issuer: string | null;
        template_version: string;
        snapshot: AgreementSnapshot;
    };
}

export default function Show({ agreement }: Props) {
    const { snapshot } = agreement;
    const pdf = route('agreements.download', agreement.id);
    const people = [snapshot.concessionaire, ...(snapshot.additional_concessionaires ?? [])];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Convenios', href: route('agreements.index', { tab: 'history' }) },
                { title: agreement.number, href: '' },
            ]}
        >
            <Head title={`Convenio ${agreement.number}`} />
            <ShowLayout
                header={
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight">
                                Convenio N.º <span className="font-mono">{agreement.number}</span>
                            </h1>
                            <Badge>Emitido</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {agreement.created_at} · {agreement.issuer}
                        </p>
                    </div>
                }
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('agreements.index', { tab: 'history' })}>Volver al historial</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={pdf} target="_blank" rel="noopener noreferrer">
                                Abrir / imprimir
                            </a>
                        </Button>
                        <Button asChild>
                            <a href={pdf} download>
                                Descargar PDF
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={route('agreements.download-word', agreement.id)} download>
                                Descargar Word
                            </a>
                        </Button>
                    </div>
                }
            >
                <div className="flex flex-col gap-5">
                    <div className="bg-card flex flex-col gap-2 rounded-lg border p-4 text-sm sm:flex-row sm:justify-between sm:gap-6">
                        <div>
                            <span className="text-muted-foreground block">Cesionario principal</span>
                            <strong>{snapshot.concessionaire.name}</strong>
                        </div>
                        <div>
                            <span className="text-muted-foreground block">Local y rubro</span>
                            <strong>
                                {snapshot.local.code} · {snapshot.trade.name}
                            </strong>
                        </div>
                    </div>
                    <p className="text-muted-foreground text-sm">La emisión no acredita la firma ni sustituye el registro contractual.</p>
                    <p className="text-muted-foreground text-xs">
                        Word es una copia editable generada al descargar, no se almacena. Su distribución puede variar; el PDF es el original
                        conservado.
                    </p>
                    <Tabs defaultValue="document">
                        <TabsList aria-label="Consulta del convenio">
                            <TabsTrigger value="document">Documento</TabsTrigger>
                            <TabsTrigger value="data">Datos de emisión</TabsTrigger>
                        </TabsList>
                        <TabsContent value="document" forceMount className="data-[state=inactive]:hidden">
                            <div className="flex flex-col gap-3">
                                <p className="text-muted-foreground text-sm">PDF original conservado · Solo lectura</p>
                                <iframe
                                    title={`PDF del convenio ${agreement.number}`}
                                    src={pdf}
                                    className="h-[75vh] w-full rounded-xl border bg-white"
                                />
                            </div>
                        </TabsContent>
                        <TabsContent value="data">
                            <div className="bg-card grid gap-8 rounded-xl border p-6 lg:grid-cols-2">
                                <ShowSection id="preserved-data" title="Datos utilizados">
                                    <AgreementSummary snapshot={snapshot} />
                                </ShowSection>
                                <ShowSection id="signers-data" title="Perfiles preservados">
                                    <div className="flex flex-col gap-5">
                                        {people.map((person) => (
                                            <div key={person.document} className="flex flex-col gap-1 text-sm">
                                                <h3 className="font-semibold">
                                                    {person.name} · {person.document}
                                                </h3>
                                                <p>
                                                    {person.sex === 'F' ? 'Femenino' : 'Masculino'} · {person.marital_status_label}
                                                </p>
                                                {person.address && <p>{person.address}</p>}
                                                <p>
                                                    {person.email} · {person.phone}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </ShowSection>
                                <ShowSection id="document-metadata" title="Trazabilidad">
                                    <dl className="flex flex-col gap-2 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground">Emitido por</dt>
                                            <dd>
                                                {agreement.issuer} · {agreement.created_at}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">Versión de plantilla</dt>
                                            <dd>{agreement.template_version}</dd>
                                        </div>
                                    </dl>
                                    <p className="text-muted-foreground mt-3 text-xs">
                                        Estos datos son históricos. Los cambios posteriores en perfiles y catálogos no modifican este documento.
                                    </p>
                                </ShowSection>
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>
            </ShowLayout>
        </AppLayout>
    );
}
