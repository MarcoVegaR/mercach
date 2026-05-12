<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Balance</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        .header { position: relative; width: 100%; margin-bottom: 8px; padding-bottom: 4px; min-height: 90px; }
        .header-right { position: absolute; top: 0; right: 0; text-align: right; }
        .doc-title { font-size: 15px; font-weight: bold; text-align: center; padding-top: 20px; }
        .logo-right { height: 70px; width: auto; display: block; margin-left: auto; margin-bottom: 4px; }
        .doc-no { font-size: 12px; font-weight: 800; color: #0f172a; display: block; letter-spacing: 0.3px; }
        .muted { color: #6b7280; }
        .grid { display: table; width: 100%; table-layout: fixed; }
        .row { display: table-row; }
        .col { display: table-cell; vertical-align: top; padding: 3px; }
        .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 3px; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 3px; text-align: left; }
        th { background: #f9fafb; }
        .right { text-align: right; }
        .small { font-size: 9px; }
        .nums { font-variant-numeric: tabular-nums; }
        .letterhead { position: fixed; left: -15px; top: -15px; right: -10px; bottom: -5px; z-index: -1; opacity: .20; }
        .letterhead img { width: calc(100% + 20px); height: calc(100% + 20px); object-fit: fill; }
        .hero { display: table; width: 100%; table-layout: fixed; margin: 2px 0 3px; }
        .chip { border: 1px solid #e5e7eb; border-radius: 4px; padding: 4px; text-align: center; }
        .chip .k { font-size: 10px; color: #6b7280; }
        .chip .v { font-size: 13px; font-weight: 700; }
        .section-title { font-size: 12px; font-weight: 700; margin: 10px 0 4px; }
    </style>
@if (!empty($letterhead_base64))
    <style>
        @page {
            margin: 18px;
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

<div class="header">
    <div class="header-right">
        @if (!empty($logo_base64))
            <img class="logo-right" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo" />
        @endif
        <div class="doc-no">Corte: {{ (string) ($at ?? '') }}</div>
    </div>
    <div class="doc-title">Balance</div>
</div>

@php($isConcessionaire = ($scope ?? '') === 'concessionaire')
@php($debtorLabel = $isConcessionaire ? (string) ($header['full_name'] ?? '') : trim(((string) ($header['code'] ?? '')).' '.((string) ($header['name'] ?? ''))))
@php($doc = $isConcessionaire ? trim(((string) data_get($header, 'document.type_code', '')).' '.((string) data_get($header, 'document.number', ''))) : '')
@php($summary = (array) data_get($data, 'summary', []))
@php($movements = (array) data_get($data, 'movements', []))
@php($totalsByCurrency = (array) data_get($data, 'totals_by_currency', []))

<div class="grid" style="margin-top: 4px;">
    <div class="row">
        <div class="col">
            <div class="box">
                <div class="small muted">Titular</div>
                <div style="font-weight: 600;">{{ $debtorLabel !== '' ? $debtorLabel : ($scope_label ?? '').' #'.($scope_id ?? '') }}</div>
                @if ($doc !== '')
                    <div class="small muted">{{ $doc }}</div>
                @endif
            </div>
        </div>
        <div class="col">
            <div class="box">
                <div class="small muted">Locales incluidos</div>
                @php($codes = $included_local_codes ?? [])
                <div style="font-weight: 600;">{{ empty($codes) ? 'Todos' : implode(', ', array_slice($codes, 0, 8)) }}</div>
                @if (!empty($codes) && count($codes) > 8)
                    <div class="small muted">+{{ count($codes) - 8 }} más</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="hero" style="margin-top: 6px;">
    <div class="row">
        <div class="col">
            <div class="chip">
                <div class="k">Cargos (Bs)</div>
                <div class="v nums">{{ number_format(((int) ($summary['total_charges_bs'] ?? 0))/100, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col">
            <div class="chip">
                <div class="k">Pagos (Bs)</div>
                <div class="v nums">{{ number_format(((int) ($summary['total_payments_bs'] ?? 0))/100, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col">
            <div class="chip">
                <div class="k">Créditos (Bs)</div>
                <div class="v nums">{{ number_format(((int) ($summary['total_credits_bs'] ?? 0))/100, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col">
            <div class="chip">
                <div class="k">Deuda (Bs)</div>
                <div class="v nums">{{ number_format(((int) ($summary['final_balance_bs'] ?? 0))/100, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>
</div>

@if (!empty($totalsByCurrency))
<div class="box" style="margin-top: 6px;">
    <table>
        <thead>
            <tr>
                <th>Moneda</th>
                <th class="right nums">Cargos</th>
                <th class="right nums">Deuda</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($totalsByCurrency as $currency => $row)
                <tr>
                    <td>{{ (string) $currency }}</td>
                    <td class="right nums">{{ number_format(((int) ($row['charges_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ number_format(((int) ($row['outstanding_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div style="margin-top: 10px;">
    <div class="section-title">Detalle de movimientos</div>
    <table>
        <thead>
            <tr>
                <th style="width: 9%">Fecha</th>
                <th style="width: 8%">Tipo</th>
                <th style="width: 11%">Referencia</th>
                <th style="width: 27%">Concepto</th>
                <th style="width: 8%">Moneda</th>
                <th class="right nums" style="width: 11%">Importe</th>
                <th class="right nums" style="width: 13%">Debe (Bs)</th>
                <th class="right nums" style="width: 13%">Haber (Bs)</th>
                <th class="right nums" style="width: 13%">Saldo (Bs)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ !empty($movement['date']) ? \Illuminate\Support\Carbon::parse((string) $movement['date'])->format('d/m/Y') : '—' }}</td>
                    <td>{{ (string) ($movement['type'] ?? '') }}</td>
                    <td>{{ (string) ($movement['reference'] ?? '—') }}</td>
                    <td>{{ (string) ($movement['description'] ?? '') }}</td>
                    <td>{{ (string) ($movement['currency'] ?? 'VES') }}</td>
                    <td class="right nums">{{ number_format(((int) ($movement['amount_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ ((int) ($movement['debit'] ?? 0)) > 0 ? number_format(((int) ($movement['debit'] ?? 0))/100, 2, ',', '.') : '—' }}</td>
                    <td class="right nums">{{ ((int) ($movement['credit'] ?? 0)) > 0 ? number_format(((int) ($movement['credit'] ?? 0))/100, 2, ',', '.') : '—' }}</td>
                    <td class="right nums">{{ number_format(((int) ($movement['balance'] ?? 0))/100, 2, ',', '.') }}</td>
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
</div>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
    $size = 8;
    $text = "{PAGE_NUM} / {PAGE_COUNT}";
    $width = $fontMetrics->get_text_width($text, $font, $size);
    $x = $pdf->get_width() - $width - 24;
    $y = $pdf->get_height() - 24;
    $pdf->page_text($x, $y, $text, $font, $size, [0,0,0]);
}
</script>
</body>
</html>
