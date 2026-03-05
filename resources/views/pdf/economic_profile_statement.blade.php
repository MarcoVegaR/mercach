<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de cuenta</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
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
        tfoot { display: table-row-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #e5e7eb; padding: 3px; text-align: left; }
        th { background: #f9fafb; }
        .right { text-align: right; }
        .small { font-size: 10px; }
        .nums { font-variant-numeric: tabular-nums; }
        .letterhead { position: fixed; left: -15px; top: -15px; right: -10px; bottom: -5px; z-index: -1; opacity: .20; }
        .letterhead img { width: calc(100% + 20px); height: calc(100% + 20px); object-fit: fill; }
        .hero { display: table; width: 100%; table-layout: fixed; margin: 2px 0 3px; }
        .chip { border: 1px solid #e5e7eb; border-radius: 4px; padding: 4px; text-align: center; }
        .chip .k { font-size: 10px; color: #6b7280; }
        .chip .v { font-size: 13px; font-weight: 700; }
        .section-title { font-size: 12px; font-weight: 700; margin: 10px 0 4px; }
        .subtable th { background: #f1f5f9; }
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
    <div class="doc-title">Estado de cuenta</div>
</div>

@php($isConcessionaire = ($scope ?? '') === 'concessionaire')
@php($debtorLabel = $isConcessionaire ? (string) ($header['full_name'] ?? '') : trim(((string) ($header['code'] ?? '')).' '.((string) ($header['name'] ?? ''))))
@php($doc = $isConcessionaire ? trim(((string) data_get($header, 'document.type_code', '')).' '.((string) data_get($header, 'document.number', ''))) : '')

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

@php($open = (int) ($summary_bs['open_bs_minor'] ?? 0))
@php($overdue = (int) ($summary_bs['overdue_bs_minor'] ?? 0))
@php($credits = (int) ($summary_bs['credits_open_bs_minor'] ?? 0))
@php($netDue = (int) ($summary_bs['net_due_after_credit_bs_minor'] ?? 0))
@php($eurOpenMinor = 0)
@php($usdOpenMinor = 0)
@foreach (($charges ?? []) as $c)
    @php($ccy = strtoupper((string) ($c['currency'] ?? 'VES')))
    @php($minor = (int) ($c['outstanding_minor'] ?? 0))
    @if ($ccy === 'EUR')
        @php($eurOpenMinor += $minor)
    @elseif ($ccy === 'USD')
        @php($usdOpenMinor += $minor)
    @endif
@endforeach

<div class="hero" style="margin-top: 6px;">
    <div class="row">
        <div class="col">
            <div class="chip">
                <div class="k">Total (Bs)</div>
                <div class="v nums">{{ number_format($open/100, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col">
            <div class="chip">
                <div class="k">Vencido (Bs)</div>
                <div class="v nums">{{ number_format($overdue/100, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col">
            <div class="chip">
                <div class="k">Créditos (Bs)</div>
                <div class="v nums">{{ number_format($credits/100, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="col">
            <div class="chip">
                <div class="k">Neto a pagar (Bs)</div>
                <div class="v nums">{{ number_format($netDue/100, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>
</div>

@php($totalCondoUsd = 0)
@php($totalRentEur = 0)
@php($totalRentUsd = 0)
@php($totalAdjEur = 0)
@foreach (($charges ?? []) as $c)
    @php($kind = strtoupper((string) ($c['kind'] ?? '')))
    @php($currency = strtoupper((string) ($c['currency'] ?? 'VES')))
    @php($minor = (int) ($c['outstanding_minor'] ?? 0))
    @if (str_contains($kind, 'CONDO') && $currency === 'USD')
        @php($totalCondoUsd += $minor)
    @elseif (str_contains($kind, 'RENT') && $currency === 'EUR')
        @php($totalRentEur += $minor)
    @elseif (str_contains($kind, 'RENT') && $currency === 'USD')
        @php($totalRentUsd += $minor)
    @elseif ($kind === 'ADJ' && $currency === 'EUR')
        @php($totalAdjEur += $minor)
    @endif
@endforeach

<div class="hero" style="margin-top: 6px;">
    <div class="row">
        @if ($totalCondoUsd > 0)
        <div class="col">
            <div class="chip">
                <div class="k">Condominio</div>
                <div class="v nums">$ {{ number_format($totalCondoUsd/100, 2, ',', '.') }}</div>
            </div>
        </div>
        @endif
        @if ($totalRentEur > 0)
        <div class="col">
            <div class="chip">
                <div class="k">Tasa de uso</div>
                <div class="v nums">€ {{ number_format($totalRentEur/100, 2, ',', '.') }}</div>
            </div>
        </div>
        @endif
        @if ($totalAdjEur > 0)
        <div class="col">
            <div class="chip">
                <div class="k">Gasto fijo Mant.</div>
                <div class="v nums">€ {{ number_format($totalAdjEur/100, 2, ',', '.') }}</div>
            </div>
        </div>
        @endif
        @if ($totalRentUsd > 0)
        <div class="col">
            <div class="chip">
                <div class="k">Alquiler fijo</div>
                <div class="v nums">$ {{ number_format($totalRentUsd/100, 2, ',', '.') }}</div>
            </div>
        </div>
        @endif
        @php($conceptCount = ($totalCondoUsd > 0 ? 1 : 0) + ($totalRentEur > 0 ? 1 : 0) + ($totalAdjEur > 0 ? 1 : 0) + ($totalRentUsd > 0 ? 1 : 0))
        @for ($i = $conceptCount; $i < 4; $i++)
        <div class="col"></div>
        @endfor
    </div>
</div>

@php($localsAgg = [])
@php($chargesByLocal = [])
@foreach (($charges ?? []) as $c)
    @php($lid = (int) ($c['local_id'] ?? 0))
    @if ($lid <= 0)
        @continue
    @endif
    @php($localCode = (string) ($c['local_code'] ?? ''))
    @php($localType = (string) ($c['local_type_name'] ?? ''))
    @php($label = trim($localType.' '.$localCode))
    @php($label = $label !== '' ? $label : (string) ($c['local_label'] ?? ('Local #'.$lid)))
    @if (!isset($localsAgg[$lid]))
        @php($localsAgg[$lid] = ['local_id' => $lid, 'label' => $label, 'condo_usd_minor' => 0, 'rent_eur_minor' => 0, 'rent_usd_minor' => 0, 'open_bs_minor' => 0, 'overdue_bs_minor' => 0])
    @endif
    @php($chargesByLocal[$lid][] = $c)
    @php($kind = strtoupper((string) ($c['kind'] ?? '')))
    @php($currency = strtoupper((string) ($c['currency'] ?? 'VES')))
    @php($minor = (int) ($c['outstanding_minor'] ?? 0))
    @php($bs = (int) ($c['outstanding_bs_minor'] ?? 0))
    @php($localsAgg[$lid]['open_bs_minor'] += $bs)
    @php($isOverdue = (string) ($c['due_on'] ?? '') < (string) ($at ?? ''))
    @if ($isOverdue)
        @php($localsAgg[$lid]['overdue_bs_minor'] += $bs)
    @endif
    @if (str_contains($kind, 'CONDO') && $currency === 'USD')
        @php($localsAgg[$lid]['condo_usd_minor'] += $minor)
    @elseif (str_contains($kind, 'RENT') && $currency === 'EUR')
        @php($localsAgg[$lid]['rent_eur_minor'] += $minor)
    @elseif (str_contains($kind, 'RENT') && $currency === 'USD')
        @php($localsAgg[$lid]['rent_usd_minor'] += $minor)
    @endif
@endforeach
@php($localsAgg = array_values($localsAgg))
@php(usort($localsAgg, fn ($a, $b) => strcmp((string) $a['label'], (string) $b['label'])))

<div class="box" style="margin-top: 6px;">
    <table>
        <caption class="small" style="font-weight: 700; margin-bottom: 3px;">Resumen por local</caption>
        <thead>
            <tr>
                <th style="width: 50%">Local</th>
                <th class="right nums" style="width: 25%">Deuda (Bs)</th>
                <th class="right nums" style="width: 25%">Vencido (Bs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($localsAgg ?? []) as $row)
                <tr>
                    <td>{{ (string) ($row['label'] ?? '') }}</td>
                    <td class="right nums">{{ number_format(((int) ($row['open_bs_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ number_format(((int) ($row['overdue_bs_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top: 10px;">
    <div class="section-title">Detalle de cargos pendientes</div>
    @foreach (($localsAgg ?? []) as $lr)
        @php($lid = (int) ($lr['local_id'] ?? 0))
        @php($list = $chargesByLocal[$lid] ?? [])
        @php(usort($list, fn ($a, $b) => strcmp((string) ($a['due_on'] ?? ''), (string) ($b['due_on'] ?? '')) ?: strcmp((string) ($a['kind'] ?? ''), (string) ($b['kind'] ?? ''))))
        <div style="page-break-inside: avoid;">
            <div class="box" style="margin-top: 6px;">
                <div style="font-weight: 700; margin-bottom: 4px;">{{ (string) ($lr['label'] ?? '') }}</div>
                <table class="subtable">
                    <thead>
                        <tr>
                            <th style="width: 10%">Cargo</th>
                            <th style="width: 22%">Concepto</th>
                            <th style="width: 18%">Periodo</th>
                            <th style="width: 12%">Vence</th>
                            <th style="width: 10%">Moneda</th>
                            <th class="right nums" style="width: 14%">Importe</th>
                            <th class="right nums" style="width: 14%">Saldo (Bs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($list as $c)
                            @php($kind = strtoupper((string) ($c['kind'] ?? '')))
                            @php($currency = strtoupper((string) ($c['currency'] ?? 'VES')))
                            @php($concept = str_contains($kind, 'CONDO') ? 'Condominio' : (($kind === 'RENT_EUR_M2' || ($currency === 'EUR' && str_contains($kind, 'RENT'))) ? 'Tasa de uso' : (($kind === 'RENT_EUR_FIXED' || ($currency === 'USD' && str_contains($kind, 'RENT'))) ? 'Alquiler fijo' : (($kind === 'FINE') ? 'Cargo por multa' : (($kind === 'ADJ') ? 'Gasto Fijo de Mantenimiento' : (($kind === 'CESION_DERECHOS') ? 'Cesión de derechos' : 'Cargo'))))))
                            @php($period = (string) ($c['period'] ?? ''))
                            @php($due = (string) ($c['due_on'] ?? ''))
                            @php($amountMinor = (int) ($c['outstanding_minor'] ?? 0))
                            @php($amountBsMinor = (int) ($c['outstanding_bs_minor'] ?? 0))
                            <tr>
                                <td>#{{ (int) ($c['charge_id'] ?? 0) }}</td>
                                <td>{{ $concept }}</td>
                                <td>{{ $period !== '' ? \Illuminate\Support\Carbon::parse($period)->locale('es')->translatedFormat('F Y') : '' }}</td>
                                <td>{{ $due !== '' ? \Illuminate\Support\Carbon::parse($due)->format('d/m/Y') : '' }}</td>
                                <td>{{ $currency }}</td>
                                <td class="right nums">{{ number_format($amountMinor/100, 2, ',', '.') }}</td>
                                <td class="right nums">{{ number_format($amountBsMinor/100, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
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
