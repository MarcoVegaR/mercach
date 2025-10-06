<?php

declare(strict_types=1);

namespace App\Http\Controllers\Charges;

use App\Contracts\Services\Charges\ChargesOrchestratorInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RunController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        // Minimal options for UI
        $markets = \App\Models\Market::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();

        return Inertia::render('charges/run', [
            'options' => [
                'types' => [
                    ['value' => 'ALL', 'label' => 'Todos (M2, Disponibles, Fijo, Condominio)'],
                    ['value' => 'RENT_EUR_M2', 'label' => 'Alquiler por m² (EUR)'],
                    ['value' => 'RENT_EUR_M2_AVAIL', 'label' => 'Locales disponibles (EUR)'],
                    ['value' => 'RENT_EUR_FIXED', 'label' => 'Alquiler fijo (EUR)'],
                    ['value' => 'CONDO_USD', 'label' => 'Condominio (USD)'],
                ],
                'markets' => $markets,
            ],
        ]);
    }

    private function currencyFor(string $type): string
    {
        return match ($type) {
            'CONDO_USD' => 'USD',
            default => 'EUR',
        };
    }

    private function formatMinor(int $minor, string $currency): string
    {
        $major = $minor / 100;

        return number_format($major, 2, '.', ',').' '.$currency;
    }

    public function run(Request $request, ChargesOrchestratorInterface $orchestrator): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:ALL,RENT_EUR_M2,RENT_EUR_M2_AVAIL,RENT_EUR_FIXED,CONDO_USD',
            'market_id' => 'nullable|integer|exists:markets,id',
            'period' => 'nullable|date', // YYYY-MM-01 or any date within month
            'date' => 'nullable|date',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $type = (string) $validated['type'];

        // Conditional validations
        $requiresPeriod = in_array($type, ['ALL', 'RENT_EUR_M2', 'RENT_EUR_M2_AVAIL', 'RENT_EUR_FIXED', 'CONDO_USD'], true);
        $requiresMarket = in_array($type, ['ALL', 'RENT_EUR_M2', 'RENT_EUR_M2_AVAIL', 'CONDO_USD'], true);
        $errors = [];
        if ($requiresPeriod && empty($validated['period'])) {
            $errors[] = 'El campo Periodo es requerido para el tipo seleccionado.';
        }
        if ($requiresMarket && empty($validated['market_id'])) {
            $errors[] = 'El campo Mercado es requerido para el tipo seleccionado.';
        }
        if (! empty($errors)) {
            return redirect()->back()->withErrors($errors);
        }

        // Prepare params baseline
        // Normalize period to first day of month if provided
        $normalizedPeriod = null;
        if (! empty($validated['period'])) {
            try {
                $normalizedPeriod = Carbon::parse((string) $validated['period'])->startOfMonth()->toDateString();
            } catch (\Throwable $e) {
                return redirect()->back()->withErrors(['Periodo inválido. Use un mes válido (YYYY-MM).']);
            }
        }

        $baseParams = array_filter([
            'market_id' => $validated['market_id'] ?? null,
            'period' => $normalizedPeriod,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // Server-side preflight checks per type
        $typesToRun = $type === 'ALL' ? ['RENT_EUR_M2', 'RENT_EUR_M2_AVAIL', 'RENT_EUR_FIXED', 'CONDO_USD'] : [$type];
        $preErrors = [];
        foreach ($typesToRun as $t) {
            $preErrors = array_merge($preErrors, $this->preflight($t, $baseParams));
        }
        if (! empty($preErrors)) {
            return redirect()->back()->withErrors($preErrors);
        }

        if ($type !== 'ALL') {
            $result = $orchestrator->run($type, $baseParams);
            $totalMinor = isset($result['totalMinor']) ? (int) $result['totalMinor'] : 0;
            $cur = $this->currencyFor($type);
            $totalStr = $totalMinor > 0 ? (' Total: '.$this->formatMinor($totalMinor, $cur)) : '';
            $extra = '';
            if ($type === 'CONDO_USD') {
                $unitMinor = isset($result['unitMinor']) ? (int) $result['unitMinor'] : 0;
                if ($unitMinor > 0) {
                    $extra = ' | Costo por m²: '.$this->formatMinor($unitMinor, 'USD');
                }
            }

            return redirect()->route('charges.index')
                ->with('success', sprintf('Generación ejecutada (%s). Generados: %d, upserted: %d, Omitidos: %d, Errores: %d%s%s', $type, $result['generated'], $result['upserted'], $result['skipped'], count($result['errors']), $totalStr, $extra));
        }

        // Run ALL sequentially and aggregate (including totals)
        $types = ['RENT_EUR_M2', 'RENT_EUR_M2_AVAIL', 'RENT_EUR_FIXED', 'CONDO_USD'];
        $agg = [
            'generated' => 0,
            'upserted' => 0,
            'skipped' => 0,
            'errors' => [],
            'totalEurMinor' => 0,
            'totalUsdMinor' => 0,
            'condoUnitMinor' => null,
        ];
        foreach ($types as $t) {
            $res = $orchestrator->run($t, $baseParams);
            $agg['generated'] += (int) $res['generated'];
            $agg['upserted'] += (int) $res['upserted'];
            $agg['skipped'] += (int) $res['skipped'];
            if (! empty($res['errors'])) {
                $agg['errors'] = array_merge($agg['errors'], (array) $res['errors']);
            }
            $tot = isset($res['totalMinor']) ? (int) $res['totalMinor'] : 0;
            if ($t === 'CONDO_USD') {
                $agg['totalUsdMinor'] += $tot;
                if (isset($res['unitMinor']) && (int) $res['unitMinor'] > 0) {
                    $agg['condoUnitMinor'] = (int) $res['unitMinor'];
                }
            } else {
                $agg['totalEurMinor'] += $tot;
            }
        }

        $errCount = count($agg['errors']);
        $msg = sprintf('Generación ejecutada (Todos). Generados: %d, upserted: %d, Omitidos: %d, Errores: %d', $agg['generated'], $agg['upserted'], $agg['skipped'], $errCount);
        if ($agg['totalEurMinor'] > 0) {
            $msg .= ' | Total EUR: '.$this->formatMinor((int) $agg['totalEurMinor'], 'EUR');
        }
        if ($agg['totalUsdMinor'] > 0) {
            $msg .= ' | Total USD: '.$this->formatMinor((int) $agg['totalUsdMinor'], 'USD');
            if (! empty($agg['condoUnitMinor'])) {
                $msg .= ' | Costo condominio por m²: '.$this->formatMinor((int) $agg['condoUnitMinor'], 'USD');
            }
        }
        if ($errCount > 0) {
            $msg .= ' Detalles: '.implode(' | ', array_map('strval', array_slice($agg['errors'], 0, 5))).($errCount > 5 ? '…' : '');
        }

        return redirect()->route('charges.index')->with('success', $msg);
    }

    /**
     * Preflight validations per charge type. Returns array of error messages.
     *
     * @param  array<string,mixed>  $params
     * @return array<int,string>
     */
    private function preflight(string $type, array $params): array
    {
        $errors = [];
        $marketId = (int) ($params['market_id'] ?? 0);
        $period = (string) ($params['period'] ?? '');
        $monthStart = '';
        $monthEnd = '';
        if ($period !== '') {
            try {
                $monthStart = Carbon::parse($period)->startOfMonth()->toDateString();
                $monthEnd = Carbon::parse($period)->endOfMonth()->toDateString();
            } catch (\Throwable) {
                $errors[] = 'Periodo inválido. Use un mes válido (YYYY-MM).';

                return $errors;
            }
        }

        // Market must be active for types that require a market
        if (in_array($type, ['RENT_EUR_M2', 'RENT_EUR_M2_AVAIL', 'CONDO_USD'], true)) {
            $isActiveMarket = $marketId > 0 && DB::table('markets')->where('id', $marketId)->where('is_active', true)->exists();
            if (! $isActiveMarket) {
                $errors[] = 'El mercado seleccionado no existe o no está activo.';
            }
        }

        // Tariff required for M2 and Disponibles
        if (in_array($type, ['RENT_EUR_M2', 'RENT_EUR_M2_AVAIL'], true) && $marketId > 0) {
            $tariff = DB::table('market_tariffs')->where('market_id', $marketId)->where('is_current', true)->first(['price_per_m2_eur_minor']);
            if (! $tariff) {
                $errors[] = 'No existe una tarifa vigente para el mercado seleccionado.';
            } elseif ((int) $tariff->price_per_m2_eur_minor <= 0) {
                $errors[] = 'La tarifa vigente del mercado no tiene un precio válido (> 0).';
            }
        }

        // Condo requires a period row and non-zero expenses
        if ($type === 'CONDO_USD' && $marketId > 0 && $period !== '') {
            $condo = DB::table('condo_periods')->where('market_id', $marketId)->where('period', $period)->whereNull('deleted_at')->first(['id', 'status']);
            if (! $condo) {
                $errors[] = 'No existe un período de condominio para el mercado y período seleccionados.';
            } else {
                // Must be FINAL to run charges
                if (strtoupper((string) ($condo->status ?? '')) !== 'FINAL') {
                    $errors[] = 'El período de condominio debe estar FINAL para ejecutar cargos.';
                }
                $sum = (int) DB::table('condo_expenses')
                    ->where('condo_period_id', $condo->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->sum('amount_usd_minor');
                if ($sum <= 0) {
                    $errors[] = 'El período de condominio no tiene gastos cargados.';
                }
                // Ensure there are participants with positive total area
                $totalArea = (float) DB::table('locals as l')
                    ->where('l.market_id', $marketId)
                    ->where('l.is_active', true)
                    ->whereNull('l.deleted_at')
                    ->whereNotExists(function ($q) use ($condo): void {
                        $q->from('condo_participants as cp2')
                            ->whereColumn('cp2.local_id', 'l.id')
                            ->where('cp2.condo_period_id', '=', $condo->id)
                            ->whereNull('cp2.deleted_at')
                            ->where('cp2.is_active', '=', true)
                            ->where('cp2.included', '=', false);
                    })
                    ->sum('l.area_m2');
                if ($totalArea <= 0.0) {
                    $errors[] = 'El período de condominio no tiene participantes con metraje válido.';
                }
            }
        }

        // Candidate existence checks per type (avoid ejecutar en vacío)
        if ($period !== '') {
            if ($type === 'RENT_EUR_M2' && $marketId > 0) {
                $cnt = (int) DB::table('contracts as c')
                    ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                    ->join('contract_modalities as cm', 'cm.id', '=', 'c.contract_modality_id')
                    ->join('contract_types as ct', 'ct.id', '=', 'c.contract_type_id')
                    ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                    ->join('locals as l', 'l.id', '=', 'cl.local_id')
                    ->whereIn('cs.code', ['VIG', 'EXT'])
                    ->where('cm.code', '=', 'M2')
                    ->where('ct.code', '=', 'CONV')
                    ->where('l.market_id', '=', $marketId)
                    ->whereNull('c.deleted_at')
                    ->whereNull('l.deleted_at')
                    ->whereDate('c.start_date', '<=', $monthEnd)
                    ->where(function ($q) use ($monthStart) {
                        $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $monthStart);
                    })
                    ->count();
                if ($cnt <= 0) {
                    $errors[] = 'No hay contratos M2 vigentes en el período para el mercado seleccionado.';
                }
            }

            if ($type === 'RENT_EUR_M2_AVAIL' && $marketId > 0) {
                $cnt = (int) DB::table('locals as l')
                    ->where('l.market_id', '=', $marketId)
                    ->whereNull('l.deleted_at')
                    ->whereNotExists(function ($sub) use ($monthStart, $monthEnd): void {
                        $sub->from('contract_local as cl')
                            ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
                            ->join('contract_modalities as cm', 'cm.id', '=', 'c.contract_modality_id')
                            ->join('contract_types as ct', 'ct.id', '=', 'c.contract_type_id')
                            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                            ->whereColumn('cl.local_id', 'l.id')
                            ->where('ct.code', '=', 'CONV')
                            ->whereIn('cm.code', ['M2', 'TFIJA'])
                            ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
                            ->whereDate('c.start_date', '<=', $monthEnd)
                            ->where(function ($q) use ($monthStart): void {
                                $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $monthStart);
                            })
                            ->whereNull('c.deleted_at');
                    })
                    ->count();
                if ($cnt <= 0) {
                    $errors[] = 'No hay locales disponibles (sin contrato vigente) en el período para el mercado seleccionado.';
                }
            }

            if ($type === 'RENT_EUR_FIXED') {
                $cnt = (int) DB::table('contracts as c')
                    ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
                    ->join('contract_modalities as cm', 'cm.id', '=', 'c.contract_modality_id')
                    ->join('contract_types as ct', 'ct.id', '=', 'c.contract_type_id')
                    ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                    ->join('locals as l', 'l.id', '=', 'cl.local_id')
                    ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
                    ->where('cm.code', '=', 'TFIJA')
                    ->where('ct.code', '=', 'CONV')
                    ->whereNull('c.deleted_at')
                    ->whereNull('l.deleted_at')
                    ->whereNotNull('c.monthly_price_eur')
                    ->whereRaw('c.monthly_price_eur > 0')
                    ->whereDate('c.start_date', '<=', $monthEnd)
                    ->where(function ($q) use ($monthStart) {
                        $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $monthStart);
                    })
                    ->count();
                if ($cnt <= 0) {
                    $errors[] = 'No hay contratos fijos (TFIJA) elegibles en el período seleccionado.';
                }
            }
        }

        return $errors;
    }
}
