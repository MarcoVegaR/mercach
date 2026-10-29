<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recaudación por concepto</title>
    <style>
        @page { margin: 20px 18px 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 9px; line-height: 1.35; }
        .letterhead { position: fixed; left: -15px; top: -15px; right: -10px; bottom: -5px; z-index: -1; opacity: .16; }
        .letterhead img { width: 100%; height: 100%; }
        .header { position: relative; min-height: 70px; border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 10px; }
        .eyebrow { color: #0f766e; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .8px; }
        h1 { font-size: 18px; margin: 4px 0; }
        h2 { font-size: 11px; margin: 12px 0 5px; }
        .header-right { position: absolute; top: 0; right: 0; text-align: right; }
        .logo { height: 52px; }
        .muted { color: #64748b; }
        .note { border-left: 3px solid #0f766e; background: #f8fafc; padding: 7px 9px; margin-top: 8px; }
        .warning { color: #92400e; background: #fffbeb; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #0f172a; color: white; padding: 5px; text-align: left; font-size: 8px; }
        td { border: 1px solid #e2e8f0; padding: 5px; vertical-align: top; overflow-wrap: break-word; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .right { text-align: right; white-space: nowrap; }
        .total td { background: #f0fdfa; color: #0f766e; font-weight: bold; }
        .small { font-size: 7.5px; }
        .footer { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 7px; color: #64748b; }
        .page:after { content: counter(page); }
    </style>
</head>
<body>
@if (!empty($letterhead['base64']))
    <div class="letterhead"><img src="data:{{ $letterhead['mime'] }};base64,{{ $letterhead['base64'] }}" alt="" /></div>
@endif
@php
    $money = fn ($minor) => $minor === null ? 'No disponible' : number_format($minor / 100, 2, ',', '.');
    $date = fn ($value) => \Carbon\Carbon::parse($value)->format('d/m/Y');
    $totals = $data['totals'];
    $filters = $data['filters'];
@endphp
<div class="header">
    <div class="eyebrow">Reporte financiero · Recaudación</div>
    <h1>Recaudación por concepto</h1>
    <div>Fecha del pago: {{ $date($filters['from']) }} al {{ $date($filters['to']) }}</div>
    <div class="muted">Conceptos: {{ $data['concepts_label'] }}</div>
    <div class="header-right">
        @if (!empty($logo['base64']))
            <img class="logo" src="data:{{ $logo['mime'] }};base64,{{ $logo['base64'] }}" alt="Logo" />
        @endif
        <div class="small">Generado: {{ $data['generated_at'] }}</div>
    </div>
</div>
@if ($totals['missing_rate_count'] > 0)
    <div class="note warning">Hay {{ $totals['missing_rate_count'] }} filas sin tasa USD histórica válida. El total USD no está disponible para evitar presentar una suma incompleta.</div>
@endif
<h2>Resumen por concepto</h2>
<table>
    <thead><tr><th>Concepto</th><th class="right">Pagos</th><th class="right">Aplicado Bs</th><th class="right">Equivalente USD al pago</th></tr></thead>
    <tbody>
    @forelse ($data['summary'] as $row)
        <tr><td>{{ $row['concept'] }}</td><td class="right">{{ $row['payment_count'] }}</td><td class="right">{{ $money($row['amount_bs_minor']) }}</td><td class="right">{{ $money($row['amount_usd_minor']) }}</td></tr>
    @empty
        <tr><td colspan="4">No se encontraron aplicaciones para el período y los conceptos seleccionados.</td></tr>
    @endforelse
    <tr class="total"><td>Total · pagos distintos</td><td class="right">{{ $totals['payment_count'] }}</td><td class="right">{{ $money($totals['amount_bs_minor']) }}</td><td class="right">{{ $money($totals['amount_usd_minor']) }}</td></tr>
    </tbody>
</table>
@if ($data['reconciliation'])
    <h2>Conciliación del período · Todos los conceptos</h2>
    <div class="note">
        Recibido ({{ $data['reconciliation']['payment_count'] }} pagos): <strong>Bs {{ $money($data['reconciliation']['received_bs_minor']) }}</strong>
        · Aplicado: <strong>Bs {{ $money($data['reconciliation']['applied_bs_minor']) }}</strong>
        · Sin atribuir a conceptos: <strong>Bs {{ $money($data['reconciliation']['unattributed_bs_minor']) }}</strong>.
        La diferencia puede incluir fondos sin aplicar y saldos a favor; no se suma a ningún concepto.
    </div>
@endif
<h2>Detalle de aplicaciones por pago y concepto</h2>
<table class="small">
    <thead><tr><th>Fecha</th><th>Pago / referencia</th><th>Pagador</th><th>Locales</th><th>Concepto</th><th class="right">Tasa Bs/USD</th><th class="right">Aplicado Bs</th><th class="right">Equiv. USD</th></tr></thead>
    <tbody>
    @forelse ($data['rows'] as $row)
        <tr>
            <td>{{ $date($row['paid_on']) }}</td><td>#{{ $row['payment_id'] }}<br>{{ $row['reference'] ?: 'Sin referencia' }}</td>
            <td>{{ $row['debtor_name'] }}</td><td>{{ $row['locals'] ?: '—' }}</td><td>{{ $row['concept'] }}</td>
            <td class="right">{{ $row['rate'] === null ? 'Sin tasa válida' : number_format($row['rate'], 2, ',', '.') }}</td>
            <td class="right">{{ $money($row['amount_bs_minor']) }}</td><td class="right">{{ $money($row['amount_usd_minor']) }}</td>
        </tr>
    @empty
        <tr><td colspan="8">Sin aplicaciones para los filtros seleccionados.</td></tr>
    @endforelse
    <tr class="total"><td colspan="6">Total aplicado</td><td class="right">{{ $money($totals['amount_bs_minor']) }}</td><td class="right">{{ $money($totals['amount_usd_minor']) }}</td></tr>
    </tbody>
</table>
<div class="footer">Recaudación por concepto · {{ $data['generated_at'] }} · Página <span class="page"></span></div>
</body>
</html>
