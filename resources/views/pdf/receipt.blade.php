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
        .sigline { border-top: 1px solid #9ca3af; margin-top: 24px; height: 0; }
        .siglabel { text-align: center; font-size: 9px; color: #6b7280; margin-top: 2px; }
        .qr-section { display: table; width: 100%; margin-top: 8px; }
        .qr-left { display: table-cell; width: 70%; vertical-align: bottom; }
        .qr-right { display: table-cell; width: 30%; text-align: right; vertical-align: bottom; }
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

@php($methodNames = ['DEB' => 'Débito', 'TRA' => 'Transferencia', 'PM' => 'Pago Móvil', 'EFE' => 'Efectivo'])
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
                <div class="small muted">Deudor</div>
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
                <th>Destino del pago</th>
                <td>{{ $company_label ?? data_get($payment, 'company_bank_account_id') }}</td>
            </tr>
            <tr>
                <th>Banco origen</th>
                <td>{{ data_get($payment, 'origin_bank_name') ?? data_get($payment, 'origin_bank_id') ?? '—' }}</td>
                <th>Monto total (VES)</th>
                <td class="right nums">{{ number_format(((int) ($payment->amount_bs_minor ?? 0))/100, 2, ',', '.') }} VES</td>
            </tr>
        </tbody>
    </table>
</div>

<div style="margin-top: 10px;">
    <table>
        <thead>
            <tr>
                <th style="width: 10%">Cargo</th>
                <th style="width: 18%">Periodo</th>
                <th style="width: 18%">Tipo</th>
                <th style="width: 10%">Moneda</th>
                <th class="right" style="width: 22%">Aplicado (moneda)</th>
                <th class="right" style="width: 22%">Aplicado (VES)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $it)
                <tr>
                    <td>#{{ $it['charge_id'] }}</td>
                    <td>{{ $it['period'] }}</td>
                    <td>{{ $it['kind'] }}</td>
                    <td>{{ $it['currency'] }}</td>
                    <td class="right nums">
                        @if (!is_null($it['applied_currency_minor']))
                            {{ number_format($it['applied_currency_minor']/100, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="right nums">{{ number_format($it['applied_bs_minor']/100, 2, ',', '.') }}</td>
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
                <div class="small">USD→VES: {{ isset($rates['USD']) ? number_format($rates['USD'], 4, ',', '.') : '—' }}</div>
                <div class="small">EUR→VES: {{ isset($rates['EUR']) ? number_format($rates['EUR'], 4, ',', '.') : '—' }}</div>
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
                            <th>Total VES</th>
                            <td class="right nums">{{ number_format(($totals['bs_minor'] ?? 0)/100, 2, ',', '.') }} VES</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div style="position: relative; margin-top: 40px; min-height: 40mm; page-break-inside: avoid;">
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
    
    @php($qrData = $qr_png_base64 ?? null)
    @if (!$qrData && class_exists('SimpleSoftwareIO\\QrCode\\Facades\\QrCode'))
        @php($qrData = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->margin(4)->generate($verify_url)))
    @endif
    @if ($qrData)
        <div style="position: absolute; bottom: 8mm; right: 0;">
            <img src="data:{{ $qr_mime ?? 'image/png' }};base64,{{ $qrData }}" alt="QR" style="width:14mm;height:14mm; display: block;" />
        </div>
    @endif
    
    <div style="position: absolute; bottom: 0; left: 0; right: 0; font-size: 7px; color: #9ca3af; line-height: 1.3;">
        <div>{{ (string) ($receipt->receipt_number ?? '') }} • Emitido: {{ optional($receipt->issued_at)->format('d/m/Y H:i') }} • Este documento no es una factura. Conserva este recibo para verificación y auditoría.</div>
        <div style="word-break: break-all; margin-top: 2px;">Verificación: {{ $verify_url }}</div>
    </div>
</div>
</body>
</html>
