<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte financiero de pagos</title>
    <style>
        @page { margin: 20px 18px 26px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 9px; line-height: 1.35; }
        .letterhead { position: fixed; left: -15px; top: -15px; right: -10px; bottom: -5px; z-index: -1; opacity: .16; }
        .letterhead img { width: calc(100% + 20px); height: calc(100% + 20px); object-fit: fill; }
        .header { position: relative; min-height: 82px; border-bottom: 2px solid #0f766e; margin-bottom: 10px; padding-bottom: 8px; }
        .brand { width: 68%; }
        .eyebrow { color: #0f766e; font-size: 8px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; }
        .doc-title { font-size: 18px; font-weight: 800; margin-top: 4px; color: #0f172a; }
        .doc-subtitle { color: #475569; font-size: 9px; margin-top: 2px; }
        .header-right { position: absolute; top: 0; right: 0; text-align: right; width: 30%; }
        .logo-right { height: 58px; width: auto; display: block; margin-left: auto; margin-bottom: 4px; }
        .cutoff { display: inline-block; border: 1px solid #99f6e4; background: #f0fdfa; color: #0f766e; border-radius: 12px; padding: 3px 8px; font-size: 8px; font-weight: 700; }
        .muted { color: #64748b; }
        .grid { display: table; width: 100%; table-layout: fixed; border-spacing: 0; }
        .row { display: table-row; }
        .col { display: table-cell; vertical-align: top; padding: 4px; }
        .panel { border: 1px solid #e2e8f0; border-radius: 8px; background: rgba(255,255,255,.92); padding: 8px; }
        .label { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .35px; }
        .value { font-size: 10px; font-weight: 700; color: #0f172a; }
        .summary { display: table; width: 100%; table-layout: fixed; margin-top: 8px; border-spacing: 4px 0; }
        .metric { display: table-cell; border-radius: 8px; padding: 8px 7px; border: 1px solid #e2e8f0; background: #ffffff; }
        .metric.primary { background: #ecfeff; border-color: #67e8f9; }
        .metric.count { background: #eef2ff; border-color: #c7d2fe; }
        .metric.avg { background: #f0fdf4; border-color: #86efac; }
        .metric.detail { background: #fff7ed; border-color: #fdba74; }
        .metric .k { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .metric .v { font-size: 14px; font-weight: 800; margin-top: 2px; }
        .metric.primary .v { color: #0f766e; }
        .metric.count .v { color: #3730a3; }
        .metric.avg .v { color: #15803d; }
        .metric.detail .v { color: #c2410c; }
        .metric .sub { color: #64748b; font-size: 7.5px; margin-top: 2px; }
        .note { margin-top: 8px; border-left: 3px solid #0f766e; background: #f8fafc; padding: 7px 9px; color: #334155; border-radius: 0 6px 6px 0; }
        .section { margin-top: 12px; }
        .section-title { font-size: 11px; font-weight: 800; color: #0f172a; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; background: rgba(255,255,255,.94); }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th { background: #0f172a; color: #fff; border: 1px solid #0f172a; padding: 5px 4px; text-align: left; font-size: 8px; font-weight: 700; }
        td { border: 1px solid #e2e8f0; padding: 4px; text-align: left; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .small { font-size: 8px; }
        .nums { font-variant-numeric: tabular-nums; }
        .breakdown th { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
        .footer-note { margin-top: 8px; color: #64748b; font-size: 7.5px; }
    </style>
@if (!empty($letterhead_base64))
    <style>
        @page {
            margin: 20px 18px 26px;
            background-image: url('data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 100% 100%;
        }
        body.has-letterhead {
            background-image: url('data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 100% 100%;
        }
    </style>
@endif
</head>
<body @if (!empty($letterhead_base64)) class="has-letterhead" @endif>
@if (!empty($letterhead_base64))
    <div class="letterhead">
        <img src="data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}" alt="" />
    </div>
@endif

@php
    $reportType = (string) ($filters['report_type'] ?? 'income');
    $isExonerations = $reportType === 'exonerations';
    $title = $isExonerations ? 'Reporte de exoneraciones realizadas' : 'Reporte de ingresos registrados';
    $eyebrow = $isExonerations ? 'Control de exoneraciones' : 'Control de ingresos';
    $subtitle = $isExonerations
        ? 'Resumen ejecutivo de pagos exonerados, criterios aplicados y detalle de autorizaciones.'
        : 'Resumen ejecutivo de pagos monetarios registrados, confirmados o conciliados.';
    $groupLabels = ['day' => 'Diario', 'week' => 'Semanal', 'month' => 'Mensual'];
    $groupBy = (string) ($filters['group_by'] ?? 'day');
    $totalCount = (int) data_get($totals, 'count', 0);
    $totalAmount = (int) data_get($totals, 'amount_bs_minor', 0);
    $averageAmount = (int) data_get($totals, 'average_bs_minor', 0);
    $statusBreakdown = array_values((array) data_get($totals, 'status_breakdown', []));
    $methodBreakdown = array_values((array) data_get($totals, 'method_breakdown', []));
    $detailsCount = count((array) ($details ?? []));
    $from = (string) ($filters['paid_from'] ?? '');
    $to = (string) ($filters['paid_to'] ?? '');
    $methodLabel = $isExonerations ? 'EXO' : ((string) ($filters['method'] ?? '') ?: 'Todos excepto EXO');
    $receiverBankLabel = (string) ($filters['bank_name'] ?? '') !== '' ? (string) $filters['bank_name'] : 'Todos';
    $primaryMetricLabel = $isExonerations ? 'Total exonerado' : 'Total ingresos';
    $criteriaText = $isExonerations
        ? 'Se incluyen pagos EXO con fecha de pago dentro del rango, no eliminados y no anulados. El monto representa exoneraciones registradas como compensación no monetaria.'
        : 'Se incluyen pagos registrados, confirmados o conciliados con fecha de pago dentro del rango. Se excluyen EXO, anulados y eliminados para reflejar ingresos monetarios.';
@endphp

<div class="header">
    <div class="brand">
        <div class="eyebrow">{{ $eyebrow }}</div>
        <div class="doc-title">{{ $title }}</div>
        <div class="doc-subtitle">{{ $subtitle }}</div>
    </div>
    <div class="header-right">
        @if (!empty($logo_base64))
            <img class="logo-right" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo" />
        @endif
        <div class="cutoff">Emitido: {{ (string) data_get($data, 'generated_at', '') }}</div>
    </div>
</div>

<div class="grid">
    <div class="row">
        <div class="col" style="width: 38%;">
            <div class="panel">
                <div class="label">Rango de pago</div>
                <div class="value">{{ $from }} → {{ $to }}</div>
            </div>
        </div>
        <div class="col" style="width: 20%;">
            <div class="panel">
                <div class="label">Agrupación</div>
                <div class="value">{{ $groupLabels[$groupBy] ?? $groupBy }}</div>
            </div>
        </div>
        <div class="col" style="width: 20%;">
            <div class="panel">
                <div class="label">Método</div>
                <div class="value">{{ $methodLabel }}</div>
            </div>
        </div>
        <div class="col" style="width: 22%;">
            <div class="panel">
                <div class="label">Banco receptor</div>
                <div class="value">{{ $receiverBankLabel }}</div>
            </div>
        </div>
    </div>
</div>

<div class="summary">
    <div class="metric primary">
        <div class="k">{{ $primaryMetricLabel }}</div>
        <div class="v nums">{{ number_format($totalAmount / 100, 2, ',', '.') }}</div>
        <div class="sub">Bolívares</div>
    </div>
    <div class="metric count">
        <div class="k">Registros</div>
        <div class="v nums">{{ $totalCount }}</div>
        <div class="sub">Pagos incluidos</div>
    </div>
    <div class="metric avg">
        <div class="k">Promedio</div>
        <div class="v nums">{{ number_format($averageAmount / 100, 2, ',', '.') }}</div>
        <div class="sub">Bs por registro</div>
    </div>
    <div class="metric detail">
        <div class="k">Detalle</div>
        <div class="v nums">{{ $detailsCount }}</div>
        <div class="sub">{{ !empty($data['details_truncated']) ? 'Primeros registros' : 'Registros listados' }}</div>
    </div>
</div>

<div class="note">
    <strong>Criterio aplicado:</strong> {{ $criteriaText }}
</div>

@if (!empty($statusBreakdown) || !empty($methodBreakdown))
<div class="section">
    <div class="section-title">Resumen por estado y método</div>
    <div class="grid">
        <div class="row">
            <div class="col" style="width: 50%;">
                <table class="breakdown">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th class="right nums">Registros</th>
                            <th class="right nums">Bs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statusBreakdown as $status)
                            <tr>
                                <td>{{ (string) ($status['name'] ?? ($status['code'] ?? 'N/A')) }}</td>
                                <td class="right nums">{{ (int) ($status['count'] ?? 0) }}</td>
                                <td class="right nums">{{ number_format(((int) ($status['amount_bs_minor'] ?? 0)) / 100, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="center muted">Sin estados para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="col" style="width: 50%;">
                <table class="breakdown">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th class="right nums">Registros</th>
                            <th class="right nums">Bs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($methodBreakdown as $method)
                            <tr>
                                <td>{{ (string) ($method['name'] ?? ($method['code'] ?? 'N/A')) }}</td>
                                <td class="right nums">{{ (int) ($method['count'] ?? 0) }}</td>
                                <td class="right nums">{{ number_format(((int) ($method['amount_bs_minor'] ?? 0)) / 100, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="center muted">Sin métodos para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

<div class="section">
    <div class="section-title">Evolución por período</div>
    <table>
        <thead>
            <tr>
                <th style="width: 34%">Periodo</th>
                <th class="right nums" style="width: 14%">Registros</th>
                <th class="right nums" style="width: 17%">Total Bs</th>
                <th class="right nums" style="width: 17%">Promedio Bs</th>
                <th class="right nums" style="width: 18%">Registrados / Confirmados / Conciliados</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($rows ?? []) as $row)
                <tr>
                    <td>{{ (string) ($row['period_label'] ?? '') }}</td>
                    <td class="right nums">{{ (int) ($row['count'] ?? 0) }}</td>
                    <td class="right nums">{{ number_format(((int) ($row['amount_bs_minor'] ?? 0)) / 100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ number_format(((int) ($row['average_bs_minor'] ?? 0)) / 100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ (int) ($row['registered_count'] ?? 0) }} / {{ (int) ($row['confirmed_count'] ?? 0) }} / {{ (int) ($row['applied_count'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted" style="text-align:center; padding: 8px;">Sin datos para los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Detalle de registros @if (!empty($data['details_truncated'])) <span class="muted">(primeros {{ (int) ($data['detail_limit'] ?? 0) }})</span> @endif</div>
    <table>
        <thead>
            <tr>
                <th style="width: 7%">Pago</th>
                <th style="width: 10%">Fecha</th>
                <th style="width: 10%">Estado</th>
                <th style="width: 10%">Método</th>
                <th style="width: 15%">Banco receptor</th>
                <th style="width: 16%">Deudor</th>
                <th style="width: 12%">Referencia</th>
                @if ($isExonerations)
                    <th style="width: 11%">Motivo</th>
                @endif
                <th class="right nums" style="width: 9%">Monto Bs</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($details ?? []) as $detail)
                <tr>
                    <td>#{{ (int) ($detail['id'] ?? 0) }}</td>
                    <td>{{ (string) ($detail['paid_on'] ?? '') }}</td>
                    <td>{{ (string) ($detail['status_name'] ?? ($detail['status_code'] ?? '')) }}</td>
                    <td>{{ (string) ($detail['method_name'] ?? ($detail['method_code'] ?? '')) }}</td>
                    <td>{{ (string) ($detail['receiver_bank_name'] ?? 'Sin banco receptor') }}</td>
                    <td>{{ (string) ($detail['debtor_name'] ?? '') }}</td>
                    <td>{{ (string) ($detail['reference'] ?? '') }}</td>
                    @if ($isExonerations)
                        <td>{{ (string) ($detail['exoneration_reason'] ?? '') }}</td>
                    @endif
                    <td class="right nums">{{ number_format(((int) ($detail['amount_bs_minor'] ?? 0)) / 100, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isExonerations ? 9 : 8 }}" class="muted" style="text-align:center; padding: 8px;">Sin detalle disponible.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
