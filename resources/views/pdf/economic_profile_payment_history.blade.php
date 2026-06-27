<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Histórico de pagos</title>
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
        .metric.count { background: #eef2ff; border-color: #c7d2fe; }
        .metric.total { background: #ecfeff; border-color: #67e8f9; }
        .metric.applied { background: #f0fdf4; border-color: #86efac; }
        .metric.available { background: #fff7ed; border-color: #fdba74; }
        .metric .k { color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .metric .v { font-size: 14px; font-weight: 800; margin-top: 2px; }
        .metric .sub { color: #64748b; font-size: 7.5px; margin-top: 2px; }
        .metric.count .v { color: #3730a3; }
        .metric.total .v { color: #0f766e; }
        .metric.applied .v { color: #15803d; }
        .metric.available .v { color: #c2410c; }
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
        .debt-cell { color: #c2410c; font-weight: 800; }
        .badge { display: inline-block; border-radius: 10px; padding: 2px 6px; font-size: 7px; font-weight: 800; text-transform: uppercase; }
        .badge-voided { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }
        .badge-credit { color: #1d4ed8; background: #dbeafe; border: 1px solid #bfdbfe; }
        .badge-applied { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; }
        .badge-partial { color: #92400e; background: #fef3c7; border: 1px solid #fde68a; }
        .badge-available { color: #0f766e; background: #ecfeff; border: 1px solid #67e8f9; }
        .badge-method { color: #334155; background: #f1f5f9; border: 1px solid #e2e8f0; }
        .row-voided td { color: #64748b; text-decoration: line-through; }
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
@endphp

<div class="header">
    <div class="brand">
        <div class="eyebrow">Historial de pagos del titular</div>
        <div class="doc-title">Histórico de pagos</div>
        <div class="doc-subtitle">Pagos registrados, aplicación a deuda, disponibilidad elegible y trazabilidad por local.</div>
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
                @php
                    $codes = $included_local_codes ?? [];
                @endphp
                <div class="value">{{ empty($codes) ? 'Todos' : implode(', ', array_slice($codes, 0, 8)) }}</div>
                @if (!empty($codes) && count($codes) > 8)
                    <div class="small muted">+{{ count($codes) - 8 }} más</div>
                @endif
            </div>
        </div>
    </div>
</div>

@php
    $totalCount = (int) data_get($totals, 'count', 0);
    $voidedCount = (int) data_get($totals, 'voided_count', 0);
    $activeAmount = (int) data_get($totals, 'amount_active_bs_minor', data_get($totals, 'amount_bs_minor', 0));
    $appliedAmount = (int) data_get($totals, 'applied_bs_minor', 0);
    $eligibleAvail = (int) data_get($totals, 'eligible_available_bs_minor', 0);
    $convertedCredit = (int) data_get($totals, 'converted_to_credit_bs_minor', 0);
    $voidedAmount = (int) data_get($totals, 'voided_bs_minor', 0);
    $rawAvail = (int) data_get($totals, 'available_bs_minor', 0);
    $finalDue = (int) data_get($reconciliation ?? [], 'summary_bs.final_due_bs_minor', 0);
@endphp

<div class="summary">
    <div class="metric count">
        <div class="k">Pagos vivos</div>
        <div class="v nums">{{ $totalCount - $voidedCount }}@if ($voidedCount > 0)<span class="small muted"> / {{ $totalCount }}</span>@endif</div>
        <div class="sub">Registros no anulados</div>
    </div>
    <div class="metric total">
        <div class="k">Total registrado</div>
        <div class="v nums">{{ number_format($activeAmount/100, 2, ',', '.') }}</div>
        <div class="sub">Bs de pagos vivos</div>
    </div>
    <div class="metric applied">
        <div class="k">Aplicado a deuda</div>
        <div class="v nums">{{ number_format($appliedAmount/100, 2, ',', '.') }}</div>
        <div class="sub">Bs cruzados</div>
    </div>
    <div class="metric available">
        <div class="k">Disponible aplicable</div>
        <div class="v nums">{{ number_format($eligibleAvail/100, 2, ',', '.') }}</div>
        <div class="sub">Bs elegibles</div>
    </div>
</div>

<div class="note">
    El disponible aplicable representa pagos vivos que aún pueden cruzarse contra deuda dentro del alcance actual.
</div>

<div class="section">
    <div class="section-title">Ciclo de vida y reconciliación</div>
    <table>
        <thead>
            <tr>
                <th style="width: 70%;">Concepto</th>
                <th class="right nums" style="width: 30%;">Bs</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 70%;">Total registrado (incluye anulados)</td>
                <td class="right nums">{{ number_format(((int) data_get($totals, 'amount_bs_minor', 0))/100, 2, ',', '.') }}</td>
            </tr>
            @if ($voidedCount > 0)
            <tr>
                <td>− Anulados (VOID): {{ $voidedCount }}</td>
                <td class="right nums">−{{ number_format($voidedAmount/100, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr style="font-weight: 700;">
                <td>= Pagos vivos</td>
                <td class="right nums">{{ number_format($activeAmount/100, 2, ',', '.') }}</td>
            </tr>
            <tr class="small muted">
                <td>− Aplicado a deuda</td>
                <td class="right nums positive">−{{ number_format($appliedAmount/100, 2, ',', '.') }}</td>
            </tr>
            @if ($convertedCredit > 0)
            <tr class="small muted">
                <td>− Convertido a crédito (CONC → customer_credit OPEN)</td>
                <td class="right nums positive">−{{ number_format($convertedCredit/100, 2, ',', '.') }}</td>
            </tr>
            @endif
            @if ($eligibleAvail > 0)
            <tr style="font-weight: 800; background:#ecfeff;">
                <td>= Disponible aplicable a deuda</td>
                <td class="right nums debt-cell">{{ number_format($eligibleAvail/100, 2, ',', '.') }}</td>
            </tr>
            @endif
            @if ($finalDue > 0)
            <tr class="small muted">
                <td>Deuda final del Perfil Económico (referencia)</td>
                <td class="right nums">{{ number_format($finalDue/100, 2, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Detalle de pagos</div>
    <table>
        <thead>
            <tr>
                <th style="width: 6%">Pago</th>
                <th style="width: 11%">Fecha</th>
                <th style="width: 9%">Estado</th>
                <th style="width: 9%">Método</th>
                <th style="width: 11%">Referencia</th>
                <th style="width: 12%">Locales</th>
                <th style="width: 16%">Cruce de deuda</th>
                <th class="right nums" style="width: 8%">Monto</th>
                <th class="right nums" style="width: 8%">Aplicado</th>
                <th class="right nums" style="width: 10%">Disp. elegible</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($payments ?? []) as $payment)
                @php
                    $isVoided = (bool) ($payment['is_voided'] ?? false);
                    $lifecycle = (string) ($payment['lifecycle_state'] ?? 'unknown');
                    $eligible = (int) ($payment['eligible_available_bs_minor'] ?? 0);
                    $converted = (int) ($payment['converted_to_credit_bs_minor'] ?? 0);
                    $applied = (int) ($payment['crossed_bs_minor'] ?? $payment['applied_bs_minor'] ?? 0);
                    $badgeClass = match ($lifecycle) {
                        'voided' => 'badge-voided',
                        'converted_to_credit' => 'badge-credit',
                        'fully_applied' => 'badge-applied',
                        'partially_applied' => 'badge-partial',
                        'available' => 'badge-available',
                        default => 'badge-partial',
                    };
                    $badgeText = match ($lifecycle) {
                        'voided' => 'Anulado',
                        'converted_to_credit' => 'A crédito',
                        'fully_applied' => 'Aplicado',
                        'partially_applied' => 'Parcial',
                        'available' => 'Disponible',
                        default => (string) ($payment['status'] ?? '—'),
                    };
                @endphp
                <tr class="{{ $isVoided ? 'row-voided' : '' }}">
                    <td>#{{ (int) ($payment['payment_id'] ?? 0) }}</td>
                    <td>{{ !empty($payment['paid_on']) ? \Illuminate\Support\Carbon::parse((string) $payment['paid_on'])->format('d/m/Y') : (!empty($payment['created_at']) ? \Illuminate\Support\Carbon::parse((string) $payment['created_at'])->format('d/m/Y') : '—') }}</td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
                        @if ($converted > 0)
                            <div class="small muted">+{{ number_format($converted/100, 2, ',', '.') }} crédito</div>
                        @endif
                    </td>
                    <td><span class="badge badge-method">{{ (string) ($payment['method'] ?? '—') }}</span></td>
                    <td>{{ (string) ($payment['reference'] ?? '—') }}</td>
                    <td>{{ (string) ($payment['local_summary_label'] ?? '—') }}</td>
                    <td>
                        {{ (string) ($payment['cross_summary'] ?? 'Sin aplicación registrada') }}
                        @if (!empty($payment['crossed_charge_count']))
                            <div class="small muted">{{ (int) ($payment['crossed_charge_count'] ?? 0) }} cargos cruzados</div>
                        @endif
                    </td>
                    <td class="right nums">{{ number_format(((int) ($payment['amount_bs_minor'] ?? 0))/100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ number_format($applied/100, 2, ',', '.') }}</td>
                    <td class="right nums">{{ $eligible > 0 ? number_format($eligible/100, 2, ',', '.') : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center muted">No hay pagos para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
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
