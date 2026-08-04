<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de cargos incobrables</title>
    <style>
        @page { margin: 18px 16px 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 8px; line-height: 1.35; }
        .header { position: relative; min-height: 70px; border-bottom: 2px solid #9f1239; margin-bottom: 10px; padding-bottom: 8px; }
        .brand { width: 74%; }
        .eyebrow { color: #9f1239; font-size: 8px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; }
        .doc-title { font-size: 18px; font-weight: 800; margin-top: 4px; color: #0f172a; }
        .doc-subtitle { color: #475569; font-size: 9px; margin-top: 2px; }
        .header-right { position: absolute; top: 0; right: 0; text-align: right; width: 22%; }
        .logo-right { height: 50px; width: auto; display: block; margin-left: auto; }
        .cutoff { color: #64748b; font-size: 8px; margin-top: 4px; }
        .muted { color: #64748b; }
        .summary { display: table; width: 100%; table-layout: fixed; margin-top: 8px; border-spacing: 4px 0; }
        .metric { display: table-cell; border-radius: 8px; padding: 8px 7px; border: 1px solid #e2e8f0; background: rgba(255,255,255,.92); }
        .metric.primary { background: #fff1f2; border-color: #fecdd3; }
        .metric.count { background: #eef2ff; border-color: #c7d2fe; }
        .metric.currency { background: #f8fafc; border-color: #cbd5e1; }
        .metric .k { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .metric .v { font-size: 14px; font-weight: 800; margin-top: 2px; }
        .metric.primary .v { color: #9f1239; }
        .metric.count .v { color: #3730a3; }
        .metric.currency .v { color: #0f172a; }
        .metric .sub { color: #64748b; font-size: 7.5px; margin-top: 2px; }
        .note { margin-top: 8px; border-left: 3px solid #9f1239; background: rgba(248,250,252,.94); padding: 7px 9px; color: #334155; border-radius: 0 6px 6px 0; }
        .section { margin-top: 12px; }
        .section-title { font-size: 11px; font-weight: 800; color: #0f172a; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; page-break-inside: auto; background: rgba(255,255,255,.94); }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th { background: #0f172a; color: #fff; border: 1px solid #0f172a; padding: 5px 4px; text-align: left; font-size: 7.5px; font-weight: 700; }
        td { border: 1px solid #e2e8f0; padding: 4px; text-align: left; vertical-align: top; word-break: break-word; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .small { font-size: 7.5px; }
        .nums { font-variant-numeric: tabular-nums; }
        .money-label { color: #64748b; font-size: 6.8px; font-weight: 700; text-transform: uppercase; }
        .footer-note { margin-top: 8px; color: #64748b; font-size: 7.5px; }
    </style>
@if (!empty($letterhead_base64))
    <style>
        @page {
            margin: 18px 16px 24px;
            background-image: url('data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 100% 100%;
        }
    </style>
@endif
</head>
<body>
@php
    $count = (int) data_get($totals, 'count', 0);
    $declaredBs = (int) data_get($totals, 'declared_outstanding_bs_minor', 0);
    $currentBs = (int) data_get($totals, 'current_outstanding_bs_minor', 0);
    $status = (string) data_get($filters, 'status', 'current');
    $statusLabel = $status === 'restored' ? 'Restaurados' : ($status === 'all' ? 'Todos' : 'Actuales');
    $markedFrom = (string) data_get($filters, 'marked_between.from', '');
    $markedTo = (string) data_get($filters, 'marked_between.to', '');
    $filterSummary = collect([
        'estado '.$statusLabel,
        $markedFrom !== '' ? 'desde '.$markedFrom : null,
        $markedTo !== '' ? 'hasta '.$markedTo : null,
    ])->filter()->implode(' · ');
    $currencyTotals = collect($totals_by_currency ?? [])->filter(fn ($row) => (int) ($row['current_outstanding_amount_minor'] ?? 0) > 0)->values();
@endphp

<div class="header">
    <div class="brand">
        <div class="eyebrow">Control de cobrabilidad</div>
        <div class="doc-title">Reporte de cargos incobrables</div>
        <div class="doc-subtitle">Saldos separados de la deuda cobrable, asociados al cesionario histórico del cargo.</div>
    </div>
    <div class="header-right">
        @if (!empty($logo_base64))
            <img class="logo-right" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo" />
        @endif
        <div class="cutoff">Generado {{ $generated_at }}</div>
    </div>
</div>

<div class="summary">
    <div class="metric primary">
        <div class="k">Saldo incobrable actual</div>
        <div class="v nums">Bs. {{ number_format($currentBs / 100, 2, ',', '.') }}</div>
        <div class="sub">Equivalente administrativo actual</div>
    </div>
    <div class="metric count">
        <div class="k">Saldo declarado Bs</div>
        <div class="v nums">Bs. {{ number_format($declaredBs / 100, 2, ',', '.') }}</div>
        <div class="sub">Snapshot histórico convertido</div>
    </div>
    <div class="metric">
        <div class="k">Eventos</div>
        <div class="v nums">{{ $count }}</div>
        <div class="sub">Filtro: {{ $statusLabel }}</div>
    </div>
    <div class="metric currency">
        <div class="k">Moneda original</div>
        <div class="v nums">
            @if ($currencyTotals->isEmpty())
                0,00
            @else
                @foreach ($currencyTotals as $currencyTotal)
                    <div>{{ (string) $currencyTotal['currency'] }} {{ number_format(((int) $currencyTotal['current_outstanding_amount_minor']) / 100, 2, ',', '.') }}</div>
                @endforeach
            @endif
        </div>
        <div class="sub">Saldo incobrable por moneda</div>
    </div>
</div>

<div class="note">
    <strong>Filtros:</strong> {{ $filterSummary }}.
    El cesionario mostrado corresponde al contrato asociado al cargo cuando fue generado; no se actualiza si el local recibe un contrato nuevo posteriormente.
</div>

<div class="section">
    <div class="section-title">Detalle de cargos incobrables</div>
    <table>
        <thead>
            <tr>
                <th style="width: 6%">Cargo</th>
                <th style="width: 10%">Fecha</th>
                <th style="width: 15%">Mercado / Local</th>
                <th style="width: 20%">Cesionario histórico</th>
                <th style="width: 10%">Concepto</th>
                <th class="right nums" style="width: 12%">Saldo incobrable</th>
                <th style="width: 27%">Motivo / Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($rows ?? []) as $row)
                <tr>
                    <td class="nums">#{{ (int) ($row['charge_id'] ?? 0) }}</td>
                    <td>{{ (string) ($row['marked_at'] ?? '') }}</td>
                    <td>
                        <strong>{{ (string) ($row['market_name'] ?? '') }}</strong>
                        <div class="muted small">{{ (string) ($row['local_code'] ?? '') }}</div>
                    </td>
                    <td>{{ (string) ($row['concessionaire_name'] ?? 'No determinado') }}</td>
                    <td>{{ (string) ($row['kind_label'] ?? $row['kind'] ?? 'Cargo') }}</td>
                    <td class="right nums">
                        <div class="money-label">{{ (string) ($row['currency'] ?? '') }}</div>
                        <strong>{{ number_format(((int) ($row['current_outstanding_amount_minor'] ?? 0)) / 100, 2, ',', '.') }}</strong>
                    </td>
                    <td>
                        {{ \Illuminate\Support\Str::limit((string) ($row['reason'] ?? ''), 110) }}
                        <div class="muted small">{{ (string) ($row['marked_by'] ?? '') }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted center" style="padding: 10px;">Sin cargos incobrables para los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="footer-note">
    Este reporte no modifica estados de pago; documenta decisiones administrativas de cobrabilidad y su historial funcional.
</div>
</body>
</html>
