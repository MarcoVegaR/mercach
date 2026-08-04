<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de cuenta</title>
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
        .metric.debt { background: #fff7ed; border-color: #fdba74; }
        .metric.overdue { background: #fef2f2; border-color: #fecaca; }
        .metric.credit { background: #f0fdf4; border-color: #86efac; }
        .metric.final { background: #ecfeff; border-color: #67e8f9; }
        .metric.concept { background: #eef2ff; border-color: #c7d2fe; }
        .metric .k { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .metric .v { font-size: 14px; font-weight: 800; margin-top: 2px; }
        .metric .sub { color: #64748b; font-size: 7.5px; margin-top: 2px; }
        .metric.debt .v { color: #c2410c; }
        .metric.overdue .v { color: #b91c1c; }
        .metric.credit .v { color: #15803d; }
        .metric.final .v { color: #0f766e; }
        .metric.concept .v { color: #3730a3; }
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
        .positive { color: #166534; font-weight: 700; }
        .overdue-cell { color: #b91c1c; font-weight: 800; }
        .debt-cell { color: #c2410c; font-weight: 800; }
        .badge { display: inline-block; border-radius: 10px; padding: 2px 6px; font-size: 7px; font-weight: 800; text-transform: uppercase; }
        .badge-concept { color: #3730a3; background: #eef2ff; border: 1px solid #c7d2fe; }
        .badge-condo { color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; }
        .badge-rent { color: #0f766e; background: #ccfbf1; border: 1px solid #99f6e4; }
        .badge-fixed { color: #7c2d12; background: #ffedd5; border: 1px solid #fed7aa; }
        .badge-fine { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }
        .badge-currency { color: #334155; background: #f1f5f9; border: 1px solid #e2e8f0; }
        .badge-overdue { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }
        .badge-current { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; }
        .currency-table th, .subtable th { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
        .local-block { page-break-inside: avoid; margin-top: 7px; }
        .local-header { display: table; width: 100%; table-layout: fixed; margin-bottom: 7px; }
        .local-title { display: table-cell; width: 52%; vertical-align: middle; font-weight: 800; color: #0f172a; font-size: 10px; }
        .local-meta { display: table-cell; width: 48%; text-align: right; vertical-align: middle; }
        .local-metric { display: inline-block; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; padding: 3px 7px; margin-left: 3px; font-size: 7.5px; }
        .local-metric strong { font-size: 8px; color: #0f172a; }
        .local-metric.overdue strong { color: #b91c1c; }
        .charge-table .concept-cell { color: #1e293b; font-weight: 700; }
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

@php($isConcessionaire = ($scope ?? '') === 'concessionaire')
@php($debtorLabel = $isConcessionaire ? (string) ($header['full_name'] ?? '') : trim(((string) ($header['code'] ?? '')).' '.((string) ($header['name'] ?? ''))))
@php($doc = $isConcessionaire ? trim(((string) data_get($header, 'document.type_code', '')).' '.((string) data_get($header, 'document.number', ''))) : '')

<div class="header">
    <div class="brand">
        <div class="eyebrow">Estado financiero del titular</div>
        <div class="doc-title">Estado de cuenta</div>
        <div class="doc-subtitle">Cargos abiertos al corte, saldos a favor aplicables y equivalencia en Bs por concepto/local.</div>
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
                @php($codes = $included_local_codes ?? [])
                <div class="value">{{ empty($codes) ? 'Todos' : implode(', ', array_slice($codes, 0, 8)) }}</div>
                @if (!empty($codes) && count($codes) > 8)
                    <div class="small muted">+{{ count($codes) - 8 }} más</div>
                @endif
            </div>
        </div>
    </div>
</div>

@php($open = (int) ($summary_bs['gross_debt_bs_minor'] ?? $summary_bs['open_bs_minor'] ?? 0))
@php($overdue = (int) ($summary_bs['gross_debt_overdue_bs_minor'] ?? $summary_bs['overdue_bs_minor'] ?? 0))
@php($credits = (int) ($summary_bs['credits_open_bs_minor'] ?? 0))
@php($eligibleAvail = (int) ($summary_bs['eligible_payments_available_bs_minor'] ?? 0))
@php($paymentsGap = (int) ($summary_bs['payments_reconciliation_gap_bs_minor'] ?? 0))
@php($netDue = (int) ($summary_bs['final_due_bs_minor'] ?? $summary_bs['net_due_after_credit_bs_minor'] ?? 0))
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

<div class="summary">
    <div class="metric debt">
        <div class="k">Deuda bruta</div>
        <div class="v nums">{{ number_format($open/100, 2, ',', '.') }}</div>
        <div class="sub">Bs en cargos abiertos</div>
    </div>
    <div class="metric overdue">
        <div class="k">Vencido</div>
        <div class="v nums">{{ number_format($overdue/100, 2, ',', '.') }}</div>
        <div class="sub">Bs al corte</div>
    </div>
    <div class="metric credit">
        <div class="k">A favor</div>
        <div class="v nums">{{ number_format(($credits + $eligibleAvail)/100, 2, ',', '.') }}</div>
        <div class="sub">Créditos y pagos aplicables</div>
    </div>
    <div class="metric final">
        <div class="k">Deuda final</div>
        <div class="v nums">{{ number_format($netDue/100, 2, ',', '.') }}</div>
        <div class="sub">Bs a pagar</div>
    </div>
</div>

<div class="note">
    Todos los saldos finales están expresados en Bs al corte. Los importes en moneda origen se muestran para trazabilidad; su equivalente en Bs es el valor usado para calcular la deuda final.
</div>

<div class="section">
    <div class="section-title">Reconciliación de la deuda final</div>
    <table>
        <thead>
            <tr>
                <th style="width: 70%;">Concepto</th>
                <th class="right nums" style="width: 30%;">Bs</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 70%;">Deuda bruta (cargos abiertos)</td>
                <td class="right nums">{{ number_format($open/100, 2, ',', '.') }}</td>
            </tr>
            @if ($credits > 0)
            <tr>
                <td>− Créditos a su favor</td>
                <td class="right nums positive">−{{ number_format($credits/100, 2, ',', '.') }}</td>
            </tr>
            @endif
            @if ($eligibleAvail > 0)
            <tr>
                <td>− Pagos disponibles aplicables</td>
                <td class="right nums positive">−{{ number_format($eligibleAvail/100, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr style="font-weight: 800; background:#ecfeff;">
                <td>= Deuda final a pagar (Bs)</td>
                <td class="right nums debt-cell">{{ number_format($netDue/100, 2, ',', '.') }}</td>
            </tr>
            @if ($paymentsGap > 0)
            <tr class="small muted">
                <td>Pagos históricos fuera del alcance actual (informativo)</td>
                <td class="right nums">{{ number_format($paymentsGap/100, 2, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

@php($totalCondoUsd = 0)
@php($totalRentEur = 0)
@php($totalRentUsd = 0)
@php($totalAdjEur = 0)
@php($totalCondoUsdBs = 0)
@php($totalRentEurBs = 0)
@php($totalRentUsdBs = 0)
@php($totalAdjEurBs = 0)
@foreach (($charges ?? []) as $c)
    @php($kind = strtoupper((string) ($c['kind'] ?? '')))
    @php($currency = strtoupper((string) ($c['currency'] ?? 'VES')))
    @php($minor = (int) ($c['outstanding_minor'] ?? 0))
    @php($bs = (int) ($c['outstanding_bs_minor'] ?? 0))
    @if (str_contains($kind, 'CONDO') && $currency === 'USD')
        @php($totalCondoUsd += $minor)
        @php($totalCondoUsdBs += $bs)
    @elseif (str_contains($kind, 'RENT') && $currency === 'EUR')
        @php($totalRentEur += $minor)
        @php($totalRentEurBs += $bs)
    @elseif (str_contains($kind, 'RENT') && $currency === 'USD')
        @php($totalRentUsd += $minor)
        @php($totalRentUsdBs += $bs)
    @elseif ($kind === 'ADJ' && $currency === 'EUR')
        @php($totalAdjEur += $minor)
        @php($totalAdjEurBs += $bs)
    @endif
@endforeach

@php($conceptCount = ($totalCondoUsd > 0 ? 1 : 0) + ($totalRentEur > 0 ? 1 : 0) + ($totalAdjEur > 0 ? 1 : 0) + ($totalRentUsd > 0 ? 1 : 0))
@if ($conceptCount > 0)
<div class="section">
    <div class="section-title">Resumen por concepto</div>
    <div class="summary">
        @if ($totalCondoUsd > 0)
        <div class="metric concept">
            <div class="k">Condominio</div>
            <div class="v nums">$ {{ number_format($totalCondoUsd/100, 2, ',', '.') }}</div>
            <div class="sub">Equivalente Bs {{ number_format($totalCondoUsdBs/100, 2, ',', '.') }}</div>
        </div>
        @endif
        @if ($totalRentEur > 0)
        <div class="metric concept">
            <div class="k">Tasa de uso</div>
            <div class="v nums">€ {{ number_format($totalRentEur/100, 2, ',', '.') }}</div>
            <div class="sub">Equivalente Bs {{ number_format($totalRentEurBs/100, 2, ',', '.') }}</div>
        </div>
        @endif
        @if ($totalAdjEur > 0)
        <div class="metric concept">
            <div class="k">Gasto fijo Mant.</div>
            <div class="v nums">€ {{ number_format($totalAdjEur/100, 2, ',', '.') }}</div>
            <div class="sub">Equivalente Bs {{ number_format($totalAdjEurBs/100, 2, ',', '.') }}</div>
        </div>
        @endif
        @if ($totalRentUsd > 0)
        <div class="metric concept">
            <div class="k">Alquiler fijo</div>
            <div class="v nums">$ {{ number_format($totalRentUsd/100, 2, ',', '.') }}</div>
            <div class="sub">Equivalente Bs {{ number_format($totalRentUsdBs/100, 2, ',', '.') }}</div>
        </div>
        @endif
    </div>
</div>
@endif

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

<div class="section">
    <div class="section-title">Resumen por local</div>
    <table>
        <thead>
            <tr>
                <th style="width: 50%">Local</th>
                <th class="right nums" style="width: 25%">Deuda (Bs)</th>
                <th class="right nums" style="width: 25%">Vencido (Bs)</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($localsAgg ?? []) as $row)
                <tr>
                    <td><strong>{{ (string) ($row['label'] ?? '') }}</strong></td>
                    <td class="right nums debt-cell">{{ number_format(((int) ($row['open_bs_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                    <td class="right nums {{ ((int) ($row['overdue_bs_minor'] ?? 0)) > 0 ? 'overdue-cell' : 'positive' }}">{{ number_format(((int) ($row['overdue_bs_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="center muted">No hay cargos pendientes para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Detalle de cargos pendientes</div>
    @foreach (($localsAgg ?? []) as $lr)
        @php($lid = (int) ($lr['local_id'] ?? 0))
        @php($list = $chargesByLocal[$lid] ?? [])
        @php(usort($list, fn ($a, $b) => strcmp((string) ($a['due_on'] ?? ''), (string) ($b['due_on'] ?? '')) ?: strcmp((string) ($a['kind'] ?? ''), (string) ($b['kind'] ?? ''))))
        <div class="local-block">
            <div class="panel">
                <div class="local-header">
                    <div class="local-title">{{ (string) ($lr['label'] ?? '') }}</div>
                    <div class="local-meta">
                        <span class="local-metric">Deuda: <strong class="nums">{{ number_format(((int) ($lr['open_bs_minor'] ?? 0))/100, 2, ',', '.') }}</strong></span>
                        <span class="local-metric overdue">Vencido: <strong class="nums">{{ number_format(((int) ($lr['overdue_bs_minor'] ?? 0))/100, 2, ',', '.') }}</strong></span>
                    </div>
                </div>
                <table class="charge-table">
                    <thead>
                        <tr>
                            <th style="width: 9%">Cargo</th>
                            <th style="width: 20%">Concepto</th>
                            <th style="width: 14%">Rubro</th>
                            <th style="width: 15%">Periodo</th>
                            <th style="width: 13%">Vence</th>
                            <th class="center" style="width: 7%">Mon.</th>
                            <th class="right nums" style="width: 11%">Importe</th>
                            <th class="right nums" style="width: 11%">Saldo (Bs)</th>
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
                            @php($isChargeOverdue = $due !== '' && $due < (string) ($at ?? ''))
                            @php($badgeClass = str_contains($kind, 'CONDO') ? 'badge-condo' : (($kind === 'RENT_EUR_FIXED' || ($currency === 'USD' && str_contains($kind, 'RENT'))) ? 'badge-fixed' : (($currency === 'EUR' && str_contains($kind, 'RENT')) ? 'badge-rent' : (($kind === 'FINE') ? 'badge-fine' : 'badge-concept'))))
                            <tr>
                                <td>#{{ (int) ($c['charge_id'] ?? 0) }}</td>
                                <td class="concept-cell"><span class="badge {{ $badgeClass }}">{{ $concept }}</span></td>
                                <td>{{ $c['trade_category_name'] ?? '—' }}</td>
                                <td>{{ $period !== '' ? \Illuminate\Support\Carbon::parse($period)->locale('es')->translatedFormat('F Y') : '' }}</td>
                                <td>
                                    {{ $due !== '' ? \Illuminate\Support\Carbon::parse($due)->format('d/m/Y') : '' }}
                                    @if ($due !== '')
                                        <div><span class="badge {{ $isChargeOverdue ? 'badge-overdue' : 'badge-current' }}">{{ $isChargeOverdue ? 'Vencido' : 'Al día' }}</span></div>
                                    @endif
                                </td>
                                <td class="center"><span class="badge badge-currency">{{ $currency }}</span></td>
                                <td class="right nums">{{ number_format($amountMinor/100, 2, ',', '.') }}</td>
                                <td class="right nums debt-cell">{{ number_format($amountBsMinor/100, 2, ',', '.') }}</td>
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
    $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
    $width = $fontMetrics->get_text_width($text, $font, $size);
    $x = $pdf->get_width() - $width - 24;
    $y = $pdf->get_height() - 22;
    $pdf->page_text($x, $y, $text, $font, $size, [71,85,105]);
}
</script>
</body>
</html>
