<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $receipt->receipt_number }} - Recibo de pago • Gastos comunes</title>
    <meta name="author" content="{{ $market_name ?? 'Mercado' }}">
    <meta name="subject" content="Recibo de pago">
    <meta name="keywords" content="recibo,pago,gastos comunes,MERCACH">
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
        .footer-signatures { position: fixed; left: 18px; right: 18px; bottom: 30mm; z-index: 2; }
        .qr-fixed { position: fixed; left: 8mm; bottom: 6mm; z-index: 2; }
        .footer-info { position: fixed; left: 18px; right: 18px; bottom: 4mm; font-size: 7px; color: #9ca3af; line-height: 1.3; z-index: 1; }
        .qr-caption { position: fixed; left: 8mm; bottom: 4mm; width: 22mm; font-size: 6px; color: #9ca3af; line-height: 1.2; z-index: 2; word-break: break-word; text-align: center; }
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
    <div class="doc-title">Recibo de pago • Gastos comunes</div>
</div>

@php($methodNames = ['DEB' => 'Débito', 'TRA' => 'Transferencia', 'PM' => 'Pago Móvil', 'EFE' => 'Efectivo'])
@php($methodDisplay = $methodNames[$payment->method ?? ''] ?? ($payment->method ?? '—'))

<div class="grid">
    <div class="row">
        <div class="col">
            <div class="box">
                <div style="font-weight:700">{{ $market_name ?? 'Mercado' }}</div>
                <div class="small muted">{{ $market_address ?? '' }}</div>
            </div>
        </div>
        <div class="col">
            <div class="box">
                <div class="small muted">Deudor</div>
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

<div class="box" style="margin-top: 6px;">
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

<div style="margin-top: 10px;">
    <table>
        <caption class="small" style="font-weight: 700; margin-bottom: 3px;">Detalle del cargo</caption>
        <thead>
        <tr>
            <th scope="col" style="width: 14%">Cargo</th>
            <th scope="col" style="width: 16%">Concepto</th>
            <th scope="col" style="width: 16%">Periodo</th>
            <th scope="col" style="width: 12%">Moneda origen</th>
            <th scope="col" class="right nums" style="width: 14%">Importe origen</th>
            <th scope="col" class="right nums" style="width: 14%">Importe en VES</th>
            <th scope="col" class="right nums" style="width: 14%">Pagado</th>
            <th scope="col" class="right nums" style="width: 14%">Saldo ({{ $charge['currency'] }})</th>
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
            <td class="right nums">{{ number_format(($balance['currency_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
        </tr>
        </tbody>
    </table>
</div>

<div class="grid" style="margin-top: 10px;">
    <div class="row">
        <div class="col">
            <div class="box">
                <div class="small" style="font-weight:600">Detalle del período</div>
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Categoría</th>
                        <th scope="col" class="right nums">Monto (USD)</th>
                        <th scope="col" class="right nums">Monto (Bs)</th>
                        <th scope="col">Documento</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($gc['items'] as $it)
                        <tr>
                            <td>{{ $it['type'] }}</td>
                            <td class="right nums">{{ number_format(($it['amount_usd_minor'] ?? 0)/100, 2, ',', '.') }}</td>
                            <td class="right nums">{{ number_format((($it['amount_bs_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                            <td>{{ $it['invoice'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="small" style="margin-top:6px">Total período USD: <strong>{{ number_format(($gc['totals']['usd_minor'] ?? 0)/100, 2, ',', '.') }}</strong></div>
                <div class="small">Total período Bs: <strong>{{ number_format(($gc['totals']['bs_minor'] ?? 0)/100, 2, ',', '.') }}</strong></div>
            </div>
        </div>
        <div class="col">
            <div class="box">
                <div class="small" style="font-weight:600">Cálculo del cargo (prorrateo)</div>
                <table>
                    <tbody>
                    <tr>
                        <th>Área del local (m²)</th>
                        <td class="right">{{ number_format((float) ($gc['area_local'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Área total (m²)</th>
                        <td class="right">{{ number_format((float) ($gc['area_total'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Coeficiente</th>
                        <td class="right">{{ !is_null($gc['coef']) ? number_format((float) $gc['coef'] * 100, 4, ',', '.') . '%' : '—' }}</td>
                    </tr>
                    </tbody>
                </table>
                <table style="margin-top:6px">
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
                    <tr style="font-weight: 700; background: #fef3c7;">
                        <th>Saldo pendiente ({{ $charge['currency'] }})</th>
                        <td class="right nums">{{ number_format(($balance['currency_minor'] ?? 0)/100, 2, ',', '.') }} {{ $charge['currency'] }}</td>
                    </tr>
                    </tbody>
                </table>
                @endif
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
