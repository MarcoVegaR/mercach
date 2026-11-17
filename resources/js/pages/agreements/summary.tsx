import { formatDateShort } from '@/lib/date-utils';

export interface AgreementPerson {
    id?: number;
    name: string;
    document: string;
    sex: string;
    marital_status_label: string;
    address?: string;
    email: string;
    phone: string;
}

export interface AgreementSnapshot {
    concessionaire: AgreementPerson;
    additional_concessionaires?: AgreementPerson[];
    source_contract?: { id: number; number: string } | null;
    local: { code: string; area_m2: string; location: string };
    trade: { name: string };
    start_date: string;
    end_date?: string;
    signature_date: string;
}

export function AgreementSummary({ snapshot }: { snapshot: AgreementSnapshot }) {
    const details = [
        ['Cesionario principal', `${snapshot.concessionaire.name} · ${snapshot.concessionaire.document}`],
        ['Local y superficie', `${snapshot.local.code} · ${snapshot.local.area_m2} m² · ${snapshot.local.location}`],
        ['Rubro autorizado', snapshot.trade.name],
        ['Inicio de vigencia', formatDateShort(snapshot.start_date)],
        ['Firma prevista', formatDateShort(snapshot.signature_date)],
        ...(snapshot.end_date ? [['Fin de vigencia', formatDateShort(snapshot.end_date)]] : []),
        ['Referencia', snapshot.source_contract ? `Contrato ${snapshot.source_contract.number}` : 'Datos nuevos'],
    ];

    return (
        <dl className="flex flex-col gap-4 text-sm">
            {details.map(([label, value]) => (
                <div key={label} className="flex flex-col gap-1">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="font-medium break-words">{value}</dd>
                </div>
            ))}
            <div className="flex flex-col gap-1">
                <dt className="text-muted-foreground">Cesionarios adicionales</dt>
                <dd>
                    {snapshot.additional_concessionaires?.length ? (
                        <ul className="flex flex-col gap-2">
                            {snapshot.additional_concessionaires.map((person) => (
                                <li key={person.document}>
                                    {person.name} · {person.document}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        'Sin adicionales'
                    )}
                </dd>
            </div>
        </dl>
    );
}
