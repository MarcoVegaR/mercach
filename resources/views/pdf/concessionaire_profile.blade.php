<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del Cesionario</title>
    <style>
        @page { margin: 20px 18px 26px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 9px; line-height: 1.4; }
        .letterhead { position: fixed; left: -15px; top: -15px; right: -10px; bottom: -5px; z-index: -1; opacity: .16; }
        .letterhead img { width: calc(100% + 20px); height: calc(100% + 20px); object-fit: fill; }
        .header { position: relative; min-height: 82px; border-bottom: 2px solid #0f766e; margin-bottom: 12px; padding-bottom: 8px; }
        .brand { width: 68%; }
        .eyebrow { color: #0f766e; font-size: 8px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; }
        .title { font-size: 18px; font-weight: 800; margin-top: 4px; }
        .subtitle { color: #475569; margin-top: 2px; }
        .header-right { position: absolute; top: 0; right: 0; width: 30%; text-align: right; }
        .logo { height: 58px; width: auto; display: block; margin-left: auto; }
        .grid { display: table; width: 100%; table-layout: fixed; }
        .row { display: table-row; }
        .col { display: table-cell; vertical-align: top; padding: 4px; }
        .panel { border: 1px solid #e2e8f0; border-radius: 7px; background: rgba(255,255,255,.92); padding: 8px; }
        .photo { width: 92px; height: 108px; object-fit: cover; border-radius: 7px; border: 1px solid #cbd5e1; }
        .label { color: #64748b; font-size: 7.5px; font-weight: 700; text-transform: uppercase; }
        .value { font-size: 10px; font-weight: 700; margin-bottom: 7px; overflow-wrap: anywhere; word-break: break-word; }
        td { overflow-wrap: anywhere; word-break: break-word; }
        .section { margin-top: 12px; }
        .section-title { font-size: 11px; font-weight: 800; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.94); }
        th, td { border: 1px solid #e2e8f0; padding: 5px; text-align: left; }
        th { background: #f1f5f9; color: #334155; }
        .badge { display: inline-block; border-radius: 10px; padding: 2px 7px; font-size: 7px; font-weight: 800; }
        .current { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; }
        .due { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }
        .muted { color: #64748b; }
        .footer { margin-top: 14px; color: #64748b; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
@if (!empty($letterhead_base64))
    <div class="letterhead"><img src="data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}" alt=""></div>
@endif
@php
    $document = trim(((string) optional($concessionaire->documentType)->code).'-'.((string) $concessionaire->document_number), '-');
    $phone = trim(((string) optional($concessionaire->phoneAreaCode)->code).' '.((string) $concessionaire->phone_number));
    $lastLifeProof = $concessionaire->last_life_proof_at;
    $requiresCitation = !$lastLifeProof || $lastLifeProof->copy()->startOfDay()->lt(now()->startOfDay()->subYear());
    $operationalContracts = $concessionaire->contracts->filter(fn ($contract) => in_array(strtoupper((string) optional($contract->status)->code), ['VIG', 'VENC'], true));
    $locals = $operationalContracts->flatMap->locals->unique(fn ($local) => (string) $local->code)->sortBy('code')->values();
@endphp
<div class="header">
    <div class="brand">
        <div class="eyebrow">Registro administrativo</div>
        <div class="title">Ficha del Cesionario</div>
        <div class="subtitle">Información vigente registrada en el sistema</div>
    </div>
    <div class="header-right">
        @if (!empty($logo_base64))
            <img class="logo" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo">
        @endif
    </div>
</div>

<div class="grid">
    <div class="row">
        <div class="col" style="width: 22%;">
            <div class="panel" style="text-align:center;">
                @if (!empty($photo_base64))
                    <img class="photo" src="data:{{ $photo_mime }};base64,{{ $photo_base64 }}" alt="Foto">
                @else
                    <div class="muted" style="padding:44px 0;">Sin foto</div>
                @endif
            </div>
        </div>
        <div class="col" style="width: 48%;">
            <div class="panel">
                <div class="label">Nombre o razón social</div><div class="value">{{ $concessionaire->full_name }}</div>
                <div class="label">Documento</div><div class="value">{{ $document ?: '—' }}</div>
                <div class="label">Tipo de cesionario</div><div class="value">{{ optional($concessionaire->concessionaireType)->name ?? '—' }}</div>
                <div class="label">Dirección fiscal</div><div class="value">{{ $concessionaire->fiscal_address ?: '—' }}</div>
            </div>
        </div>
        <div class="col" style="width: 30%;">
            <div class="panel">
                <div class="label">Correo</div><div class="value">{{ $concessionaire->email }}</div>
                <div class="label">Teléfono</div><div class="value">{{ $phone ?: '—' }}</div>
                <div class="label">Estado</div><div class="value">{{ $concessionaire->is_active ? 'Activo' : 'Inactivo' }}</div>
                <div class="label">Usuario de portal</div><div class="value">{{ $concessionaire->users->isNotEmpty() ? 'Vinculado' : 'No vinculado' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Fe de vida</div>
    <table>
        <tr>
            <th>Última fe de vida</th>
            <td>{{ $lastLifeProof ? $lastLifeProof->format('d/m/Y') : 'Sin registro' }}</td>
            <th>Próxima citación</th>
            <td>{{ $lastLifeProof ? $lastLifeProof->copy()->addYear()->format('d/m/Y') : 'Pendiente' }}</td>
            <th>Estado</th>
            <td><span class="badge {{ $requiresCitation ? 'due' : 'current' }}">{{ $requiresCitation ? 'Requiere citación' : 'Vigente' }}</span></td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Contratos operativos y locales asociados</div>
    <table>
        <thead><tr><th>Contrato</th><th>Estado</th><th>Vigencia</th><th>Locales</th></tr></thead>
        <tbody>
        @forelse ($operationalContracts as $contract)
            <tr>
                <td>{{ $contract->number }}</td>
                <td>{{ optional($contract->status)->name ?? optional($contract->status)->code }}</td>
                <td>{{ optional($contract->start_date)->format('d/m/Y') }} - {{ optional($contract->end_date)->format('d/m/Y') ?: 'Sin fecha' }}</td>
                <td>{{ $contract->locals->sortBy('code')->map(fn ($local) => $local->code)->implode(', ') ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Sin contratos operativos asociados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Locales operativos</div>
    <div class="panel">{{ $locals->map(fn ($local) => (string) $local->code !== (string) $local->name && (string) $local->name !== '' ? $local->code.' - '.$local->name : $local->code)->implode(', ') ?: 'Sin locales operativos asociados.' }}</div>
</div>

<div class="footer">Ficha generada el {{ $printed_at->format('d/m/Y H:i') }}.</div>
</body>
</html>
