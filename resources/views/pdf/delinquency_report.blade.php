<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de morosidad</title>
    <style>
        @page { margin: 18px 16px 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 8px; line-height: 1.35; }
        .header { position: relative; min-height: 76px; border-bottom: 2px solid #b91c1c; margin-bottom: 10px; padding-bottom: 8px; }
        .brand { width: 68%; }
        .eyebrow { color: #b91c1c; font-size: 8px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; }
        .doc-title { font-size: 18px; font-weight: 800; margin-top: 4px; color: #0f172a; }
        .doc-subtitle { color: #475569; font-size: 9px; margin-top: 2px; }
        .header-right { position: absolute; top: 0; right: 0; text-align: right; width: 30%; }
        .logo-right { height: 54px; width: auto; display: block; margin-left: auto; margin-bottom: 4px; }
        .cutoff { display: inline-block; border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; border-radius: 12px; padding: 3px 8px; font-size: 8px; font-weight: 700; }
        .muted { color: #64748b; }
        .grid { display: table; width: 100%; table-layout: fixed; border-spacing: 0; }
        .row { display: table-row; }
        .col { display: table-cell; vertical-align: top; padding: 4px; }
        .panel { border: 1px solid #e2e8f0; border-radius: 8px; background: rgba(255,255,255,.92); padding: 8px; }
        .label { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .35px; }
        .value { font-size: 10px; font-weight: 700; color: #0f172a; }
        .summary { display: table; width: 100%; table-layout: fixed; margin-top: 8px; border-spacing: 4px 0; }
        .metric { display: table-cell; border-radius: 8px; padding: 8px 7px; border: 1px solid #e2e8f0; background: #ffffff; }
        .metric.primary { background: #fef2f2; border-color: #fecaca; }
        .metric.count { background: #eef2ff; border-color: #c7d2fe; }
        .metric.age { background: #fff7ed; border-color: #fdba74; }
        .metric.relief { background: #f0fdf4; border-color: #86efac; }
        .metric .k { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .metric .v { font-size: 14px; font-weight: 800; margin-top: 2px; }
        .metric.primary .v { color: #b91c1c; }
        .metric.count .v { color: #3730a3; }
        .metric.age .v { color: #c2410c; }
        .metric.relief .v { color: #15803d; }
        .metric .sub { color: #64748b; font-size: 7.5px; margin-top: 2px; }
        .note { margin-top: 8px; border-left: 3px solid #b91c1c; background: #f8fafc; padding: 7px 9px; color: #334155; border-radius: 0 6px 6px 0; }
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
        .badge { display: inline-block; border-radius: 10px; padding: 2px 6px; font-size: 7px; font-weight: 800; text-transform: uppercase; }
        .badge-overdue { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }
        .badge-current { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; }
        .badge-scope { color: #1d4ed8; background: #dbeafe; border: 1px solid #bfdbfe; }
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
<body @if (!empty($letterhead_base64)) class="has-letterhead" @endif>
@php
    $scope = (string) ($filters['scope'] ?? 'concessionaire');
    $debtType = (string) ($filters['debt_type'] ?? 'overdue');
    $isOverdue = $debtType === 'overdue';
    $scopeLabel = $scope === 'local' ? 'Por local' : 'Por cesionario';
    $debtTypeLabel = $isOverdue ? 'Deuda vencida' : 'Deuda por vencer';
    $title = 'Reporte de morosidad';
    $subtitle = $isOverdue
        ? 'Ranking de deudores por mayor antigüedad de deuda vencida pendiente.'
        : 'Ranking de deudores con deuda vigente ordenado por próximo vencimiento.';
    $totalDue = (int) data_get($totals, 'final_due_bs_minor', 0);
    $grossSelected = (int) data_get($totals, 'gross_selected_bs_minor', 0);
    $debtorsCount = (int) data_get($totals, 'debtors_count', 0);
    $chargesCount = (int) data_get($totals, 'charges_count', 0);
    $credits = (int) data_get($totals, 'credits_open_bs_minor', 0);
    $payments = (int) data_get($totals, 'payments_available_bs_minor', 0);
    $maxDays = (int) data_get($totals, 'max_days_overdue', 0);
    $criteriaText = $isOverdue
        ? 'Se incluyen cargos abiertos ISSUED/PARTIAL con vencimiento menor o igual a la fecha de generación. La prioridad se ordena por antigüedad máxima, cantidad de cargos y monto pendiente.'
        : 'Se incluyen cargos abiertos ISSUED/PARTIAL aún no vencidos. La prioridad se ordena por el vencimiento más cercano y luego por monto pendiente.';
@endphp

<div class="header">
    <div class="brand">
        <div class="eyebrow">Control de morosidad</div>
        <div class="doc-title">{{ $title }}</div>
        <div class="doc-subtitle">{{ $subtitle }}</div>
    </div>
    <div class="header-right">
        @if (!empty($logo_base64))
            <img class="logo-right" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo" />
        @endif
        <div class="cutoff">Generado: {{ (string) data_get($data, 'generated_at', '') }}</div>
    </div>
</div>

<div class="grid">
    <div class="row">
        <div class="col" style="width: 34%;">
            <div class="panel">
                <div class="label">Alcance</div>
                <div class="value"><span class="badge badge-scope">{{ $scopeLabel }}</span></div>
            </div>
        </div>
        <div class="col" style="width: 33%;">
            <div class="panel">
                <div class="label">Tipo de deuda</div>
                <div class="value"><span class="badge {{ $isOverdue ? 'badge-overdue' : 'badge-current' }}">{{ $debtTypeLabel }}</span></div>
            </div>
        </div>
        <div class="col" style="width: 33%;">
            <div class="panel">
                <div class="label">Fecha de corte</div>
                <div class="value">{{ (string) ($filters['cutoff_date'] ?? '') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="summary">
    <div class="metric primary">
        <div class="k">Deuda neta</div>
        <div class="v nums">{{ number_format($totalDue / 100, 2, ',', '.') }}</div>
        <div class="sub">Bolívares pendientes</div>
    </div>
    <div class="metric count">
        <div class="k">Deudores</div>
        <div class="v nums">{{ $debtorsCount }}</div>
        <div class="sub">{{ $chargesCount }} cargos abiertos</div>
    </div>
    <div class="metric age">
        <div class="k">{{ $isOverdue ? 'Mora máxima' : 'Deuda bruta' }}</div>
        <div class="v nums">{{ $isOverdue ? $maxDays : number_format($grossSelected / 100, 2, ',', '.') }}</div>
        <div class="sub">{{ $isOverdue ? 'Días vencidos' : 'Bs antes de saldos' }}</div>
    </div>
    <div class="metric relief">
        <div class="k">Saldos a favor</div>
        <div class="v nums">{{ number_format(($credits + $payments) / 100, 2, ',', '.') }}</div>
        <div class="sub">Créditos y pagos CONF sin aplicar</div>
    </div>
</div>

<div class="note">
    <strong>Criterio aplicado:</strong> {{ $criteriaText }} No se muestran detalles de cargos; el detalle operativo se consulta en el Estado de Cuenta.
</div>

<div class="section">
    <div class="section-title">Ranking de deudores @if (!empty($data['rows_truncated'])) <span class="muted">(primeros {{ (int) ($data['row_limit'] ?? 0) }})</span> @endif</div>
    <table>
        <thead>
            <tr>
                <th style="width: 4%">#</th>
                <th style="width: 19%">Deudor</th>
                <th style="width: 12%">Documento / Cesionario</th>
                <th style="width: 12%">Mercado</th>
                <th style="width: 12%">Locales</th>
                <th class="right nums" style="width: 7%">Cargos</th>
                <th class="right nums" style="width: 8%">Mora máx.</th>
                <th style="width: 8%">Fecha clave</th>
                <th class="right nums" style="width: 9%">Bruto Bs</th>
                <th class="right nums" style="width: 9%">Neto Bs</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($rows ?? []) as $index => $row)
                @php
                    $dateKey = $isOverdue ? (string) ($row['oldest_due_on'] ?? '') : (string) ($row['next_due_on'] ?? '');
                    $debtorName = \Illuminate\Support\Str::limit((string) ($row['debtor_name'] ?? ''), 70);
                    $debtorCode = \Illuminate\Support\Str::limit((string) ($row['debtor_code'] ?? ''), 32);
                    $secondary = \Illuminate\Support\Str::limit(
                        $scope === 'local'
                            ? (string) ($row['concessionaire_name'] ?? '')
                            : (string) ($row['debtor_document'] ?? ''),
                        70,
                    );
                    $marketNames = \Illuminate\Support\Str::limit((string) ($row['market_names'] ?? ''), 80);
                    $localCodes = \Illuminate\Support\Str::limit((string) ($row['local_codes'] ?? ''), 115);
                @endphp
                <tr>
                    <td class="center nums">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $debtorName }}</strong>
                        @if ($debtorCode !== '')
                            <div class="muted small">{{ $debtorCode }}</div>
                        @endif
                    </td>
                    <td>{{ $secondary !== '' ? $secondary : 'N/A' }}</td>
                    <td>{{ $marketNames }}</td>
                    <td>
                        {{ $localCodes }}
                        @if ((int) ($row['locals_count'] ?? 0) > 0)
                            <div class="muted small">{{ (int) ($row['locals_count'] ?? 0) }} local(es)</div>
                        @endif
                    </td>
                    <td class="right nums">{{ (int) ($row['selected_charge_count'] ?? 0) }}</td>
                    <td class="right nums">{{ (int) ($row['max_days_overdue'] ?? 0) }}</td>
                    <td>{{ $dateKey !== '' ? $dateKey : 'N/A' }}</td>
                    <td class="right nums">{{ number_format(((int) ($row['gross_selected_bs_minor'] ?? 0)) / 100, 2, ',', '.') }}</td>
                    <td class="right nums"><strong>{{ number_format(((int) ($row['final_due_bs_minor'] ?? 0)) / 100, 2, ',', '.') }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="muted center" style="padding: 10px;">Sin deudores para los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="footer-note">
    Los saldos a favor se descuentan en el mismo alcance del reporte. En la vista por local no se prorratean pagos ni créditos del cesionario.
</div>
</body>
</html>
