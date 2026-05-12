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
use App\Models\Receipt;
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

        $chargesData = $this->loadChargesDataForLocals($locals, $at, $filters, $id);
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
            'locals' => $this->resolveLocalRowsForHistory($locals),
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

    public function paymentHistoryForConcessionaire(int $id, ?DateTimeInterface $at = null, array $filters = []): array
    {
        $tz = (string) config('app.timezone', 'America/Caracas');
        $at = $at
            ? Carbon::parse($at->format('Y-m-d'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

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

        return [
            'header' => $this->loadConcessionaireHeader($id, $locals),
            'payments' => $this->listPaymentHistory('CONCESSIONAIRE', $id, $locals),
            'included_locals' => $this->resolveLocalRowsForHistory($locals),
        ];
    }

    public function paymentHistoryForLocal(int $id, ?DateTimeInterface $at = null, array $filters = []): array
    {
        $tz = (string) config('app.timezone', 'America/Caracas');
        $at = $at
            ? Carbon::parse($at->format('Y-m-d'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        return [
            'header' => $this->loadLocalHeader($id, $at),
            'payments' => $this->listPaymentHistory('LOCAL', $id, [$id]),
            'included_locals' => $this->resolveLocalRowsForHistory([$id]),
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
    private function loadChargesDataForLocals(array $localIds, Carbon $at, array $filters, ?int $concessionaireId = null): array
    {
        if ($concessionaireId !== null) {
            $q = Charge::query()->where(function ($query) use ($localIds, $concessionaireId) {
                $query->where(function ($sub) use ($concessionaireId) {
                    $sub->where('debtor_type', 'CONCESSIONAIRE')
                        ->where('debtor_id', $concessionaireId);
                });
                if (! empty($localIds)) {
                    $query->orWhere(function ($sub) use ($localIds) {
                        $sub->where('debtor_type', 'LOCAL')
                            ->whereIn('debtor_id', $localIds);
                    });
                }
            });
        } else {
            $q = Charge::query()
                ->where('debtor_type', 'LOCAL')
                ->whereIn('debtor_id', $localIds);
        }

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
            $q->whereDate('due_on', '<=', $at->toDateString());
        }

        $charges = $q->orderBy('period')->limit(500)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on', 'local_id', 'kind', 'debtor_type', 'debtor_id']);

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
            $debtorType = strtoupper((string) ($c->getAttribute('debtor_type') ?? 'LOCAL'));
            $debtorId = (int) ($c->getAttribute('debtor_id') ?? 0);
            $rawLocalId = $c->getAttribute('local_id');
            $localId = $rawLocalId !== null ? (int) $rawLocalId : null;

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

            if ($debtorType === 'LOCAL') {
                $localIdAgg = (int) ($localId ?? 0);
                if (! isset($byLocalAgg[$localIdAgg])) {
                    $byLocalAgg[$localIdAgg] = [
                        'local_id' => $localIdAgg,
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
                $byLocalAgg[$localIdAgg]['_currencies'][$ccyKey] = true;

                $byLocalAgg[$localIdAgg]['open_bs_minor'] += $outstanding;
                $byLocalAgg[$localIdAgg]['partial_applied_bs_minor'] += $allocated;
                $byLocalAgg[$localIdAgg]['net_due_bs_minor'] += $outstanding;
                $byLocalAgg[$localIdAgg]['open_minor'] += $outstandingOriginal;
            }

            $sumOpen += $outstanding;
            $isOverdue = $c->getAttribute('due_on') && (string) $c->getAttribute('due_on') <= $at->toDateString();
            if ($isOverdue) {
                $sumOverdue += $outstanding;
                if ($debtorType === 'LOCAL') {
                    $localIdAgg = (int) ($localId ?? 0);
                    if (! isset($byLocalAgg[$localIdAgg])) {
                        $byLocalAgg[$localIdAgg] = [
                            'local_id' => $localIdAgg,
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
                    $byLocalAgg[$localIdAgg]['overdue_bs_minor'] += $outstanding;
                    $byLocalAgg[$localIdAgg]['overdue_minor'] += $outstandingOriginal;
                }
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
                'debtor_type' => $debtorType,
                'debtor_id' => $debtorId,
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
                $localsById[$lid] = $this->compactLocalLabel($code, $name);
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
            $lid = null;
            if ($row['local_id'] !== null) {
                $lid = (int) $row['local_id'];
            }
            $row['local_label'] = $lid !== null && $lid > 0 ? ($localsById[$lid] ?? null) : null;
            $row['local_code'] = $lid !== null && $lid > 0 ? ($localCodesById[$lid] ?? null) : null;
            $row['local_type_name'] = $lid !== null && $lid > 0 ? ($localTypesById[$lid] ?? null) : null;

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
            $isOverdue = (string) $r['due_on'] <= $at->toDateString();

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
     * @param  array<int>  $paymentIds
     * @param  array<int>  $localIds
     * @return array<int, array<string, mixed>>
     */
    private function resolvePaymentCrossSummary(array $paymentIds, array $localIds = []): array
    {
        if (empty($paymentIds)) {
            return [];
        }

        $rows = DB::table('payment_allocations as pa')
            ->join('charges as c', 'c.id', '=', 'pa.charge_id')
            ->leftJoin('locals as l', 'l.id', '=', 'c.local_id')
            ->whereIn('pa.payment_id', $paymentIds)
            ->when(! empty($localIds), fn ($query) => $query->whereIn('c.local_id', $localIds))
            ->orderBy('pa.payment_id')
            ->orderBy('c.local_id')
            ->orderBy('c.period')
            ->get([
                'pa.payment_id',
                'pa.amount_bs_minor',
                'c.local_id',
                'c.kind',
                'c.period',
                'l.code as local_code',
                'l.name as local_name',
            ]);

        $summary = [];
        foreach ($rows as $row) {
            $paymentId = (int) ($row->payment_id ?? 0);
            if ($paymentId <= 0) {
                continue;
            }

            $localId = is_numeric($row->local_id) ? (int) $row->local_id : 0;
            $localLabel = $this->compactLocalLabel((string) ($row->local_code ?? ''), (string) ($row->local_name ?? ''));
            $concept = $this->paymentHistoryConceptLabel((string) ($row->kind ?? ''));
            $periodLabel = '';
            $periodRaw = (string) ($row->period ?? '');
            if ($periodRaw !== '') {
                try {
                    $periodLabel = Carbon::parse($periodRaw)->locale('es')->translatedFormat('m/Y');
                } catch (\Throwable) {
                    $periodLabel = $periodRaw;
                }
            }

            if (! isset($summary[$paymentId])) {
                $summary[$paymentId] = [
                    'crossed_bs_minor' => 0,
                    'crossed_charge_count' => 0,
                    'local_context' => ['local_ids' => [], 'local_labels' => []],
                    '_groups' => [],
                ];
            }

            $summary[$paymentId]['crossed_bs_minor'] += (int) ($row->amount_bs_minor ?? 0);
            $summary[$paymentId]['crossed_charge_count']++;
            if ($localId > 0) {
                $summary[$paymentId]['local_context']['local_ids'][$localId] = $localId;
                if ($localLabel !== '') {
                    $summary[$paymentId]['local_context']['local_labels'][$localLabel] = $localLabel;
                }
            }

            $groupKey = md5($localLabel.'|'.$concept);
            if (! isset($summary[$paymentId]['_groups'][$groupKey])) {
                $summary[$paymentId]['_groups'][$groupKey] = [
                    'local' => $localLabel,
                    'concept' => $concept,
                    'periods' => [],
                ];
            }
            if ($periodLabel !== '') {
                $summary[$paymentId]['_groups'][$groupKey]['periods'][$periodLabel] = $periodLabel;
            }
        }

        foreach ($summary as $paymentId => $row) {
            $items = [];
            foreach ($row['_groups'] as $group) {
                $periods = array_values($group['periods']);
                $periodText = match (count($periods)) {
                    0 => '',
                    1, 2, 3 => implode(', ', $periods),
                    default => implode(', ', array_slice($periods, 0, 3)).' +'.(count($periods) - 3).' más',
                };
                $label = trim(($group['local'] !== '' ? $group['local'].' · ' : '').$group['concept']);
                if ($periodText !== '') {
                    $label .= ' '.$periodText;
                }
                if ($label !== '') {
                    $items[] = $label;
                }
            }
            $summary[$paymentId]['local_context'] = [
                'local_ids' => array_map('intval', $row['local_context']['local_ids']),
                'local_labels' => $row['local_context']['local_labels'],
            ];
            $summary[$paymentId]['cross_summary'] = match (count($items)) {
                0 => 'Sin aplicación registrada',
                1, 2, 3 => implode('; ', $items),
                default => implode('; ', array_slice($items, 0, 3)).' +'.(count($items) - 3).' más',
            };
            unset($summary[$paymentId]['_groups']);
        }

        return $summary;
    }

    private function compactLocalLabel(string $code, string $name): string
    {
        $code = trim($code);
        $name = trim($name);

        if ($code !== '' && $name !== '' && mb_strtoupper($code) === mb_strtoupper($name)) {
            return $code;
        }

        if ($code !== '' && $name !== '') {
            return $code.' • '.$name;
        }

        return $code !== '' ? $code : $name;
    }

    private function paymentHistoryConceptLabel(string $kind): string
    {
        $kind = strtoupper($kind);
        if (str_contains($kind, 'CONDO')) {
            return 'Condominio';
        }
        if ($kind === 'RENT_EUR_FIXED') {
            return 'Alquiler fijo';
        }
        if (str_contains($kind, 'RENT')) {
            return 'Tasa de uso';
        }
        if ($kind === 'FINE') {
            return 'Multa';
        }
        if ($kind === 'ADJ') {
            return 'Gasto fijo';
        }
        if ($kind === 'CESION_DERECHOS') {
            return 'Cesión de derechos';
        }

        return 'Cargo';
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
     * @param  'CONCESSIONAIRE'|'LOCAL'  $scopeType
     * @param  array<int>  $localIds
     * @return array<int, array<string, mixed>>
     */
    private function listPaymentHistory(string $scopeType, int $scopeId, array $localIds = []): array
    {
        $paymentIds = $this->resolvePaymentIdsForHistory($scopeType, $scopeId, $localIds);
        if (empty($paymentIds)) {
            return [];
        }

        $filteredLocalIds = array_values(array_filter(array_unique($localIds), static fn (int $value): bool => $value > 0));
        $paymentLocalContext = $this->resolvePaymentLocalContext($paymentIds);
        $crossSummaryByPayment = $this->resolvePaymentCrossSummary($paymentIds, $filteredLocalIds);
        $selectedLocalLabels = [];
        foreach ($this->resolveLocalRowsForHistory($filteredLocalIds) as $local) {
            $localId = (int) $local['id'];
            if ($localId <= 0) {
                continue;
            }
            $selectedLocalLabels[$localId] = $this->compactLocalLabel(
                (string) $local['code'],
                (string) $local['name'],
            );
        }
        $receiptByPayment = Receipt::query()
            ->whereIn('payment_id', $paymentIds)
            ->where('status', 'ACTIVE')
            ->where(function ($q) {
                $q->where('scope', 'PAYMENT')->orWhereNull('scope');
            })
            ->orderByDesc('id')
            ->get(['id', 'payment_id', 'receipt_number', 'issued_at'])
            ->unique('payment_id')
            ->keyBy('payment_id');

        $appliedByPayment = PaymentAllocation::query()
            ->whereIn('payment_id', $paymentIds)
            ->selectRaw('payment_id, SUM(amount_bs_minor) as total_bs_minor')
            ->groupBy('payment_id')
            ->pluck('total_bs_minor', 'payment_id');

        $rows = Payment::query()
            ->leftJoin('payment_statuses as ps', 'ps.id', '=', 'payments.payment_status_id')
            ->whereIn('payments.id', $paymentIds)
            ->orderByRaw('COALESCE(payments.paid_on, DATE(payments.created_at)) DESC')
            ->orderByDesc('payments.id')
            ->limit(300)
            ->get([
                'payments.id',
                'payments.debtor_type',
                'payments.debtor_id',
                'payments.local_id',
                'payments.method',
                'payments.reference',
                'payments.amount_bs_minor',
                'payments.paid_on',
                'payments.created_at',
                'payments.voided_at',
                'ps.code as payment_status_code',
                'ps.name as payment_status_name',
            ]);

        return $rows->map(function ($payment) use ($paymentLocalContext, $crossSummaryByPayment, $receiptByPayment, $appliedByPayment, $scopeType, $filteredLocalIds, $selectedLocalLabels) {
            $paymentId = (int) $payment->getAttribute('id');
            $amountBsMinor = (int) ($payment->getAttribute('amount_bs_minor') ?? 0);
            $appliedBsMinor = (int) ($appliedByPayment[$paymentId] ?? 0);
            $paymentLocalIdRaw = $payment->getAttribute('local_id');
            $paymentLocalId = is_numeric($paymentLocalIdRaw) ? (int) $paymentLocalIdRaw : null;
            $context = ! empty($filteredLocalIds)
                ? ($crossSummaryByPayment[$paymentId]['local_context'] ?? ['local_ids' => [], 'local_labels' => []])
                : ($paymentLocalContext[$paymentId] ?? ['local_ids' => [], 'local_labels' => []]);

            if (! empty($filteredLocalIds) && $context['local_ids'] === []) {
                $debtorType = strtoupper((string) ($payment->getAttribute('debtor_type') ?? ''));
                $debtorId = (int) ($payment->getAttribute('debtor_id') ?? 0);
                $directLocalId = null;
                if ($paymentLocalId !== null && in_array($paymentLocalId, $filteredLocalIds, true)) {
                    $directLocalId = $paymentLocalId;
                } elseif ($debtorType === 'LOCAL' && in_array($debtorId, $filteredLocalIds, true)) {
                    $directLocalId = $debtorId;
                }

                if ($directLocalId === null) {
                    return null;
                }

                $context = [
                    'local_ids' => [$directLocalId],
                    'local_labels' => [($selectedLocalLabels[$directLocalId] ?? ('Local #'.$directLocalId))],
                ];
            }

            $localLabels = array_values(array_unique(array_filter(array_map(fn ($label) => is_string($label) ? trim($label) : '', (array) ($context['local_labels'] ?? [])))));
            $localIds = array_values(array_unique(array_filter(array_map(fn ($value) => is_numeric($value) ? (int) $value : 0, (array) ($context['local_ids'] ?? [])))));
            $localSummary = match (count($localLabels)) {
                0 => $scopeType === 'CONCESSIONAIRE' ? 'Cesionario' : 'Sin local asociado',
                1 => $localLabels[0],
                2 => implode(', ', $localLabels),
                default => $localLabels[0].', '.$localLabels[1].' +'.(count($localLabels) - 2),
            };
            $receipt = $receiptByPayment->get($paymentId);
            $crossSummary = $crossSummaryByPayment[$paymentId] ?? null;

            return [
                'payment_id' => $paymentId,
                'debtor_type' => (string) ($payment->getAttribute('debtor_type') ?? ''),
                'debtor_id' => (int) ($payment->getAttribute('debtor_id') ?? 0),
                'method' => (string) ($payment->getAttribute('method') ?? ''),
                'reference' => (string) ($payment->getAttribute('reference') ?? ''),
                'status' => (string) ($payment->getAttribute('payment_status_code') ?? ''),
                'status_code' => (string) ($payment->getAttribute('payment_status_code') ?? ''),
                'status_name' => (string) ($payment->getAttribute('payment_status_name') ?? ''),
                'amount_bs_minor' => $amountBsMinor,
                'applied_bs_minor' => $appliedBsMinor,
                'available_bs_minor' => max(0, $amountBsMinor - $appliedBsMinor),
                'paid_on' => (string) ($payment->getAttribute('paid_on') ?? ''),
                'created_at' => (string) ($payment->getAttribute('created_at') ?? ''),
                'voided_at' => (string) ($payment->getAttribute('voided_at') ?? ''),
                'local_ids' => $localIds,
                'local_labels' => $localLabels,
                'local_summary_label' => $localSummary,
                'crossed_bs_minor' => (int) ($crossSummary['crossed_bs_minor'] ?? 0),
                'crossed_charge_count' => (int) ($crossSummary['crossed_charge_count'] ?? 0),
                'cross_summary' => (string) ($crossSummary['cross_summary'] ?? 'Sin aplicación registrada'),
                'receipt_id' => $receipt ? (int) $receipt->getKey() : null,
                'receipt_number' => $receipt ? (string) ($receipt->getAttribute('receipt_number') ?? '') : null,
                'receipt_issued_at' => $receipt ? (string) ($receipt->getAttribute('issued_at') ?? '') : null,
            ];
        })->filter()->values()->all();
    }

    /**
     * @param  'CONCESSIONAIRE'|'LOCAL'  $scopeType
     * @param  array<int>  $localIds
     * @return array<int>
     */
    private function resolvePaymentIdsForHistory(string $scopeType, int $scopeId, array $localIds = []): array
    {
        $directIds = Payment::query()
            ->where(function ($query) use ($scopeType, $scopeId, $localIds) {
                if ($scopeType === 'CONCESSIONAIRE') {
                    if (! empty($localIds)) {
                        $query->where(function ($sub) use ($localIds) {
                            $sub->where(function ($inner) use ($localIds) {
                                $inner->where('debtor_type', 'LOCAL')
                                    ->whereIn('debtor_id', $localIds);
                            })->orWhereIn('local_id', $localIds);
                        });
                    } else {
                        $query->where(function ($sub) use ($scopeId) {
                            $sub->where('debtor_type', 'CONCESSIONAIRE')
                                ->where('debtor_id', $scopeId);
                        });
                    }
                } else {
                    $query->where(function ($sub) use ($scopeId) {
                        $sub->where('debtor_type', 'LOCAL')
                            ->where('debtor_id', $scopeId);
                    })->orWhere('local_id', $scopeId);
                }
            })
            ->pluck('id')
            ->all();

        $allocationIds = PaymentAllocation::query()
            ->join('charges as c', 'c.id', '=', 'payment_allocations.charge_id')
            ->where(function ($query) use ($scopeType, $scopeId, $localIds) {
                if ($scopeType === 'CONCESSIONAIRE') {
                    $query->where(function ($sub) use ($scopeId) {
                        $sub->where('c.debtor_type', 'CONCESSIONAIRE')
                            ->where('c.debtor_id', $scopeId);
                    });
                    if (! empty($localIds)) {
                        $query->orWhereIn('c.local_id', $localIds)
                            ->orWhere(function ($sub) use ($localIds) {
                                $sub->where('c.debtor_type', 'LOCAL')
                                    ->whereIn('c.debtor_id', $localIds);
                            });
                    }
                } else {
                    $query->where('c.local_id', $scopeId)
                        ->orWhere(function ($sub) use ($scopeId) {
                            $sub->where('c.debtor_type', 'LOCAL')
                                ->where('c.debtor_id', $scopeId);
                        });
                }
            })
            ->pluck('payment_allocations.payment_id')
            ->all();

        $creditIds = CreditApplication::query()
            ->join('charges as c', 'c.id', '=', 'credit_applications.charge_id')
            ->where(function ($query) use ($scopeType, $scopeId, $localIds) {
                if ($scopeType === 'CONCESSIONAIRE') {
                    $query->where(function ($sub) use ($scopeId) {
                        $sub->where('c.debtor_type', 'CONCESSIONAIRE')
                            ->where('c.debtor_id', $scopeId);
                    });
                    if (! empty($localIds)) {
                        $query->orWhereIn('c.local_id', $localIds)
                            ->orWhere(function ($sub) use ($localIds) {
                                $sub->where('c.debtor_type', 'LOCAL')
                                    ->whereIn('c.debtor_id', $localIds);
                            });
                    }
                } else {
                    $query->where('c.local_id', $scopeId)
                        ->orWhere(function ($sub) use ($scopeId) {
                            $sub->where('c.debtor_type', 'LOCAL')
                                ->where('c.debtor_id', $scopeId);
                        });
                }
            })
            ->pluck('credit_applications.payment_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($directIds, $allocationIds, $creditIds))));
    }

    /**
     * @param  array<int>  $paymentIds
     * @return array<int, array{local_ids: array<int>, local_labels: array<int, string>}>
     */
    private function resolvePaymentLocalContext(array $paymentIds): array
    {
        if (empty($paymentIds)) {
            return [];
        }

        $map = [];
        $rememberLocal = static function (array &$items, int $paymentId, ?int $localId): void {
            if ($localId === null || $localId <= 0) {
                return;
            }
            if (! isset($items[$paymentId])) {
                $items[$paymentId] = [];
            }
            $items[$paymentId][$localId] = $localId;
        };

        Payment::query()
            ->whereIn('id', $paymentIds)
            ->get(['id', 'local_id'])
            ->each(function ($payment) use (&$map, $rememberLocal) {
                $localId = $payment->getAttribute('local_id');
                $rememberLocal($map, (int) $payment->getKey(), is_numeric($localId) ? (int) $localId : null);
            });

        DB::table('payment_allocations as pa')
            ->join('charges as c', 'c.id', '=', 'pa.charge_id')
            ->whereIn('pa.payment_id', $paymentIds)
            ->whereNotNull('c.local_id')
            ->get(['pa.payment_id', 'c.local_id'])
            ->each(function ($row) use (&$map, $rememberLocal) {
                $localId = isset($row->local_id) ? (int) $row->local_id : null;
                $rememberLocal($map, (int) $row->payment_id, $localId);
            });

        DB::table('credit_applications as ca')
            ->join('charges as c', 'c.id', '=', 'ca.charge_id')
            ->whereIn('ca.payment_id', $paymentIds)
            ->whereNotNull('c.local_id')
            ->get(['ca.payment_id', 'c.local_id'])
            ->each(function ($row) use (&$map, $rememberLocal) {
                $localId = isset($row->local_id) ? (int) $row->local_id : null;
                $rememberLocal($map, (int) $row->payment_id, $localId);
            });

        $allLocalIds = [];
        foreach ($map as $localMap) {
            foreach ($localMap as $localId) {
                $allLocalIds[(int) $localId] = (int) $localId;
            }
        }
        $allLocalIds = array_values($allLocalIds);

        $labelsByLocal = empty($allLocalIds)
            ? []
            : LocalModel::query()
                ->whereIn('id', $allLocalIds)
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(function ($local) {
                    $code = (string) ($local->getAttribute('code') ?? '');
                    $name = (string) ($local->getAttribute('name') ?? '');
                    $label = trim(($code ? $code.' • ' : '').$name);

                    return [(int) $local->getKey() => ($label !== '' ? $label : 'Local #'.$local->getKey())];
                })
                ->all();

        $result = [];
        foreach ($paymentIds as $paymentId) {
            $ids = array_values(array_unique(array_map('intval', array_values($map[$paymentId] ?? []))));
            sort($ids);
            $labels = array_values(array_filter(array_map(fn ($localId) => $labelsByLocal[$localId] ?? null, $ids)));
            $result[(int) $paymentId] = [
                'local_ids' => $ids,
                'local_labels' => $labels,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int>  $localIds
     * @return array<int, array{id:int, code:string, name:string}>
     */
    private function resolveLocalRowsForHistory(array $localIds): array
    {
        $localIds = array_values(array_filter(array_unique($localIds), static fn (int $value): bool => $value > 0));
        if (empty($localIds)) {
            return [];
        }

        return LocalModel::query()
            ->whereIn('id', $localIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($local) => [
                'id' => (int) $local->getKey(),
                'code' => (string) ($local->getAttribute('code') ?? ''),
                'name' => (string) ($local->getAttribute('name') ?? ''),
            ])
            ->all();
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
     * Convierte monto en moneda extranjera a Bs usando la tasa FX.
     * amount (2dp) * rate (2dp) => 4dp, redondear a 2dp.
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

        // Política BCV: redondear a 2 decimales basándose en el tercer decimal
        $rateMinor = (int) round($rateToVes * 100);
        if ($rateMinor <= 0) {
            return null;
        }

        $prod = $amountMinor * $rateMinor;

        return (int) round($prod / 100);
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

        return (int) round($amountBsMinor * 100 / $rateMinor);
    }

    /**
     * Generar datos para el reporte de balance (ledger tradicional).
     *
     * @param  string  $scopeType  'concessionaire' or 'local'
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getBalanceData(string $scopeType, int $scopeId, array $filters = []): array
    {
        $at = isset($filters['at']) && $filters['at'] instanceof DateTimeInterface ? Carbon::parse($filters['at']->format('Y-m-d')) : Carbon::now();
        $profile = $scopeType === 'local'
            ? $this->forLocal($scopeId, $at, $filters)
            : $this->forConcessionaire($scopeId, $at, $filters);

        $openCharges = array_values((array) ($profile['tables']['charges_open'] ?? []));
        $chargesById = [];
        foreach ($openCharges as $charge) {
            $chargeId = (int) ($charge['charge_id'] ?? 0);
            if ($chargeId > 0) {
                $chargesById[$chargeId] = $charge;
            }
        }
        $openChargeIds = array_keys($chargesById);
        $balanceFilters = $filters;
        if ($scopeType === 'concessionaire' && ! isset($balanceFilters['local_ids'])) {
            $balanceFilters['local_ids'] = array_values(array_filter(array_map(
                fn (array $row): int => (int) ($row['local_id'] ?? 0),
                (array) ($profile['by_local'] ?? []),
            )));
        }
        $ledgerChargeIds = $this->resolveBalanceLedgerChargeIds($scopeType, $scopeId, $balanceFilters);
        $extraChargeIds = array_values(array_diff($ledgerChargeIds, $openChargeIds));
        foreach ($this->loadBalanceChargesByIds($extraChargeIds, $at, $balanceFilters) as $charge) {
            $chargeId = (int) ($charge['charge_id'] ?? 0);
            if ($chargeId > 0) {
                $chargesById[$chargeId] = $charge;
            }
        }
        $charges = array_values($chargesById);
        $chargeIds = array_values(array_filter(array_map(fn (array $charge): int => (int) ($charge['charge_id'] ?? 0), $charges)));
        $receiptByPayment = Receipt::query()
            ->whereNotNull('payment_id')
            ->selectRaw('payment_id, MAX(receipt_number) as receipt_number')
            ->groupBy('payment_id');
        $paymentRows = empty($chargeIds)
            ? collect()
            : PaymentAllocation::query()
                ->whereIn('payment_allocations.charge_id', $chargeIds)
                ->whereNull('payment_allocations.deleted_at')
                ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
                ->whereNull('p.deleted_at')
                ->whereNull('p.voided_at')
                ->leftJoinSub($receiptByPayment, 'r', 'r.payment_id', '=', 'p.id')
                ->get([
                    'payment_allocations.charge_id',
                    'payment_allocations.amount_bs_minor',
                    'p.id as payment_id',
                    'p.paid_on',
                    'p.reference',
                    'r.receipt_number',
                ])
                ->groupBy('charge_id');

        $movements = [];
        $totalsByCurrency = [];

        foreach ($charges as $charge) {
            $chargeId = (int) ($charge['charge_id'] ?? 0);
            $currency = strtoupper((string) ($charge['currency'] ?? 'VES'));
            $period = (string) ($charge['period'] ?? '');
            $dueOn = (string) ($charge['due_on'] ?? '');
            $date = $period !== '' ? $period : $dueOn;
            $concept = $this->economicProfileConceptLabel((string) ($charge['kind'] ?? ''), $currency);
            $reference = $period !== '' ? Carbon::parse($period)->format('Y-m') : '#'.$chargeId;
            $localLabel = trim((string) ($charge['local_label'] ?? $charge['local_code'] ?? ''));
            $description = trim($concept.($localLabel !== '' ? ' · '.$localLabel : ''));
            $amountBs = (int) ($charge['amount_bs_minor'] ?? 0);
            $allocatedBs = (int) ($charge['allocated_bs_minor'] ?? 0);
            $creditedBs = (int) ($charge['credited_bs_minor'] ?? 0);
            $outstandingBs = (int) ($charge['outstanding_bs_minor'] ?? 0);
            $ledgerDebitBs = max($amountBs, $outstandingBs + $allocatedBs + $creditedBs);
            $amountMinor = (int) ($charge['amount_minor'] ?? 0);
            $outstandingMinor = (int) ($charge['outstanding_minor'] ?? 0);

            if (! isset($totalsByCurrency[$currency])) {
                $totalsByCurrency[$currency] = [
                    'charges_minor' => 0,
                    'outstanding_minor' => 0,
                ];
            }
            $totalsByCurrency[$currency]['charges_minor'] += $amountMinor;
            $totalsByCurrency[$currency]['outstanding_minor'] += $outstandingMinor;

            $movements[] = [
                'date' => $date,
                'sort' => $date.'|1|'.$chargeId,
                'type' => 'Cargo',
                'reference' => $reference,
                'description' => $description,
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'debit' => $ledgerDebitBs,
                'credit' => 0,
                'balance' => 0,
            ];

            foreach (($paymentRows->get($chargeId) ?? collect()) as $payment) {
                $paidOn = (string) ($payment->getAttribute('paid_on') ?? '');
                $paymentReference = (string) ($payment->getAttribute('receipt_number') ?: $payment->getAttribute('reference') ?: '#'.$payment->getAttribute('payment_id'));
                $creditBs = (int) ($payment->getAttribute('amount_bs_minor') ?? 0);
                if ($creditBs <= 0) {
                    continue;
                }
                $movements[] = [
                    'date' => $paidOn !== '' ? $paidOn : $date,
                    'sort' => ($paidOn !== '' ? $paidOn : $date).'|2|'.$chargeId,
                    'type' => 'Pago',
                    'reference' => $paymentReference,
                    'description' => 'Pago aplicado a '.$concept,
                    'currency' => 'VES',
                    'amount_minor' => $creditBs,
                    'debit' => 0,
                    'credit' => $creditBs,
                    'balance' => 0,
                ];
            }

            if ($creditedBs > 0) {
                $movements[] = [
                    'date' => $date,
                    'sort' => $date.'|3|'.$chargeId,
                    'type' => 'Crédito',
                    'reference' => $reference,
                    'description' => 'Crédito aplicado a '.$concept,
                    'currency' => 'VES',
                    'amount_minor' => $creditedBs,
                    'debit' => 0,
                    'credit' => $creditedBs,
                    'balance' => 0,
                ];
            }
        }

        usort($movements, fn ($a, $b) => strcmp((string) $a['sort'], (string) $b['sort']));

        $balance = 0;
        foreach ($movements as &$movement) {
            $balance += $movement['debit'] - $movement['credit'];
            $movement['balance'] = $balance;
            unset($movement['sort']);
        }

        unset($movement);

        $totalChargesBs = array_sum(array_map(function ($charge): int {
            $amountBs = (int) ($charge['amount_bs_minor'] ?? 0);
            $allocatedBs = (int) ($charge['allocated_bs_minor'] ?? 0);
            $creditedBs = (int) ($charge['credited_bs_minor'] ?? 0);
            $outstandingBs = (int) ($charge['outstanding_bs_minor'] ?? 0);

            return max($amountBs, $outstandingBs + $allocatedBs + $creditedBs);
        }, $charges));
        $totalPaymentsBs = array_sum(array_map(fn ($charge): int => (int) ($charge['allocated_bs_minor'] ?? 0), $charges));
        $totalCreditsBs = array_sum(array_map(fn ($charge): int => (int) ($charge['credited_bs_minor'] ?? 0), $charges));

        return [
            'summary' => [
                'total_charges_bs' => $totalChargesBs,
                'total_payments_bs' => $totalPaymentsBs,
                'total_credits_bs' => $totalCreditsBs,
                'final_balance_bs' => $balance,
            ],
            'totals_by_currency' => $totalsByCurrency,
            'movements' => $movements,
            'header' => $profile['header'] ?? [],
            'included_local_codes' => array_values(array_filter(array_map(fn (array $row): string => (string) ($row['local_code'] ?? ''), (array) ($profile['by_local'] ?? [])))),
            'concessionaire_name' => $profile['header']['concessionaire']['full_name'] ?? $profile['header']['full_name'] ?? null,
        ];
    }

    private function resolveBalanceLedgerChargeIds(string $scopeType, int $scopeId, mixed $filters): mixed
    {
        $filters = is_array($filters) ? $filters : [];
        $query = PaymentAllocation::query()
            ->select('payment_allocations.charge_id')
            ->distinct()
            ->whereNull('payment_allocations.deleted_at')
            ->whereNotNull('payment_allocations.charge_id')
            ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->join('charges as ch', 'ch.id', '=', 'payment_allocations.charge_id')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->whereNull('ch.deleted_at');

        if ($scopeType === 'local') {
            $query->where(function ($where) use ($scopeId): void {
                $where->where('ch.local_id', $scopeId)
                    ->orWhere(function ($sub) use ($scopeId): void {
                        $sub->where('ch.debtor_type', 'LOCAL')
                            ->where('ch.debtor_id', $scopeId);
                    });
            });
        } else {
            $localIds = [];
            if (isset($filters['local_ids']) && is_array($filters['local_ids']) && count($filters['local_ids']) > 0) {
                $localIds = array_values(array_unique(array_filter(array_map(fn ($value): int => is_numeric($value) ? (int) $value : 0, $filters['local_ids']))));
            }

            $query->where(function ($where) use ($localIds, $scopeId): void {
                $where->where(function ($sub) use ($scopeId): void {
                    $sub->where('p.debtor_type', 'CONCESSIONAIRE')
                        ->where('p.debtor_id', $scopeId);
                })->orWhere(function ($sub) use ($scopeId): void {
                    $sub->where('ch.debtor_type', 'CONCESSIONAIRE')
                        ->where('ch.debtor_id', $scopeId);
                });

                if (! empty($localIds)) {
                    $where->orWhere(function ($sub) use ($localIds): void {
                        $sub->whereIn('ch.local_id', $localIds)
                            ->orWhere(function ($nested) use ($localIds): void {
                                $nested->where('ch.debtor_type', 'LOCAL')
                                    ->whereIn('ch.debtor_id', $localIds);
                            });
                    });
                }
            });
        }

        return $query->pluck('payment_allocations.charge_id')
            ->filter()
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function loadBalanceChargesByIds(mixed $chargeIds, Carbon $at, mixed $filters): mixed
    {
        $chargeIds = is_array($chargeIds) ? $chargeIds : [];
        $filters = is_array($filters) ? $filters : [];
        $chargeIds = array_values(array_unique(array_filter(array_map(fn ($value): int => (int) $value, $chargeIds))));
        if (empty($chargeIds)) {
            return [];
        }

        $query = Charge::query()
            ->whereIn('id', $chargeIds)
            ->whereNull('deleted_at');

        if (! empty($filters['currency'])) {
            $query->where('currency', strtoupper((string) $filters['currency']));
        }
        if (! empty($filters['kind'])) {
            $query->where('kind', strtoupper((string) $filters['kind']));
        }
        if (! empty($filters['period_from'])) {
            $from = Carbon::createFromFormat('Y-m', (string) $filters['period_from'])->startOfMonth()->toDateString();
            $query->whereDate('period', '>=', $from);
        }
        if (! empty($filters['period_to'])) {
            $to = Carbon::createFromFormat('Y-m', (string) $filters['period_to'])->endOfMonth()->toDateString();
            $query->whereDate('period', '<=', $to);
        }
        if (! empty($filters['overdue_only'])) {
            $query->whereDate('due_on', '<=', $at->toDateString());
        }

        $charges = $query->orderBy('period')->get([
            'id',
            'currency',
            'amount_minor',
            'amount_bs_minor_issued',
            'period',
            'due_on',
            'local_id',
            'kind',
            'debtor_type',
            'debtor_id',
            'charge_status_id',
        ]);
        if ($charges->isEmpty()) {
            return [];
        }

        $ids = $charges->pluck('id')->all();
        $allocRows = PaymentAllocation::query()
            ->whereIn('payment_allocations.charge_id', $ids)
            ->whereNull('payment_allocations.deleted_at')
            ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->get(['payment_allocations.charge_id', 'payment_allocations.amount_bs_minor']);

        /** @var FxRateServiceInterface $fx */
        $fx = $this->container->get(FxRateServiceInterface::class);

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

        $statusCodes = ChargeStatus::query()
            ->whereIn('id', $charges->pluck('charge_status_id')->filter()->unique()->values()->all())
            ->pluck('code', 'id');
        $localIds = $charges->pluck('local_id')->filter()->unique()->values()->all();
        $locals = empty($localIds)
            ? collect()
            : LocalModel::query()->whereIn('id', $localIds)->get(['id', 'code', 'name'])->keyBy('id');

        return $charges->map(function (Charge $charge) use ($allocRows, $creditByChargeBs, $fx, $statusCodes, $locals, $at): array {
            $chargeId = (int) $charge->getAttribute('id');
            $currency = strtoupper((string) ($charge->getAttribute('currency') ?? 'VES'));
            $amountMinor = (int) $charge->getAttribute('amount_minor');
            $allocated = (int) $allocRows->where('charge_id', $chargeId)->sum('amount_bs_minor');
            $credited = (int) ($creditByChargeBs[$chargeId] ?? 0);
            $issuedBs = $charge->getAttribute('amount_bs_minor_issued');
            $amountBsMinor = is_numeric($issuedBs) ? (int) $issuedBs : null;
            if ($amountBsMinor === null || $amountBsMinor <= 0) {
                if ($currency === 'VES' || $currency === '') {
                    $amountBsMinor = $amountMinor;
                } else {
                    $rate = $fx->resolveAt($currency, $at);
                    $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                    $amountBsMinor = $this->toVesMinor($amountMinor, $rateToVes) ?? 0;
                }
            }

            $statusCode = strtoupper((string) ($statusCodes[(int) $charge->getAttribute('charge_status_id')] ?? ''));
            $isSettled = in_array($statusCode, ['SETTLED', 'PAID', 'CANCELLED', 'CANCELED'], true);
            if ($isSettled) {
                if (($allocated + $credited) > 0) {
                    $amountBsMinor = $allocated + $credited;
                }
                $outstandingBsMinor = 0;
                $outstandingMinor = 0;
            } else {
                $outstandingBsMinor = max(0, $amountBsMinor - $allocated - $credited);
                $outstandingMinor = $currency === 'VES' ? $outstandingBsMinor : 0;
            }

            $rawLocalId = $charge->getAttribute('local_id');
            $localId = $rawLocalId !== null ? (int) $rawLocalId : null;
            $local = $localId !== null ? $locals->get($localId) : null;
            $localCode = $local ? (string) ($local->getAttribute('code') ?? '') : null;
            $localName = $local ? (string) ($local->getAttribute('name') ?? '') : null;

            return [
                'charge_id' => $chargeId,
                'local_id' => $localId,
                'period' => (string) $charge->getAttribute('period'),
                'due_on' => (string) ($charge->getAttribute('due_on') ?? ''),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'amount_bs_minor' => $amountBsMinor,
                'allocated_bs_minor' => $allocated,
                'credited_bs_minor' => $credited,
                'outstanding_bs_minor' => $outstandingBsMinor,
                'outstanding_minor' => $outstandingMinor,
                'kind' => (string) ($charge->getAttribute('kind') ?? ''),
                'debtor_type' => strtoupper((string) ($charge->getAttribute('debtor_type') ?? 'LOCAL')),
                'debtor_id' => (int) ($charge->getAttribute('debtor_id') ?? 0),
                'local_label' => $localCode !== null ? $this->compactLocalLabel($localCode, (string) $localName) : null,
                'local_code' => $localCode,
                'local_type_name' => null,
            ];
        })->all();
    }

    private function economicProfileConceptLabel(string $kind, string $currency): string
    {
        $kind = strtoupper($kind);
        $currency = strtoupper($currency);

        if (str_contains($kind, 'CONDO')) {
            return 'Condominio';
        }
        if ($kind === 'RENT_EUR_M2' || ($currency === 'EUR' && str_contains($kind, 'RENT'))) {
            return 'Tasa de uso';
        }
        if ($kind === 'RENT_EUR_FIXED' || ($currency === 'USD' && str_contains($kind, 'RENT'))) {
            return 'Alquiler fijo';
        }
        if ($kind === 'FINE') {
            return 'Cargo por multa';
        }
        if ($kind === 'ADJ') {
            return 'Gasto Fijo de Mantenimiento';
        }
        if ($kind === 'CESION_DERECHOS') {
            return 'Cesión de derechos';
        }

        return 'Cargo';
    }
}
