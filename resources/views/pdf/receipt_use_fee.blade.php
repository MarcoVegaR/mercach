<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt->receipt_number }} - Recibo de pago • Tasa por uso</title>
    <meta name="author" content="{{ $market_name ?? 'Mercado' }}">
    <meta name="subject" content="Recibo de pago">
    <meta name="keywords" content="recibo,pago,tasa por uso,MERCACH">
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .doc-title { font-size: 15px; font-weight: bold; text-align: center; flex: 1; }
        .receipt-no { font-size: 18px; font-weight: 800; letter-spacing: 0.5px; background: #f3f4f6; padding: 4px 8px; border-radius: 4px; border: 2px solid #111827; }
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
        .letterhead { position: fixed; left: 0; top: 0; width: 100%; height: 100%; z-index: -1; opacity: .08; }
        .letterhead img { width: 100%; height: auto; }
        .siggrid { display: table; width: 100%; table-layout: fixed; margin-top: 8px; }
        .sigcell { display: table-cell; vertical-align: bottom; padding: 3px 6px 0; }
        .sigline { border-top: 1px solid #9ca3af; margin-top: 14px; height: 0; }
        .siglabel { text-align: center; font-size: 9px; color: #6b7280; margin-top: 2px; }
        .qr-section { display: table; width: 100%; margin-top: 8px; }
        .qr-left { display: table-cell; width: 70%; vertical-align: bottom; }
        .qr-right { display: table-cell; width: 30%; text-align: right; vertical-align: bottom; }
        .hero { display: table; width: 100%; table-layout: fixed; margin: 2px 0 3px; }
        .chip { border: 1px solid #e5e7eb; border-radius: 4px; padding: 4px; text-align: center; }
        .chip .k { font-size: 10px; color: #6b7280; }
        .chip .v { font-size: 13px; font-weight: 700; }
    </style>
</head>
<body>
@if (!empty($letterhead_base64))
    <div class="letterhead">
        <img src="data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}" alt="" />
    </div>
@endif
<div class="header">
    <div class="doc-title">Recibo de pago • Tasa por uso de bien público</div>
    <div class="receipt-no">{{ (string) ($display_receipt_no ?? $receipt->receipt_number ?? '') }}</div>
</div>

@php($appliedBs = (int) ($applied['bs_minor'] ?? 0))
@php($saldoBs = $balance['bs_minor'] ?? null)
@php($estado = (isset($balance['currency_minor']) && ($balance['currency_minor'] ?? 0) === 0) ? 'Pagado total' : 'Pagado parcial')
<div class="hero">
    <div class="row">
        <div class="col"><div class="chip"><div class="k">Total abonado</div><div class="v nums">{{ number_format($appliedBs/100, 2, ',', '.') }} VES</div></div></div>
        <div class="col"><div class="chip"><div class="k">Saldo pendiente</div><div class="v nums">@if(!is_null($saldoBs)) {{ number_format(((int)$saldoBs)/100, 2, ',', '.') }} VES @else — @endif</div></div></div>
        <div class="col"><div class="chip"><div class="k">Estado</div><div class="v">{{ $estado }}</div></div></div>
    </div>
</div>

<div class="box" style="margin-top: 4px;">
    <table>
        <tbody>
        <tr>
            <th>Método</th>
            <td>{{ (string) ($payment->method ?? '') }}</td>
            <th>Referencia</th>
            <td>{{ (string) ($payment->reference ?? '') }}</td>
        </tr>
        <tr>
            <th>Pagado el</th>
            <td>{{ $payment->paid_on ? \Illuminate\Support\Carbon::parse((string) $payment->paid_on)->setTimezone($rates_meta['tz'] ?? config('app.timezone'))->format('Y-m-d H:i') : '' }} {{ $rates_meta['tz'] ?? '' }}</td>
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
                <div class="small muted">Deudor</div>
                <div>{{ $debtor_label ?? data_get($payment, 'debtor_type').' #'.data_get($payment, 'debtor_id') }}</div>
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
        <div class="col">
            <div class="box">
                <div class="small muted">Documento</div>
                <div class="small">Tipo: {{ $receipt_type ?? 'RECIBO' }}</div>
                <div class="small">Fecha de elaboración: {{ $built_at ?? '' }}</div>
                <div class="small">Fecha del pago: {{ $payment->paid_on ? \Illuminate\Support\Carbon::parse((string) $payment->paid_on)->format('Y-m-d') : '' }}</div>
                <div class="small">Referencia: {{ (string) ($payment->reference ?? '') }}</div>
            </div>
        </div>
    </div>
</div>

<div style="margin-top: 6px;">
    <table>
        <caption class="small">Detalle del cargo</caption>
        <thead>
        <tr>
            <th scope="col" style="width: 14%">Cargo</th>
            <th scope="col" style="width: 16%">Concepto</th>
            <th scope="col" style="width: 16%">Periodo</th>
            <th scope="col" style="width: 12%">Moneda origen</th>
            <th scope="col" class="right nums" style="width: 14%">Importe origen</th>
            <th scope="col" class="right nums" style="width: 14%">Importe en VES</th>
            <th scope="col" class="right nums" style="width: 14%">Pagado</th>
            <th scope="col" class="right nums" style="width: 14%">Saldo</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>#{{ $charge['id'] }}</td>
            <td>{{ $receipt_type ?? '—' }}</td>
            <td>{{ $charge['period'] ? \Illuminate\Support\Carbon::parse((string) $charge['period'])->format('Y-m-d') : '' }}</td>
            <td>{{ $charge['currency'] }}</td>
            <td class="right nums">{{ number_format(($charge['amount_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
            <td class="right nums">{{ !is_null($charge['bs_equiv_minor'] ?? null) ? number_format(($charge['bs_equiv_minor'] ?? 0)/100, 2, ',', '.') : '—' }} VES</td>
            <td class="right nums">{{ number_format(($applied['bs_minor'] ?? 0)/100, 2, ',', '.') }} VES</td>
            <td class="right nums">@if(!is_null($balance['bs_minor'] ?? null)) {{ number_format(($balance['bs_minor'] ?? 0)/100, 2, ',', '.') }} VES @else — @endif</td>
        </tr>
        </tbody>
    </table>
</div>

<div class="grid" style="margin-top: 4px;">
    <div class="row">
        <div class="col" style="width:50%">
            <div class="box">
                <div class="small muted">Tipo de cambio ({{ $rates_meta['USD']['source'] ?? $rates_meta['EUR']['source'] ?? 'BCV' }}) {{ $payment->paid_on ? \Illuminate\Support\Carbon::parse((string) $payment->paid_on)->setTimezone($rates_meta['tz'] ?? config('app.timezone'))->format('Y-m-d H:i') : '' }} {{ $rates_meta['tz'] ?? '' }}</div>
                <div class="small">USD→VES: {{ isset($rates['USD']) ? number_format($rates['USD'], 4, ',', '.') : '—' }}</div>
                <div class="small">EUR→VES: {{ isset($rates['EUR']) ? number_format($rates['EUR'], 4, ',', '.') : '—' }}</div>
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
                        <th>Cargo (VES)</th>
                        <td class="right nums">{{ !is_null($charge['bs_equiv_minor'] ?? null) ? number_format(($charge['bs_equiv_minor'] ?? 0)/100, 2, ',', '.') : '—' }} VES</td>
                    </tr>
                    <tr>
                        <th>Aplicado ({{ $charge['currency'] }})</th>
                        <td class="right nums">@if (!is_null($applied['currency_minor'] ?? null)) {{ number_format(($applied['currency_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }} @else — @endif</td>
                    </tr>
                    <tr>
                        <th>Aplicado (VES)</th>
                        <td class="right nums">{{ number_format(($applied['bs_minor'] ?? 0)/100, 2, ',', '.') }} VES</td>
                    </tr>
                    <tr>
                        <th>Saldo ({{ $charge['currency'] }})</th>
                        <td class="right nums">{{ number_format(($balance['currency_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
                    </tr>
                    <tr>
                        <th>Saldo (VES)</th>
                        <td class="right nums">{{ !is_null($balance['bs_minor'] ?? null) ? number_format(($balance['bs_minor'] ?? 0)/100, 2, ',', '.') : '—' }} VES</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="siggrid" style="margin-top: 6px;">
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

<div class="qr-section">
    <div class="qr-left">
        <div class="small muted">{{ (string) ($receipt->receipt_number ?? '') }} • Emitido: {{ optional($receipt->issued_at)->format('Y-m-d H:i') }} • Este documento no es una factura. Conserva este recibo para verificación y auditoría.</div>
    </div>
    <div class="qr-right">
        @php($qrData = $qr_png_base64 ?? null)
        @if (!$qrData && class_exists('SimpleSoftwareIO\\QrCode\\Facades\\QrCode'))
            @php($qrData = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->margin(4)->generate($verify_url)))
        @endif
        @if ($qrData)
            <img src="data:{{ $qr_mime ?? 'image/png' }};base64,{{ $qrData }}" alt="QR" style="width:18mm;height:18mm" />
        @endif
    </div>
</div>

<div class="small muted" style="margin-top:3px;">Verificación: {{ $verify_url }}</div>
</body>
</html>
