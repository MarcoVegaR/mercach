<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de movimientos y saldo</title>
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
        .grid { display: table; width: 100%; table-layout: fixed; border-spacing: 0; }
        .row { display: table-row; }
        .col { display: table-cell; vertical-align: top; padding: 4px; }
        .panel { border: 1px solid #e2e8f0; border-radius: 8px; background: rgba(255,255,255,.92); padding: 8px; }
        .label { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .35px; }
        .value { font-size: 10px; font-weight: 700; color: #0f172a; }
        .muted { color: #64748b; }
        .small { font-size: 8px; }
        .nums { font-variant-numeric: tabular-nums; }
        .summary { display: table; width: 100%; table-layout: fixed; margin-top: 8px; border-spacing: 4px 0; }
        .metric { display: table-cell; border-radius: 8px; padding: 8px 7px; border: 1px solid #e2e8f0; background: #ffffff; }
        .metric.total { background: #ecfeff; border-color: #67e8f9; }
        .metric.pay { background: #f0fdf4; border-color: #86efac; }
        .metric.debt { background: #fff7ed; border-color: #fdba74; }
        .metric .k { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .metric .v { font-size: 14px; font-weight: 800; margin-top: 2px; }
        .metric.debt .v { color: #c2410c; }
        .metric.pay .v { color: #15803d; }
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
        .badge { display: inline-block; border-radius: 10px; padding: 2px 6px; font-size: 7px; font-weight: 800; text-transform: uppercase; }
        .badge-charge { color: #92400e; background: #fef3c7; border: 1px solid #fde68a; }
        .badge-payment { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; }
        .badge-credit { color: #1d4ed8; background: #dbeafe; border: 1px solid #bfdbfe; }
        .ref { font-size: 7.5px; white-space: nowrap; }
        .concept { color: #1e293b; }
        .positive { color: #166534; font-weight: 700; }
        .debt-cell { color: #c2410c; font-weight: 800; }
        .currency-table th { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
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
    $isConcessionaire = ($scope ?? '') === 'concessionaire';
    $debtorLabel = $isConcessionaire ? (string) ($header['full_name'] ?? '') : trim(((string) ($header['code'] ?? '')).' '.((string) ($header['name'] ?? '')));
    $doc = $isConcessionaire ? trim(((string) data_get($header, 'document.type_code', '')).' '.((string) data_get($header, 'document.number', ''))) : '';
    $summary = (array) data_get($data, 'summary', []);
    $movements = array_values((array) data_get($data, 'movements', []));
    $totalsByCurrency = (array) data_get($data, 'totals_by_currency', []);
    $codes = array_values((array) ($included_local_codes ?? []));
    $grouped = [];
    $paymentGroups = [];
    foreach ($movements as $index => $movement) {
        $type = (string) ($movement['type'] ?? '');
        if ($type !== 'Pago') {
            $grouped[] = array_merge($movement, ['_order' => $index, '_count' => 1]);
            continue;
        }
        $key = implode('|', [
            (string) ($movement['date'] ?? ''),
            (string) ($movement['reference'] ?? ''),
            (string) ($movement['description'] ?? ''),
        ]);
        if (!isset($paymentGroups[$key])) {
            $paymentGroups[$key] = array_merge($movement, ['_order' => $index, '_count' => 0]);
        }
        $paymentGroups[$key]['credit'] = (int) ($paymentGroups[$key]['credit'] ?? 0) + (int) ($movement['credit'] ?? 0);
        $paymentGroups[$key]['amount_minor'] = (int) ($paymentGroups[$key]['amount_minor'] ?? 0) + (int) ($movement['amount_minor'] ?? 0);
        $paymentGroups[$key]['_count'] = (int) $paymentGroups[$key]['_count'] + 1;
    }
    foreach ($paymentGroups as $movement) {
        if ((int) ($movement['_count'] ?? 0) > 1) {
            $movement['description'] = ((string) ($movement['description'] ?? 'Pago aplicado')).' ('.((int) $movement['_count']).' aplicaciones)';
        }
        $grouped[] = $movement;
    }
    usort($grouped, fn ($a, $b) => ((int) ($a['_order'] ?? 0)) <=> ((int) ($b['_order'] ?? 0)));
    $runningBalance = 0;
    foreach ($grouped as $idx => $movement) {
        $runningBalance += (int) ($movement['debit'] ?? 0) - (int) ($movement['credit'] ?? 0);
        $grouped[$idx]['balance'] = $runningBalance;
    }
@endphp

<div class="header">
    <div class="brand">
        <div class="eyebrow">Estado financiero del titular</div>
        <div class="doc-title">Estado de movimientos y saldo</div>
        <div class="doc-subtitle">Ledger histórico de cargos, pagos aplicados, créditos y saldo pendiente.</div>
    </div>
    <div class="header-right">
        @if (!empty($logo_base64))
            <img class="logo-right" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo" />
        @endif
        <div class="cutoff">Corte: {{ (string) ($at ?? '') }}</div>
    </div>
</div>

<div class="grid">
    <div class="row">
        <div class="col" style="width: 58%;">
            <div class="panel">
                <div class="label">Titular</div>
                <div class="value">{{ $debtorLabel !== '' ? $debtorLabel : ($scope_label ?? '').' #'.($scope_id ?? '') }}</div>
                @if ($doc !== '')
                    <div class="small muted">{{ $doc }}</div>
                @endif
            </div>
        </div>
        <div class="col" style="width: 42%;">
            <div class="panel">
                <div class="label">Locales incluidos</div>
                <div class="value">{{ empty($codes) ? 'Todos' : implode(', ', array_slice($codes, 0, 8)) }}</div>
                @if (!empty($codes) && count($codes) > 8)
                    <div class="small muted">+{{ count($codes) - 8 }} más</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="summary">
    <div class="metric total">
        <div class="k">Total facturado</div>
        <div class="v nums">{{ number_format(((int) ($summary['total_charges_bs'] ?? 0))/100, 2, ',', '.') }}</div>
    </div>
    <div class="metric pay">
        <div class="k">Pagos aplicados</div>
        <div class="v nums">{{ number_format(((int) ($summary['total_payments_bs'] ?? 0))/100, 2, ',', '.') }}</div>
    </div>
    <div class="metric">
        <div class="k">Créditos aplicados</div>
        <div class="v nums">{{ number_format(((int) ($summary['total_credits_bs'] ?? 0))/100, 2, ',', '.') }}</div>
    </div>
    <div class="metric debt">
        <div class="k">Saldo pendiente</div>
        <div class="v nums">{{ number_format(((int) ($summary['final_balance_bs'] ?? 0))/100, 2, ',', '.') }}</div>
    </div>
</div>

<div class="note">
    Este documento muestra movimientos históricos para trazabilidad. Los cargos compensados por pagos o créditos pueden aparecer en el detalle, pero no representan deuda adicional. El saldo pendiente al corte es el monto exigible mostrado en el resumen.
</div>

@if (!empty($totalsByCurrency))
<div class="section">
    <div class="section-title">Resumen por moneda de origen</div>
    <table class="currency-table">
        <thead>
            <tr>
                <th>Moneda</th>
                <th class="right nums">Cargos históricos</th>
                <th class="right nums">Saldo pendiente</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($totalsByCurrency as $currency => $row)
                <tr>
                    <td><strong>{{ (string) $currency }}</strong></td>
                    <td class="right nums">{{ number_format(((int) ($row['charges_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                    <td class="right nums debt-cell">{{ number_format(((int) ($row['outstanding_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="section">
    <div class="section-title">Detalle de movimientos</div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 7%;">Tipo</th>
                <th style="width: 13%;">Referencia</th>
                <th style="width: 29%;">Concepto</th>
                <th class="center" style="width: 6%;">Mon.</th>
                <th class="right nums" style="width: 10%;">Importe</th>
                <th class="right nums" style="width: 9%;">Debe</th>
                <th class="right nums" style="width: 9%;">Haber</th>
                <th class="right nums" style="width: 9%;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($grouped as $movement)
                @php($type = (string) ($movement['type'] ?? ''))
                <tr>
                    <td>{{ !empty($movement['date']) ? \Illuminate\Support\Carbon::parse((string) $movement['date'])->format('d/m/Y') : '—' }}</td>
                    <td>
                        <span class="badge {{ $type === 'Pago' ? 'badge-payment' : ($type === 'Crédito' ? 'badge-credit' : 'badge-charge') }}">{{ $type }}</span>
                    </td>
                    <td class="ref">{{ (string) ($movement['reference'] ?? '—') }}</td>
                    <td class="concept">{{ (string) ($movement['description'] ?? '') }}</td>
                    <td class="center">{{ (string) ($movement['currency'] ?? 'VES') }}</td>
                    <td class="right nums">{{ number_format(((int) ($movement['amount_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ ((int) ($movement['debit'] ?? 0)) > 0 ? number_format(((int) ($movement['debit'] ?? 0))/100, 2, ',', '.') : '—' }}</td>
                    <td class="right nums positive">{{ ((int) ($movement['credit'] ?? 0)) > 0 ? number_format(((int) ($movement['credit'] ?? 0))/100, 2, ',', '.') : '—' }}</td>
                    <td class="right nums debt-cell">{{ number_format(((int) ($movement['balance'] ?? 0))/100, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="small muted">No hay movimientos para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" class="right">Totales</th>
                <th class="right nums">{{ number_format(((int) ($summary['total_charges_bs'] ?? 0))/100, 2, ',', '.') }}</th>
                <th class="right nums">{{ number_format((((int) ($summary['total_payments_bs'] ?? 0)) + ((int) ($summary['total_credits_bs'] ?? 0)))/100, 2, ',', '.') }}</th>
                <th class="right nums">{{ number_format(((int) ($summary['final_balance_bs'] ?? 0))/100, 2, ',', '.') }}</th>
            </tr>
        </tfoot>
    </table>
    <div class="footer-note">Los pagos con múltiples aplicaciones se agrupan por fecha, recibo/referencia y concepto para mejorar la lectura del documento.</div>
</div>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
    $size = 8;
    $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
    $width = $fontMetrics->get_text_width($text, $font, $size);
    $x = $pdf->get_width() - $width - 24;
    $y = $pdf->get_height() - 22;
    $pdf->page_text($x, $y, $text, $font, $size, [71,85,105]);
}
</script>
</body>
</html>
