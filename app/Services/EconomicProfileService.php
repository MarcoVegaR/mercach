<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\EconomicProfileServiceInterface;
use App\Contracts\Services\FxRateServiceInterface;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\Concessionaire;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\Local as LocalModel;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use DateTimeInterface;
use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EconomicProfileService implements EconomicProfileServiceInterface
{
    public function __construct(private ContainerInterface $container) {}

    public function searchConcessionaires(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $builder = Concessionaire::query()
            ->select(['id', 'full_name', 'document_type_id', 'document_number'])
            ->limit($limit)
            ->orderBy('full_name');

        // Document search by digits
        if (preg_match('/^\d{6,}$/', $q)) {
            $rows = $builder->where('document_number', 'LIKE', "%{$q}%")->orderBy('document_number')->limit($limit)->get();
        } else {
            // Broad prefilter (case-insensitive) then strict normalize match in PHP
            $prefilter = Concessionaire::query()
                ->select(['id', 'full_name', 'document_type_id', 'document_number'])
                ->whereRaw('LOWER(full_name) LIKE ?', ['%'.strtolower($q).'%'])
                ->orderBy('full_name')
                ->limit($limit * 10)
                ->get();
            $qNorm = $this->normalizeText($q);
            $rows = $prefilter->filter(function ($c) use ($qNorm) {
                $name = (string) ($c->getAttribute('full_name') ?? '');

                return str_contains($this->normalizeText($name), $qNorm);
            })->take($limit);
        }

        return $rows->map(function ($c) {
            return [
                'id' => (int) $c->getKey(),
                'label' => (string) $c->getAttribute('full_name'),
                'document_number' => (string) ($c->getAttribute('document_number') ?? ''),
            ];
        })->all();
    }

    public function searchLocals(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        // Broad prefilter (case-insensitive) then strict normalize match in PHP
        $prefilter = LocalModel::query()
            ->select(['id', 'code', 'name'])
            ->where(function ($b) use ($q) {
                $b->whereRaw('LOWER(code) LIKE ?', ['%'.strtolower($q).'%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%'.strtolower($q).'%']);
            })
            ->orderBy('code')
            ->limit($limit * 10)
            ->get();
        $qNorm = $this->normalizeText($q);
        $rows = $prefilter->filter(function ($l) use ($qNorm) {
            $code = (string) ($l->getAttribute('code') ?? '');
            $name = (string) ($l->getAttribute('name') ?? '');
            $label = trim(($code ? $code.' • ' : '').$name);

            return str_contains($this->normalizeText($label), $qNorm);
        })->take($limit);

        return $rows->map(function ($l) {
            $code = (string) ($l->getAttribute('code') ?? '');
            $name = (string) ($l->getAttribute('name') ?? '');

            return [
                'id' => (int) $l->getKey(),
                'label' => trim(($code ? $code.' • ' : '').$name),
            ];
        })->all();
    }

    public function forConcessionaire(int $id, ?DateTimeInterface $at = null, array $filters = []): array
    {
        $tz = (string) config('app.timezone', 'America/Caracas');
        $at = $at
            ? Carbon::parse($at->format('Y-m-d'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        // Resolve locals held by concessionaire at date "at"
        // VENC: considered active (ignores end_date) until explicitly terminated (TERM)
        $locals = DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', $id)
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $at->toDateString())
            ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
            ->where(function ($w) use ($at) {
                $w->whereIn('cs.code', ['VIG', 'EXT'])
                    ->where(function ($q) use ($at) {
                        $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $at->toDateString());
                    })
                    ->orWhere('cs.code', '=', 'VENC');
            })
            ->pluck('l.id')
            ->unique()
            ->values()
            ->all();

        if (isset($filters['local_ids']) && is_array($filters['local_ids']) && count($filters['local_ids']) > 0) {
            $wanted = array_values(array_unique(array_filter(array_map(fn ($v) => is_numeric($v) ? (int) $v : 0, $filters['local_ids']))));
            if (! empty($wanted)) {
                $locals = array_values(array_intersect($locals, $wanted));
            }
        }

        $header = $this->loadConcessionaireHeader($id, $locals);

        $chargesData = $this->loadChargesDataForLocals($locals, $at, $filters);
        $paymentsAvailable = $this->sumAvailablePayments('CONCESSIONAIRE', $id);
        $creditsOpen = $this->sumOpenCredits('CONCESSIONAIRE', $id);

        $summary = [
            'open_bs_minor' => $chargesData['sum_open_bs_minor'],
            'overdue_bs_minor' => $chargesData['sum_overdue_bs_minor'],
            'payments_available_bs_minor' => $paymentsAvailable,
            'credits_open_bs_minor' => $creditsOpen,
            'net_due_after_credit_bs_minor' => max(0, $chargesData['sum_open_bs_minor'] - $creditsOpen),
            'aging' => $chargesData['aging'],
        ];

        // FX-based aggregates (portal-style) for local profile as well
        $fxSummaryBs = $this->convertSummaryFxToBs($chargesData['summary_fx']);
        if ($fxSummaryBs['open_bs_minor_from_fx'] > 0 || $fxSummaryBs['overdue_bs_minor_from_fx'] > 0) {
            $summary['open_bs_minor_from_fx'] = $fxSummaryBs['open_bs_minor_from_fx'];
            $summary['overdue_bs_minor_from_fx'] = $fxSummaryBs['overdue_bs_minor_from_fx'];
            $summary['net_due_after_credit_bs_minor_from_fx'] = max(0, $fxSummaryBs['open_bs_minor_from_fx'] - $creditsOpen);
        }

        return [
            'header' => $header,
            'summary_bs' => $summary,
            'summary_fx' => $chargesData['summary_fx'],
            'by_local' => $chargesData['by_local'],
            'tables' => [
                'charges_open' => $chargesData['charges_open'],
                'credits_open' => $this->listOpenCredits('CONCESSIONAIRE', $id),
                'payments_partial' => $this->listPartialPayments('CONCESSIONAIRE', $id),
            ],
            'recent' => $this->recentEventsForLocals($locals, $at),
        ];
    }

    public function forLocal(int $id, ?DateTimeInterface $at = null, array $filters = []): array
    {
        $tz = (string) config('app.timezone', 'America/Caracas');
        $at = $at
            ? Carbon::parse($at->format('Y-m-d'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $header = $this->loadLocalHeader($id, $at);
        $chargesData = $this->loadChargesDataForLocals([$id], $at, $filters);
        $paymentsAvailable = $this->sumAvailablePayments('LOCAL', $id);
        $creditsOpen = $this->sumOpenCredits('LOCAL', $id);

        $summary = [
            'open_bs_minor' => $chargesData['sum_open_bs_minor'],
            'overdue_bs_minor' => $chargesData['sum_overdue_bs_minor'],
            'payments_available_bs_minor' => $paymentsAvailable,
            'credits_open_bs_minor' => $creditsOpen,
            'net_due_after_credit_bs_minor' => max(0, $chargesData['sum_open_bs_minor'] - $creditsOpen),
            'aging' => $chargesData['aging'],
        ];

        return [
            'header' => $header,
            'summary_bs' => $summary,
            'summary_fx' => $chargesData['summary_fx'],
            'by_local' => $chargesData['by_local'],
            'tables' => [
                'charges_open' => $chargesData['charges_open'],
                'credits_open' => $this->listOpenCredits('LOCAL', $id),
                'payments_partial' => $this->listPartialPayments('LOCAL', $id),
            ],
            'recent' => $this->recentEventsForLocals([$id], $at),
        ];
    }

    public function export(string $scope, int $id, string $format, ?DateTimeInterface $at = null, array $filters = []): StreamedResponse
    {
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'json'], true)) {
            $format = 'csv';
        }
        $at = $at ? Carbon::parse($at->format('Y-m-d')) : Carbon::today();
        $data = $scope === 'local' ? $this->forLocal($id, $at, $filters) : $this->forConcessionaire($id, $at, $filters);

        if ($format === 'json') {
            $response = new StreamedResponse(function () use ($data) {
                echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            });
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', 'attachment; filename="economic_profile_'.($scope).'_'.$id.'_'.date('Ymd_His').'.json"');

            return $response;
        }

        // CSV: export summary and charges
        $response = new StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Section', 'Key', 'Value']);
            foreach ($data['summary_bs'] as $k => $v) {
                fputcsv($out, ['summary_bs', $k, (string) $v]);
            }
            fputcsv($out, []);
            fputcsv($out, ['charges_open', 'charge_id', 'period', 'due_on', 'amount_bs_minor', 'allocated_bs_minor', 'credited_bs_minor', 'outstanding_bs_minor', 'local_id']);
            foreach ($data['tables']['charges_open'] as $row) {
                fputcsv($out, [
                    'charges_open',
                    $row['charge_id'] ?? null,
                    $row['period'] ?? null,
                    $row['due_on'] ?? null,
                    $row['amount_bs_minor'] ?? null,
                    $row['allocated_bs_minor'] ?? null,
                    $row['credited_bs_minor'] ?? null,
                    $row['outstanding_bs_minor'] ?? null,
                    $row['local_id'] ?? null,
                ]);
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="economic_profile_'.($scope).'_'.$id.'_'.date('Ymd_His').'.csv"');

        return $response;
    }

    // --- Helpers ---

    /**
     * @param  array<int>  $localIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function loadChargesDataForLocals(array $localIds, Carbon $at, array $filters): array
    {
        $q = Charge::query()
            ->where('debtor_type', 'LOCAL')
            ->whereIn('debtor_id', $localIds);

        try {
            $statusIds = ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
            if (! empty($statusIds)) {
                $q->whereIn('charge_status_id', $statusIds);
            }
        } catch (\Throwable $e) {
        }

        if (! empty($filters['currency'])) {
            $q->where('currency', strtoupper((string) $filters['currency']));
        }
        if (! empty($filters['kind'])) {
            $q->where('kind', strtoupper((string) $filters['kind']));
        }
        if (! empty($filters['period_from'])) {
            $from = Carbon::createFromFormat('Y-m', (string) $filters['period_from'])->startOfMonth()->toDateString();
            $q->whereDate('period', '>=', $from);
        }
        if (! empty($filters['period_to'])) {
            $to = Carbon::createFromFormat('Y-m', (string) $filters['period_to'])->endOfMonth()->toDateString();
            $q->whereDate('period', '<=', $to);
        }
        if (! empty($filters['overdue_only'])) {
            $q->whereDate('due_on', '<', $at->toDateString());
        }

        $charges = $q->orderBy('period')->limit(500)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on', 'local_id', 'kind', 'debtor_id']);

        $ids = $charges->pluck('id')->all();

        // Cargar allocations con fecha de pago para conversión correcta
        $allocRows = PaymentAllocation::query()
            ->whereIn('payment_allocations.charge_id', $ids)
            ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->get(['payment_allocations.charge_id', 'payment_allocations.amount_bs_minor', 'p.paid_on']);

        /** @var FxRateServiceInterface $fx */
        $fx = $this->container->get(FxRateServiceInterface::class);

        // Credits: convert to BS before subtracting (truncate FX to 2 decimals like portal)
        $creditApps = CreditApplication::query()->whereIn('charge_id', $ids)->get(['charge_id', 'amount_minor', 'customer_credit_id']);
        $creditByChargeBs = collect();
        if ($creditApps->count() > 0) {
            $creditIds = $creditApps->pluck('customer_credit_id')->filter()->unique()->values()->all();
            $credits = empty($creditIds)
                ? collect()
                : CustomerCredit::query()->whereIn('id', $creditIds)->get(['id', 'currency'])->keyBy('id');
            foreach ($creditApps as $app) {
                $cid = (int) ($app->getAttribute('customer_credit_id') ?? 0);
                $currency = (string) ($credits[$cid]->getAttribute('currency') ?? 'VES');
                $amountMinor = (int) $app->getAttribute('amount_minor');
                if ($currency === 'VES') {
                    $amountBsMinor = $amountMinor;
                } else {
                    $rate = $fx->resolveAt($currency, $at);
                    $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                    $amountBsMinor = $this->toVesMinor($amountMinor, $rateToVes) ?? 0;
                }
                $chargeId = (int) $app->getAttribute('charge_id');
                $creditByChargeBs[$chargeId] = (int) ($creditByChargeBs[$chargeId] ?? 0) + $amountBsMinor;
            }
        }

        // Pre-fetch FX for USD/EUR for summary in those currencies
        $usdRate = $fx->resolveAt('USD', $at);
        $eurRate = $fx->resolveAt('EUR', $at);
        $usdToVes = $usdRate ? (float) $usdRate->getAttribute('rate_to_ves') : null;
        $eurToVes = $eurRate ? (float) $eurRate->getAttribute('rate_to_ves') : null;

        $byLocalAgg = [];
        $aging = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $sumOpen = 0;
        $sumOverdue = 0;
        $summaryFx = [
            'condo' => ['currency' => 'USD', 'open_minor' => 0, 'overdue_minor' => 0, 'rate_to_ves' => $usdToVes],
            'rent_m2' => ['currency' => 'EUR', 'open_minor' => 0, 'overdue_minor' => 0, 'rate_to_ves' => $eurToVes],
            'rent_fixed' => ['currency' => 'USD', 'open_minor' => 0, 'overdue_minor' => 0, 'rate_to_ves' => $usdToVes],
        ];
        $rows = [];
        foreach ($charges as $c) {
            $currency = strtoupper((string) ($c->getAttribute('currency') ?? ''));
            $amountMinor = (int) $c->getAttribute('amount_minor');
            $chargeId = (int) $c->getAttribute('id');

            // IMPORTANTE: Calcular outstanding en moneda original primero,
            // luego convertir a VES con tasa de hoy. Esto evita discrepancias
            // cuando la tasa FX cambia entre el momento del pago y hoy.

            // Inicializar variables
            $allocated = 0;
            $credited = 0;

            if ($currency === 'VES' || $currency === '') {
                // Para VES, usar lógica simple
                $allocated = (int) $allocRows->where('charge_id', $chargeId)->sum('amount_bs_minor');
                $credited = (int) ($creditByChargeBs[$chargeId] ?? 0);
                $outstanding = max(0, $amountMinor - $allocated - $credited);
                $outstandingOriginal = $outstanding;
                $amountBsMinor = $amountMinor;
            } else {
                // Para monedas extranjeras: convertir allocations a moneda original
                $allocatedCurrencyMinor = 0;
                $allocated = (int) $allocRows->where('charge_id', $chargeId)->sum('amount_bs_minor');
                foreach ($allocRows->where('charge_id', $chargeId) as $row) {
                    $bsMinor = (int) ($row->getAttribute('amount_bs_minor') ?? 0);
                    $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
                    if ($bsMinor > 0 && $paidRaw !== '') {
                        $paidAt = new \DateTimeImmutable($paidRaw);
                        $rateAtPay = $fx->resolveAt($currency, $paidAt);
                        $rateToVesAtPay = $rateAtPay ? (float) $rateAtPay->getAttribute('rate_to_ves') : null;
                        if ($rateToVesAtPay !== null && $rateToVesAtPay > 0) {
                            // Convertir Bs a moneda original: Bs / tasa = currency
                            $currencyMinor = $this->fromVesMinor($bsMinor, $rateToVesAtPay);
                            if ($currencyMinor !== null) {
                                $allocatedCurrencyMinor += $currencyMinor;
                            }
                        }
                    }
                }

                // Convertir credits a moneda original
                $credited = (int) ($creditByChargeBs[$chargeId] ?? 0);
                $creditedCurrencyMinor = 0;
                if ($credited > 0) {
                    $rateNow = $fx->resolveAt($currency, $at);
                    $rateToVesNow = $rateNow ? (float) $rateNow->getAttribute('rate_to_ves') : null;
                    if ($rateToVesNow !== null && $rateToVesNow > 0) {
                        $converted = $this->fromVesMinor($credited, $rateToVesNow);
                        if ($converted !== null) {
                            $creditedCurrencyMinor = $converted;
                        }
                    }
                }

                // Outstanding en moneda original
                $outstandingOriginal = max(0, $amountMinor - $allocatedCurrencyMinor - $creditedCurrencyMinor);

                // Convertir a VES con tasa de hoy
                $rateNow = $fx->resolveAt($currency, $at);
                $rateToVesNow = $rateNow ? (float) $rateNow->getAttribute('rate_to_ves') : null;
                $outstanding = $this->toVesMinor($outstandingOriginal, $rateToVesNow) ?? 0;
                $amountBsMinor = $this->toVesMinor($amountMinor, $rateToVesNow);
            }

            $localId = (int) ($c->getAttribute('local_id') ?? 0);
            if (! isset($byLocalAgg[$localId])) {
                $byLocalAgg[$localId] = [
                    'local_id' => $localId,
                    'open_bs_minor' => 0,
                    'overdue_bs_minor' => 0,
                    'partial_applied_bs_minor' => 0,
                    'net_due_bs_minor' => 0,
                    'currency' => $currency,
                    'open_minor' => 0,
                    'overdue_minor' => 0,
                    '_currencies' => [],
                ];
            }

            $ccyKey = strtoupper($currency !== '' ? $currency : 'VES');
            $byLocalAgg[$localId]['_currencies'][$ccyKey] = true;

            $byLocalAgg[$localId]['open_bs_minor'] += $outstanding;
            $byLocalAgg[$localId]['partial_applied_bs_minor'] += $allocated;
            $byLocalAgg[$localId]['net_due_bs_minor'] += $outstanding;
            $byLocalAgg[$localId]['open_minor'] += $outstandingOriginal;

            $sumOpen += $outstanding;
            $isOverdue = $c->getAttribute('due_on') && (string) $c->getAttribute('due_on') < $at->toDateString();
            if ($isOverdue) {
                $sumOverdue += $outstanding;
                $byLocalAgg[$localId]['overdue_bs_minor'] += $outstanding;
                $byLocalAgg[$localId]['overdue_minor'] += $outstandingOriginal;
                // Simple aging by days
                $days = Carbon::parse((string) $c->getAttribute('due_on'))->diffInDays($at, false);
                if ($days <= 30) {
                    $aging['0_30'] += $outstanding;
                } elseif ($days <= 60) {
                    $aging['31_60'] += $outstanding;
                } elseif ($days <= 90) {
                    $aging['61_90'] += $outstanding;
                } else {
                    $aging['90_plus'] += $outstanding;
                }
            }

            // kind evaluated later to compute summary_fx from original currency outstanding
            $kind = strtoupper((string) ($c->getAttribute('kind') ?? ''));

            $rows[] = [
                'charge_id' => (int) $c->getAttribute('id'),
                'local_id' => $localId,
                'period' => (string) $c->getAttribute('period'),
                'due_on' => (string) ($c->getAttribute('due_on') ?? ''),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'amount_bs_minor' => $amountBsMinor,
                'allocated_bs_minor' => $allocated,
                'credited_bs_minor' => $credited,
                'outstanding_bs_minor' => $outstanding,
                'outstanding_minor' => $outstandingOriginal,
                'kind' => (string) ($c->getAttribute('kind') ?? ''),
            ];
        }

        // Map local labels, codes and types
        $localsById = [];
        $localCodesById = [];
        $localTypesById = [];
        if (! empty($localIds)) {
            $locals = LocalModel::query()
                ->whereIn('locals.id', $localIds)
                ->leftJoin('local_types', 'local_types.id', '=', 'locals.local_type_id')
                ->get(['locals.id', 'locals.code', 'locals.name', 'local_types.name as type_name']);
            foreach ($locals as $l) {
                $lid = (int) $l->getAttribute('id');
                $code = (string) ($l->getAttribute('code') ?? '');
                $name = (string) ($l->getAttribute('name') ?? '');
                $typeName = (string) ($l->getAttribute('type_name') ?? '');
                $localsById[$lid] = trim(($code ? $code.' • ' : '').$name);
                $localCodesById[$lid] = $code;
                $localTypesById[$lid] = $typeName;
            }
            // Ensure all locals are present in aggregation even with zero charges
            foreach ($localIds as $lid) {
                if (! isset($byLocalAgg[$lid])) {
                    $byLocalAgg[$lid] = [
                        'local_id' => (int) $lid,
                        'open_bs_minor' => 0,
                        'overdue_bs_minor' => 0,
                        'partial_applied_bs_minor' => 0,
                        'net_due_bs_minor' => 0,
                        'currency' => 'VES',
                        'open_minor' => 0,
                        'overdue_minor' => 0,
                    ];
                }
            }
        }
        $byLocal = array_values(array_map(function ($row) use ($localsById, $localCodesById, $localTypesById) {
            $currencies = [];
            if (isset($row['_currencies'])) {
                $currencies = array_keys($row['_currencies']);
            }
            unset($row['_currencies']);

            // If a local has mixed currencies (e.g., old EUR + new USD), represent totals in VES to avoid misleading sums.
            if (count($currencies) > 1) {
                $row['currency'] = 'VES';
                $row['open_minor'] = (int) $row['open_bs_minor'];
                $row['overdue_minor'] = (int) $row['overdue_bs_minor'];
            } else {
                $ccy = strtoupper((string) $row['currency']);
                if (! in_array($ccy, ['EUR', 'USD', 'VES'], true)) {
                    $row['currency'] = 'VES';
                    $row['open_minor'] = (int) $row['open_bs_minor'];
                    $row['overdue_minor'] = (int) $row['overdue_bs_minor'];
                }
            }

            $row['local_label'] = $localsById[$row['local_id']] ?? null;
            $row['local_code'] = $localCodesById[$row['local_id']] ?? null;
            $row['local_type_name'] = $localTypesById[$row['local_id']] ?? null;

            return $row;
        }, $byLocalAgg));

        // Attach local label, code and type to each charge row for UI
        $rows = array_map(function ($row) use ($localsById, $localCodesById, $localTypesById) {
            $row['local_label'] = $localsById[$row['local_id']] ?? null;
            $row['local_code'] = $localCodesById[$row['local_id']] ?? null;
            $row['local_type_name'] = $localTypesById[$row['local_id']] ?? null;

            return $row;
        }, $rows);
        // Compute summary_fx strictly from original currency outstanding by kind (no VES conversion)
        $summaryFx = [
            'condo' => ['currency' => 'USD', 'open_minor' => 0, 'overdue_minor' => 0, 'open_bs_minor' => 0, 'overdue_bs_minor' => 0, 'rate_to_ves' => $usdToVes],
            'rent_m2' => ['currency' => 'EUR', 'open_minor' => 0, 'overdue_minor' => 0, 'open_bs_minor' => 0, 'overdue_bs_minor' => 0, 'rate_to_ves' => $eurToVes],
            'rent_fixed' => ['currency' => 'USD', 'open_minor' => 0, 'overdue_minor' => 0, 'open_bs_minor' => 0, 'overdue_bs_minor' => 0, 'rate_to_ves' => $usdToVes],
            'other' => ['currency' => 'VES', 'open_minor' => 0, 'overdue_minor' => 0, 'open_bs_minor' => 0, 'overdue_bs_minor' => 0, 'rate_to_ves' => null],
        ];
        foreach ($rows as $r) {
            $kind = strtoupper((string) $r['kind']);
            $currency = strtoupper((string) $r['currency']);
            $openMinor = (int) $r['outstanding_minor'];
            $openBsMinor = (int) $r['outstanding_bs_minor'];
            $isOverdue = (string) $r['due_on'] < $at->toDateString();

            $bucket = null;
            if (str_starts_with($kind, 'CONDO')) {
                $bucket = 'condo';
            } elseif ($kind === 'RENT_EUR_M2') {
                $bucket = 'rent_m2';
            } elseif ($kind === 'RENT_EUR_FIXED') {
                $bucket = 'rent_fixed';
            } elseif (str_starts_with($kind, 'RENT')) {
                $bucket = $currency === 'USD' ? 'rent_fixed' : 'rent_m2';
            }

            if ($bucket === null) {
                $bucket = 'other';
            }

            $summaryFx[$bucket]['open_minor'] += $openMinor;
            $summaryFx[$bucket]['open_bs_minor'] += $openBsMinor;
            if ($isOverdue) {
                $summaryFx[$bucket]['overdue_minor'] += $openMinor;
                $summaryFx[$bucket]['overdue_bs_minor'] += $openBsMinor;
            }
        }

        // Backward compatible alias: legacy UIs expect `rent` as EUR.
        $summaryFx['rent'] = $summaryFx['rent_m2'];

        return [
            'sum_open_bs_minor' => $sumOpen,
            'sum_overdue_bs_minor' => $sumOverdue,
            'aging' => $aging,
            'by_local' => $byLocal,
            'charges_open' => $rows,
            'summary_fx' => $summaryFx,
        ];
    }

    /**
     * Convert aggregated FX summary (EUR/USD) to Bs with truncation, portal-style.
     *
     * @param  array<string, array<string, mixed>>  $summaryFx
     * @return array{open_bs_minor_from_fx:int,overdue_bs_minor_from_fx:int}
     */
    private function convertSummaryFxToBs(array $summaryFx): array
    {
        $openBsFromFx = 0;
        $overdueBsFromFx = 0;

        foreach ($summaryFx as $key => $row) {
            if ($key === 'rent') {
                continue;
            }
            $openMinor = (int) $row['open_minor'];
            $overdueMinor = (int) $row['overdue_minor'];
            $rateToVes = isset($row['rate_to_ves']) ? (float) $row['rate_to_ves'] : null;

            // Usar método centralizado con política de truncamiento consistente
            $openBs = $this->toVesMinor($openMinor, $rateToVes);
            $overdueBs = $this->toVesMinor($overdueMinor, $rateToVes);

            if ($openBs !== null) {
                $openBsFromFx += $openBs;
            }
            if ($overdueBs !== null) {
                $overdueBsFromFx += $overdueBs;
            }
        }

        return [
            'open_bs_minor_from_fx' => $openBsFromFx,
            'overdue_bs_minor_from_fx' => $overdueBsFromFx,
        ];
    }

    /**
     * @param  'CONCESSIONAIRE'|'LOCAL'  $debtorType
     */
    private function sumAvailablePayments(string $debtorType, int $debtorId): int
    {
        // Sum confirmed payments amount minus allocations for those payments
        $paymentIds = Payment::query()
            ->where('debtor_type', $debtorType)
            ->where('debtor_id', $debtorId)
            ->whereHas('paymentStatus', function ($q) {
                $q->where('code', 'CONF');
            })
            ->pluck('id')
            ->all();
        if (empty($paymentIds)) {
            return 0;
        }

        $sumAmount = (int) Payment::query()->whereIn('id', $paymentIds)->sum('amount_bs_minor');
        $sumAllocated = (int) PaymentAllocation::query()->whereIn('payment_id', $paymentIds)->sum('amount_bs_minor');

        return max(0, $sumAmount - $sumAllocated);
    }

    /**
     * @param  'CONCESSIONAIRE'|'LOCAL'  $debtorType
     */
    private function sumOpenCredits(string $debtorType, int $debtorId): int
    {
        return (int) CustomerCredit::query()
            ->where('debtor_type', $debtorType)
            ->where('debtor_id', $debtorId)
            ->where('status', 'OPEN')
            ->sum('balance_minor');
    }

    /**
     * @param  'CONCESSIONAIRE'|'LOCAL'  $debtorType
     * @return array<int, array<string, mixed>>
     */
    private function listOpenCredits(string $debtorType, int $debtorId): array
    {
        return CustomerCredit::query()
            ->where('debtor_type', $debtorType)
            ->where('debtor_id', $debtorId)
            ->where('status', 'OPEN')
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'balance_minor', 'source_payment_id', 'created_at'])
            ->map(fn ($c) => [
                'credit_id' => (int) $c->getKey(),
                'balance_minor' => (int) $c->getAttribute('balance_minor'),
                'source_payment_id' => (int) ($c->getAttribute('source_payment_id') ?? 0),
                'created_at' => (string) ($c->getAttribute('created_at') ?? ''),
            ])
            ->all();
    }

    /**
     * @param  'CONCESSIONAIRE'|'LOCAL'  $debtorType
     * @return array<int, array<string, mixed>>
     */
    private function listPartialPayments(string $debtorType, int $debtorId): array
    {
        $rows = Payment::query()
            ->where('debtor_type', $debtorType)
            ->where('debtor_id', $debtorId)
            ->whereHas('paymentStatus', function ($q) {
                $q->whereIn('code', ['CONF', 'CONC']);
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'paid_on', 'amount_bs_minor']);
        $ids = $rows->pluck('id')->all();
        $alloc = empty($ids) ? collect() : PaymentAllocation::query()->whereIn('payment_id', $ids)->selectRaw('payment_id, SUM(amount_bs_minor) as s')->groupBy('payment_id')->pluck('s', 'payment_id');

        return $rows->map(function ($p) use ($alloc) {
            $amount = (int) $p->getAttribute('amount_bs_minor');
            $applied = (int) ($alloc[(int) $p->getKey()] ?? 0);

            return [
                'payment_id' => (int) $p->getKey(),
                'paid_on' => (string) ($p->getAttribute('paid_on') ?? ''),
                'status' => (string) ($p->status ?? ''),
                'applied_bs_minor' => $applied,
                'available_bs_minor' => max(0, $amount - $applied),
            ];
        })->all();
    }

    /**
     * @param  array<int>  $localIds
     * @return array<int, array<string, mixed>>
     */
    private function recentEventsForLocals(array $localIds, Carbon $at): array
    {
        // Very simple: last charges and payments
        $events = [];
        $charges = Charge::query()->whereIn('local_id', $localIds)->orderByDesc('id')->limit(10)->get(['id', 'period', 'due_on', 'amount_bs_minor_issued', 'local_id', 'created_at']);
        foreach ($charges as $c) {
            $events[] = [
                'date' => (string) ($c->getAttribute('created_at') ?? ''),
                'kind' => 'CHARGE',
                'description' => 'Cargo '.$c->getAttribute('period'),
                'amount_bs_minor' => (int) ($c->getAttribute('amount_bs_minor_issued') ?? 0),
                'ref_id' => (int) $c->getKey(),
            ];
        }
        $payments = Payment::query()->whereIn('local_id', $localIds)->orderByDesc('id')->limit(10)->get(['id', 'paid_on', 'amount_bs_minor', 'local_id']);
        foreach ($payments as $p) {
            $events[] = [
                'date' => (string) ($p->getAttribute('paid_on') ?? ''),
                'kind' => 'PAYMENT',
                'description' => 'Pago',
                'amount_bs_minor' => (int) ($p->getAttribute('amount_bs_minor') ?? 0),
                'ref_id' => (int) $p->getKey(),
            ];
        }
        usort($events, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return array_slice($events, 0, 20);
    }

    /**
     * @param  array<int>  $locals
     * @return array<string, mixed>
     */
    private function loadConcessionaireHeader(int $id, array $locals): array
    {
        /** @var null|Concessionaire $c */
        $c = Concessionaire::query()->find($id);

        return [
            'id' => $id,
            'full_name' => (string) ($c?->getAttribute('full_name') ?? ''),
            'document' => [
                'type_code' => (string) optional($c?->documentType()->first())->getAttribute('code'),
                'number' => (string) ($c?->getAttribute('document_number') ?? ''),
            ],
            'contracts_count' => (int) DB::table('concessionaire_contract')->where('concessionaire_id', $id)->count(),
            'locals_count' => count($locals),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLocalHeader(int $id, Carbon $at): array
    {
        /** @var null|LocalModel $l */
        $l = LocalModel::query()->find($id);

        // Resolve active contract and concessionaire
        $activeContract = DB::table('contract_local as cl')
            ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'c.id')
            ->join('concessionaires as con', 'con.id', '=', 'cc.concessionaire_id')
            ->where('cl.local_id', $id)
            ->whereNull('c.deleted_at')
            ->whereDate('c.start_date', '<=', $at->toDateString())
            ->whereIn('cs.code', ['VIG', 'EXT', 'VENC'])
            ->where(function ($w) use ($at) {
                $w->whereIn('cs.code', ['VIG', 'EXT'])
                    ->where(function ($q) use ($at) {
                        $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $at->toDateString());
                    })
                    ->orWhere('cs.code', '=', 'VENC');
            })
            ->select([
                'c.id as contract_id',
                'c.number as contract_number',
                'cs.name as contract_status',
                'con.id as concessionaire_id',
                'con.full_name as concessionaire_name',
            ])
            ->first();

        return [
            'id' => $id,
            'code' => (string) ($l?->getAttribute('code') ?? ''),
            'name' => (string) ($l?->getAttribute('name') ?? ''),
            'concessionaire' => $activeContract ? [
                'id' => (int) $activeContract->concessionaire_id,
                'full_name' => (string) $activeContract->concessionaire_name,
                'contract' => [
                    'id' => (int) $activeContract->contract_id,
                    'number' => (string) $activeContract->contract_number,
                    'status' => (string) $activeContract->contract_status,
                ],
            ] : null,
        ];
    }

    private function normalizeText(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(["'", '"'], '', $s);
        $map = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ];

        return strtr($s, $map);
    }

    /**
     * Convertir monto en moneda original (minor units) a Bs (minor units).
     *
     * Aplica la misma política de truncamiento que FxConversionHelper::toVes:
     * amount (2dp) * rate (2dp) => 4dp, truncar a 2dp.
     *
     * @param  int  $amountMinor  Monto en moneda original (e.g., 10000 = €100.00)
     * @param  float|null  $rateToVes  Tasa rate_to_ves (e.g., 50.25)
     * @return int|null Monto en Bs minor units, null si conversión no posible
     */
    private function toVesMinor(int $amountMinor, ?float $rateToVes): ?int
    {
        if ($amountMinor <= 0 || $rateToVes === null || $rateToVes <= 0) {
            return null;
        }

        // Política FxConversionHelper: truncar, no redondear
        $rateMinor = (int) round($rateToVes * 100);
        if ($rateMinor <= 0) {
            return null;
        }

        $prod = $amountMinor * $rateMinor;

        return (int) intdiv($prod, 100);
    }

    private function fromVesMinor(int $amountBsMinor, ?float $rateToVes): ?int
    {
        if ($amountBsMinor <= 0 || $rateToVes === null || $rateToVes <= 0) {
            return null;
        }

        $rateMinor = (int) round($rateToVes * 100);
        if ($rateMinor <= 0) {
            return null;
        }

        return (int) intdiv($amountBsMinor * 100, $rateMinor);
    }
}
