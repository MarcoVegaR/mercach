<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt->receipt_number }} - Recibo de pago • {{ $receipt_heading ?? 'Tasa por uso' }}</title>
    <meta name="author" content="{{ $market_name ?? 'Mercado' }}">
    <meta name="subject" content="Recibo de pago">
    <meta name="keywords" content="recibo,pago,tasa por uso,MERCACH">
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
        table { width: 100%; border-collapse: collapse; }
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
        .qr-section { display: table; width: 100%; margin-top: 8px; }
        .qr-left { display: table-cell; width: 70%; vertical-align: bottom; }
        .qr-right { display: table-cell; width: 30%; text-align: right; vertical-align: bottom; }
        .hero { display: table; width: 100%; table-layout: fixed; margin: 2px 0 3px; }
        .chip { border: 1px solid #e5e7eb; border-radius: 4px; padding: 4px; text-align: center; }
        .chip .k { font-size: 10px; color: #6b7280; }
        .chip .v { font-size: 13px; font-weight: 700; }
        .footer-signatures { position: fixed; left: 18px; right: 18px; bottom: 30mm; z-index: 2; padding-right: 0; }
        .qr-fixed { position: fixed; left: 8mm; bottom: 6mm; z-index: 2; }
        .footer-info { position: fixed; left: 18px; right: 18px; bottom: 4mm; font-size: 7px; color: #9ca3af; line-height: 1.3; z-index: 1; }
        
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
    <div class="doc-title">Recibo de pago • {{ $receipt_heading ?? 'Tasa por uso de bien público' }}</div>
</div>

@if (($balance['currency_minor'] ?? 0) > 0)
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

<div class="box" style="margin-top: 4px;">
    <table>
        <tbody>
        <tr>
            <th>Método</th>
            <td>{{ $methodDisplay }}</td>
            <th>Referencia</th>
            <td>{{ (string) ($payment->reference ?? '') }}</td>
        </tr>
        <tr>
            <th>Pagado el</th>
            <td>{{ $payment->paid_on ? \Illuminate\Support\Carbon::parse((string) $payment->paid_on)->format('d/m/Y') : '—' }}</td>
            <th>Destino del pago</th>
            <td>{{ $company_label ?? data_get($payment, 'company_bank_account_id') }}</td>
        </tr>
        </tbody>
    </table>
</div>

<div class="grid" style="margin-top: 4px;">
    <div class="row">
        <div class="col">
            <div class="box">
                <div class="brand">{{ $market_name ?? 'Mercado' }}</div>
                <div class="small muted">{{ $market_address ?? '' }}</div>
            </div>
        </div>
        <div class="col">
            <div class="box">
                <div class="small muted">Titular</div>
                <div style="font-weight:600">{{ $debtor_label ?? '—' }}</div>
                @if (!empty($debtor_doc_type ?? null) || !empty($debtor_doc_number ?? null))
                    <div class="small muted">{{ trim(($debtor_doc_type ?? '').' '.($debtor_doc_number ?? '')) }}</div>
                @elseif(!empty(data_get($payment,'debtor_document_type')) || !empty(data_get($payment,'debtor_document_number')))
                    <div class="small muted">{{ (string) (data_get($payment,'debtor_document_type') ?? '') }} {{ (string) (data_get($payment,'debtor_document_number') ?? '') }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="box">
                <div class="small muted">Local</div>
                <div>{{ $local_name ?? ($local_label ?? '—') }}</div>
            </div>
        </div>
    </div>
</div>

<div style="margin-top: 10px;">
    <table>
        <caption class="small" style="font-weight: 700; margin-bottom: 3px;">Detalle del cargo</caption>
        <thead>
        <tr>
            <th scope="col" style="width: 16%">Cargo</th>
            <th scope="col" style="width: 22%">Concepto</th>
            <th scope="col" style="width: 20%">Periodo</th>
            <th scope="col" style="width: 14%">Moneda origen</th>
            <th scope="col" class="right nums" style="width: 14%">Importe origen</th>
            <th scope="col" class="right nums" style="width: 14%">Saldo ({{ $charge['currency'] }})</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>#{{ $charge['id'] }}</td>
            <td>{{ $receipt_type ?? '—' }}</td>
            <td>{{ $charge['period'] ? \Illuminate\Support\Carbon::parse((string) $charge['period'])->locale('es')->translatedFormat('M Y') : '' }}</td>
            <td>{{ $charge['currency'] }}</td>
            <td class="right nums">{{ number_format(($charge['amount_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
            <td class="right nums">{{ number_format(($balance['currency_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
        </tr>
        </tbody>
    </table>
</div>

<div class="grid" style="margin-top: 10px;">
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
                        <th>Cargo ({{ $charge['currency'] }})</th>
                        <td class="right nums">{{ number_format(($charge['amount_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
                    </tr>
                    <tr>
                        <th>Cargo (Bs)</th>
                        <td class="right nums">{{ !is_null($charge['bs_equiv_minor'] ?? null) ? number_format(($charge['bs_equiv_minor'] ?? 0)/100, 2, ',', '.') : '—' }} Bs</td>
                    </tr>
                    <tr>
                        <th>Aplicado ({{ $charge['currency'] }})</th>
                        <td class="right nums">@if (!is_null($applied['currency_minor'] ?? null)) {{ number_format(($applied['currency_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }} @else — @endif</td>
                    </tr>
                    <tr>
                        <th>Aplicado (Bs)</th>
                        <td class="right nums">{{ number_format(($applied['bs_minor'] ?? 0)/100, 2, ',', '.') }} Bs</td>
                    </tr>
                    <tr style="font-weight: 700; background: #fef3c7;">
                        <th>Saldo pendiente ({{ $charge['currency'] }})</th>
                        <td class="right nums">{{ number_format(($balance['currency_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
                    </tr>
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

<div class="footer-signatures">
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
</div>

@if ($qrData)
    <div class="qr-fixed">
        <img src="data:{{ $qr_mime ?? 'image/png' }};base64,{{ $qrData }}" alt="QR" style="width:18mm;height:18mm; display: block;" />
    </div>
    
@endif

<div class="footer-info">
    <div>{{ (string) ($receipt->receipt_number ?? '') }} • Emitido: {{ optional($receipt->issued_at)->format('d/m/Y') }} • Este documento no es una factura. Conserva este recibo para verificación y auditoría.</div>
    {{-- <div style="word-break: break-all; margin-top: 2px;">Verificación: {{ $verify_url }}</div> --}}
</div>
</body>
</html>
