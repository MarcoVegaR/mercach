<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt->receipt_number }} - Recibo de pago</title>
    <meta name="author" content="{{ $market_name ?? 'Mercado' }}">
    <meta name="subject" content="Recibo de pago">
    <meta name="keywords" content="recibo,pago,MERCACH">
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        .header { position: relative; width: 100%; margin-bottom: 8px; padding-bottom: 4px; min-height: 90px; }
        .header-right { position: absolute; top: 0; right: 0; text-align: right; }
        .doc-title { font-size: 15px; font-weight: bold; text-align: center; padding-top: 20px; }
        .logo-right { height: 70px; width: auto; display: block; margin-left: auto; margin-bottom: 4px; }
        .receipt-no { font-size: 13px; font-weight: 800; color: #dc2626; display: block; letter-spacing: 0.3px; }
        .muted { color: #6b7280; }
        .grid { display: table; width: 100%; table-layout: fixed; }
        .row { display: table-row; }
        .col { display: table-cell; vertical-align: top; padding: 3px; }
        .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 3px; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 3px; text-align: left; }
        th { background: #f9fafb; }
        .right { text-align: right; }
        .small { font-size: 10px; }
        .qr { text-align: right; }
        .nums { font-variant-numeric: tabular-nums; }
        .letterhead { position: fixed; left: -15px; top: -15px; right: -10px; bottom: -5px; z-index: -1; opacity: .20; }
        .letterhead img { width: calc(100% + 20px); height: calc(100% + 20px); object-fit: fill; }
        .siggrid { display: table; width: 100%; table-layout: fixed; margin-top: 8px; }
        .sigcell { display: table-cell; vertical-align: bottom; padding: 3px 6px 0; }
        .sigline { border-top: 1px solid #9ca3af; margin-top: 32px; height: 0; }
        .siglabel { text-align: center; font-size: 9px; color: #6b7280; margin-top: 2px; }
        .footer-signatures { position: fixed; left: 18px; right: 18px; bottom: 30mm; z-index: 2; padding-right: 0; }
        .qr-fixed { position: fixed; left: 8mm; bottom: 6mm; z-index: 2; }
        .footer-info { position: fixed; left: 18px; right: 18px; bottom: 4mm; font-size: 7px; color: #9ca3af; line-height: 1.3; z-index: 1; }
        .hero { display: table; width: 100%; table-layout: fixed; margin: 2px 0 3px; }
        .chip { border: 1px solid #e5e7eb; border-radius: 4px; padding: 4px; text-align: center; }
        .chip .k { font-size: 10px; color: #6b7280; }
        .chip .v { font-size: 13px; font-weight: 700; }
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
        <div class="receipt-no">No. {{ (string) ($display_receipt_no ?? $receipt->receipt_number ?? '') }}</div>
    </div>
    <div class="doc-title">Recibo de pago</div>
</div>

@php($hasPartial = false)
@foreach (($items ?? []) as $__it)
    @php($hasPartial = $hasPartial || (($__it['balance_currency_minor'] ?? 0) > 0))
    @if ($hasPartial) @break @endif
@endforeach
@if ($hasPartial)
    <div class="box" style="border:2px solid #ef4444; background:#fee2e2; color:#991b1b; font-weight:800; text-align:center; margin:6px 0; padding:6px; letter-spacing:0.5px;">
        PAGO PARCIAL
    </div>
@endif

@php($methodNames = [
    'DEB' => 'Débito',
    'TRF' => 'Transferencia',
    'TRANSFER' => 'Transferencia',
    'PMOV' => 'Pago Móvil',
    'PM' => 'Pago Móvil',
    'EFE' => 'Efectivo',
    'EXO' => 'Exoneración',
])
@php($methodDisplay = $methodNames[$payment->method ?? ''] ?? ($payment->method ?? '—'))

<div class="grid" style="margin-top: 4px;">
    <div class="row">
        <div class="col">
            <div class="box">
                <div class="small muted">Sede</div>
                <div style="font-weight: 600;">{{ $market_name ?? 'Mercado' }}</div>
                <div class="small muted">{{ $market_address ?? '' }}</div>
            </div>
        </div>
        <div class="col">
            <div class="box">
                <div class="small muted">Titular</div>
                <div>{{ $debtor_label ?? data_get($payment, 'debtor_type').' #'.data_get($payment, 'debtor_id') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="box" style="margin-top: 4px;">
    <table>
        <tbody>
            <tr>
                <th>Método</th>
                <td>{{ $methodDisplay }}</td>
                <th>Referencia</th>
                <td>{{ (string) ($payment->reference ?? '—') }}</td>
            </tr>
            <tr>
                <th>Pagado el</th>
                <td>{{ $payment->paid_on ? \Illuminate\Support\Carbon::parse((string) $payment->paid_on)->format('d/m/Y H:i') : '—' }}</td>
                <th>Emitido el</th>
                <td>{{ optional($receipt->issued_at)->format('d/m/Y H:i') ?: '—' }}</td>
            </tr>
            <tr>
                <th>Destino del pago</th>
                <td>{{ $company_label ?? data_get($payment, 'company_bank_account_id') }}</td>
                <th>Banco origen</th>
                <td>{{ $origin_bank_name ?? '—' }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div style="margin-top: 10px;">
    <table>
        <caption class="small" style="font-weight: 700; margin-bottom: 3px;">Detalle de cargos</caption>
        <thead>
            <tr>
                <th style="width: 12%">Cargo</th>
                <th style="width: 24%">Concepto</th>
                <th style="width: 20%">Periodo</th>
                <th style="width: 14%">Moneda origen</th>
                <th class="right nums" style="width: 15%">Importe origen</th>
                <th class="right nums" style="width: 15%">Saldo (moneda)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $it)
                <tr @if (!empty($it['from_credit'])) style="background:#f0fdf4; font-style:italic; color:#166534;" @endif>
                    <td>#{{ $it['charge_id'] }}</td>
                    <td>{{ $it['concept'] ?? ($it['kind'] ?? '') }}</td>
                    <td>{{ $it['period'] ? \Illuminate\Support\Carbon::parse((string) $it['period'])->locale('es')->translatedFormat('F Y') : '' }}</td>
                    <td>{{ $it['currency'] }}</td>
                    <td class="right nums">@if (!is_null($it['charge_amount_minor'])) {{ number_format((int) $it['charge_amount_minor']/100, 2, ',', '.') }} @else — @endif</td>
                    <td class="right nums">@if (!is_null($it['balance_currency_minor'] ?? null)) {{ number_format((int) $it['balance_currency_minor']/100, 2, ',', '.') }} {{ $it['currency'] }} @else — @endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>

<div class="grid" style="margin-top: 4px;">
    <div class="row">
        <div class="col" style="width:50%">
            <div class="box">
                <div class="small muted">Tipo de cambio ({{ $rates_meta['USD']['source'] ?? $rates_meta['EUR']['source'] ?? 'BCV' }}) {{ $payment->paid_on ? \Illuminate\Support\Carbon::parse((string) $payment->paid_on)->format('d/m/Y') : '' }}</div>
                <div class="small">USD→Bs: {{ isset($rates['USD']) ? number_format($rates['USD'], 4, ',', '.') : '—' }}</div>
                <div class="small">EUR→Bs: {{ isset($rates['EUR']) ? number_format($rates['EUR'], 4, ',', '.') : '—' }}</div>
            </div>
        </div>
        <div class="col" style="width:50%">
            <div class="box">
                <table>
                    <tbody>
                        <tr>
                            <th>Total USD</th>
                            <td class="right nums">{{ isset($totals['by_ccy_minor']['USD']) ? number_format($totals['by_ccy_minor']['USD']/100, 2, ',', '.') : '—' }} USD</td>
                        </tr>
                        <tr>
                            <th>Total EUR</th>
                            <td class="right nums">{{ isset($totals['by_ccy_minor']['EUR']) ? number_format($totals['by_ccy_minor']['EUR']/100, 2, ',', '.') : '—' }} EUR</td>
                        </tr>
                        <tr>
                            <th>Total Bs</th>
                            <td class="right nums">{{ number_format(($totals['bs_minor'] ?? 0)/100, 2, ',', '.') }} Bs</td>
                        </tr>
                        @if (!empty($credit_remaining_minor) && $credit_remaining_minor > 0)
                        <tr style="color:#166534; font-style:italic;">
                            <th>Saldo a favor</th>
                            <td class="right nums">{{ number_format($credit_remaining_minor/100, 2, ',', '.') }} Bs</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@php($qrData = $qr_png_base64 ?? null)
@if (!$qrData && class_exists('SimpleSoftwareIO\\QrCode\\Facades\\QrCode'))
    @php($qrData = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->margin(4)->generate($verify_url)))
@endif

<div style="page-break-inside: avoid; margin-top: 12px;">
    <div class="siggrid">
        <div class="row">
            <div class="sigcell">
                <div class="sigline"></div>
                <div class="siglabel">Emisor</div>
            </div>
            <div class="sigcell">
                <div class="sigline"></div>
                <div class="siglabel">Receptor</div>
            </div>
            <div class="sigcell">
                <div class="sigline"></div>
                <div class="siglabel">Firma Gerente</div>
            </div>
            <div class="sigcell">
                <div class="sigline"></div>
                <div class="siglabel">Firma Directora</div>
            </div>
        </div>
    </div>

    <div style="display: table; width: 100%; margin-top: 8px;">
        <div style="display: table-cell; width: 22mm; vertical-align: top;">
            @if ($qrData)
                <img src="data:{{ $qr_mime ?? 'image/png' }};base64,{{ $qrData }}" alt="QR" style="width:18mm;height:18mm; display: block;" />
            @endif
        </div>
        <div style="display: table-cell; vertical-align: top; font-size: 7px; color: #9ca3af; line-height: 1.3;">
            <div>{{ (string) ($receipt->receipt_number ?? '') }} • Emitido: {{ optional($receipt->issued_at)->format('d/m/Y') }} • Este documento no es una factura. Conserva este recibo para verificación y auditoría.</div>
            {{-- <div style="word-break: break-all; margin-top: 2px;">Verificación: {{ $verify_url }}</div> --}}
        </div>
    </div>
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
