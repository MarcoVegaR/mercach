<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Charge;
use App\Support\FxConversionHelper;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocalsFinancialStatusQuery
{
    private mixed $filters = [];

    private string $searchQuery = '';

    public function __construct(
        private ?FxConversionHelper $fxHelper = null,
    ) {}

    public function withFilters(mixed $filters): self
    {
        $this->filters = is_array($filters) ? $filters : [];

        return $this;
    }

    public function search(string $query): self
    {
        $this->searchQuery = trim($query);

        return $this;
    }

    public function paginate(int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->buildQuery()
            ->paginate($perPage, [
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'l.area_m2',
                'm.name as market_name',
                'acl.contract_number',
                'cn.id as concessionaire_id',
                'cn.full_name as concessionaire_name',
                DB::raw('CASE WHEN cn.id IS NULL THEN NULL ELSE SUM(l.area_m2) OVER (PARTITION BY cn.id) END as concessionaire_total_area_m2'),
            ], 'page', $page)
            ->withQueryString();
    }

    public function get(int $limit = 5000): Collection
    {
        return $this->buildQuery()
            ->limit($limit)
            ->get([
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'l.area_m2',
                'm.name as market_name',
                'acl.contract_number',
                'cn.id as concessionaire_id',
                'cn.full_name as concessionaire_name',
                DB::raw('CASE WHEN cn.id IS NULL THEN NULL ELSE SUM(l.area_m2) OVER (PARTITION BY cn.id) END as concessionaire_total_area_m2'),
            ]);
    }

    public function transform(LengthAwarePaginator|Collection $results): Collection
    {
        $collection = $results instanceof LengthAwarePaginator
            ? collect($results->items())
            : $results;

        $localIds = $collection
            ->pluck('local_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $lastPaidRent = $this->lastPaidPeriodByPrefix($localIds, 'RENT');
        $lastPaidCondo = $this->lastPaidPeriodByPrefix($localIds, 'CONDO');

        $debt = $this->debtByType($localIds);
        $rentDebt = $debt['rent_minor_by_local'];
        $condoDebt = $debt['condo_minor_by_local'];
        $rentCurrency = $debt['rent_currency_by_local'];
        $condoCurrency = $debt['condo_currency_by_local'];
        $rentDebtBs = $debt['rent_bs_minor_by_local'];
        $condoDebtBs = $debt['condo_bs_minor_by_local'];

        return $collection->map(function ($row) use ($lastPaidRent, $lastPaidCondo, $rentDebt, $condoDebt, $rentCurrency, $condoCurrency, $rentDebtBs, $condoDebtBs) {
            $localId = (int) ($row->local_id ?? 0);
            $rentMinor = (int) ($rentDebt[$localId] ?? 0);
            $condoMinor = (int) ($condoDebt[$localId] ?? 0);
            $rentCur = (string) ($rentCurrency[$localId] ?? '');
            $condoCur = (string) ($condoCurrency[$localId] ?? '');
            $rentBsMinor = (int) ($rentDebtBs[$localId] ?? 0);
            $condoBsMinor = (int) ($condoDebtBs[$localId] ?? 0);
            $totalBsMinor = $rentBsMinor + $condoBsMinor;

            return [
                'local_id' => $localId,
                'local_code' => (string) ($row->local_code ?? ''),
                'local_name' => (string) ($row->local_name ?? ''),
                'market_name' => (string) ($row->market_name ?? ''),
                'contract_number' => (string) ($row->contract_number ?? ''),
                'concessionaire_id' => (int) ($row->concessionaire_id ?? 0),
                'concessionaire_name' => (string) ($row->concessionaire_name ?? ''),
                'concessionaire_total_area_m2' => $row->concessionaire_total_area_m2 !== null ? (float) $row->concessionaire_total_area_m2 : null,
                'area_m2' => $row->area_m2 !== null ? (float) $row->area_m2 : null,
                'last_paid_rent_period' => $lastPaidRent[$localId] ?? null,
                'last_paid_condo_period' => $lastPaidCondo[$localId] ?? null,
                'rent_debt_currency' => $rentCur !== '' ? $rentCur : null,
                'condo_debt_currency' => $condoCur !== '' ? $condoCur : null,
                'rent_debt_minor' => $rentMinor,
                'condo_debt_minor' => $condoMinor,
                'rent_debt' => $this->fmtCurrencyMinor($rentMinor),
                'condo_debt' => $this->fmtCurrencyMinor($condoMinor),
                'rent_debt_bs_minor' => $rentBsMinor,
                'condo_debt_bs_minor' => $condoBsMinor,
                'total_debt_bs_minor' => $totalBsMinor,
                'rent_debt_bs' => $this->fmtCurrencyMinor($rentBsMinor),
                'condo_debt_bs' => $this->fmtCurrencyMinor($condoBsMinor),
                'total_debt_bs' => $this->fmtCurrencyMinor($totalBsMinor),
            ];
        });
    }

    public function transformForExport(LengthAwarePaginator|Collection $results): Collection
    {
        $rows = $this->transform($results);

        return $rows
            ->groupBy(function (array $r): string {
                $cid = (int) ($r['concessionaire_id'] ?? 0);
                if ($cid > 0) {
                    return 'c:'.$cid;
                }

                $lid = (int) ($r['local_id'] ?? 0);

                return 'l:'.$lid;
            })
            ->map(function (Collection $group, string $groupKey): array {
                $first = (array) $group->first();
                $isNoConcessionaire = str_starts_with($groupKey, 'l:');

                $markets = $group->pluck('market_name')->filter()->unique()->values()->all();
                $locals = $group->pluck('local_code')->filter()->unique()->values()->all();
                $contracts = $group->pluck('contract_number')->filter()->unique()->values()->all();

                $rentCurrency = $group->pluck('rent_debt_currency')->filter()->unique()->values()->all();
                $condoCurrency = $group->pluck('condo_debt_currency')->filter()->unique()->values()->all();

                $rentCurrencyLabel = count($rentCurrency) === 1 ? (string) $rentCurrency[0] : (count($rentCurrency) > 1 ? 'MIXED' : '');
                $condoCurrencyLabel = count($condoCurrency) === 1 ? (string) $condoCurrency[0] : (count($condoCurrency) > 1 ? 'MIXED' : '');

                $lastPaidRent = $group->pluck('last_paid_rent_period')->filter()->sortDesc()->first();
                $lastPaidCondo = $group->pluck('last_paid_condo_period')->filter()->sortDesc()->first();

                $rentMinor = (int) $group->sum(fn (array $r) => (int) ($r['rent_debt_minor'] ?? 0));
                $condoMinor = (int) $group->sum(fn (array $r) => (int) ($r['condo_debt_minor'] ?? 0));
                $rentBsMinor = (int) $group->sum(fn (array $r) => (int) ($r['rent_debt_bs_minor'] ?? 0));
                $condoBsMinor = (int) $group->sum(fn (array $r) => (int) ($r['condo_debt_bs_minor'] ?? 0));
                $totalBsMinor = $rentBsMinor + $condoBsMinor;

                $concessionaireTotalArea = $group->pluck('concessionaire_total_area_m2')->filter()->max();

                $detail = $group
                    ->sortBy(fn (array $r) => strtolower((string) ($r['local_code'] ?? '')))
                    ->map(function (array $r): string {
                        $local = (string) ($r['local_code'] ?? '');
                        $area = $r['area_m2'] !== null ? (string) $r['area_m2'] : '—';
                        $rentPeriod = (string) ($r['last_paid_rent_period'] ?? '—');
                        $condoPeriod = (string) ($r['last_paid_condo_period'] ?? '—');
                        $rentCur = (string) ($r['rent_debt_currency'] ?? '');
                        $condoCur = (string) ($r['condo_debt_currency'] ?? '');
                        $rentDebt = (string) ($r['rent_debt'] ?? '0.00');
                        $condoDebt = (string) ($r['condo_debt'] ?? '0.00');
                        $totalBs = (string) ($r['total_debt_bs'] ?? '0.00');

                        $rentLabel = $rentCur !== '' ? ($rentCur.' '.$rentDebt) : $rentDebt;
                        $condoLabel = $condoCur !== '' ? ($condoCur.' '.$condoDebt) : $condoDebt;

                        return $local
                            .' | m²: '.$area
                            .' | Uso: '.$rentPeriod.' | Deuda Uso: '.$rentLabel
                            .' | Condo: '.$condoPeriod.' | Deuda Condo: '.$condoLabel
                            .' | Total Bs: '.$totalBs;
                    })
                    ->implode("\n");

                return [
                    'locals_count' => $group->count(),
                    'market_name' => implode(', ', array_map('strval', $markets)),
                    'concessionaire_id' => $isNoConcessionaire ? null : (int) ($first['concessionaire_id'] ?? 0),
                    'concessionaire_name' => $isNoConcessionaire ? 'SIN CESIONARIO' : (string) ($first['concessionaire_name'] ?? ''),
                    'concessionaire_total_area_m2' => $concessionaireTotalArea !== null ? (float) $concessionaireTotalArea : null,
                    'locals' => implode(', ', array_map('strval', $locals)),
                    'locals_detail' => $detail,
                    'contracts' => implode(', ', array_map('strval', $contracts)),
                    'last_paid_rent_period' => $lastPaidRent !== null ? (string) $lastPaidRent : null,
                    'last_paid_condo_period' => $lastPaidCondo !== null ? (string) $lastPaidCondo : null,
                    'rent_debt_currency' => $rentCurrencyLabel !== '' ? $rentCurrencyLabel : null,
                    'condo_debt_currency' => $condoCurrencyLabel !== '' ? $condoCurrencyLabel : null,
                    'rent_debt_minor' => $rentMinor,
                    'condo_debt_minor' => $condoMinor,
                    'rent_debt' => $this->fmtCurrencyMinor($rentMinor),
                    'condo_debt' => $this->fmtCurrencyMinor($condoMinor),
                    'rent_debt_bs_minor' => $rentBsMinor,
                    'condo_debt_bs_minor' => $condoBsMinor,
                    'total_debt_bs_minor' => $totalBsMinor,
                    'rent_debt_bs' => $this->fmtCurrencyMinor($rentBsMinor),
                    'condo_debt_bs' => $this->fmtCurrencyMinor($condoBsMinor),
                    'total_debt_bs' => $this->fmtCurrencyMinor($totalBsMinor),
                ];
            })
            ->sort(function (array $a, array $b): int {
                $ac = (int) $a['locals_count'];
                $bc = (int) $b['locals_count'];
                if ($ac !== $bc) {
                    return $bc <=> $ac;
                }

                $an = strtolower((string) $a['concessionaire_name']);
                $bn = strtolower((string) $b['concessionaire_name']);

                return strcmp($an, $bn);
            })
            ->values();
    }

    private function buildQuery(): \Illuminate\Database\Query\Builder
    {
        $today = Carbon::today()->toDateString();

        $filters = is_array($this->filters) ? $this->filters : [];

        $activeContractByLocal = DB::table('contract_local as cl')
            ->join('contracts as ct', 'ct.id', '=', 'cl.contract_id')
            ->join('contract_statuses as cts', 'cts.id', '=', 'ct.contract_status_id')
            ->whereNull('ct.deleted_at')
            ->whereDate('ct.start_date', '<=', $today)
            ->whereIn('cts.code', ['VIG', 'EXT', 'VENC'])
            ->where(function ($q) use ($today): void {
                $q->whereIn('cts.code', ['VIG', 'EXT'])
                    ->where(function ($w) use ($today): void {
                        $w->whereNull('ct.end_date')->orWhereDate('ct.end_date', '>=', $today);
                    })
                    ->orWhere('cts.code', '=', 'VENC');
            })
            ->selectRaw('DISTINCT ON (cl.local_id) cl.local_id, cl.contract_id, ct.number as contract_number')
            ->orderBy('cl.local_id')
            ->orderByDesc('ct.start_date')
            ->orderByDesc('ct.id');

        $query = DB::table('locals as l')
            ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
            ->leftJoinSub($activeContractByLocal, 'acl', 'acl.local_id', '=', 'l.id')
            ->leftJoin('concessionaire_contract as cc', function ($join): void {
                $join->on('cc.contract_id', '=', 'acl.contract_id')
                    ->where('cc.is_primary', true);
            })
            ->leftJoin('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->whereNull('l.deleted_at');

        if (! empty($filters['market_id'])) {
            $query->where('l.market_id', (int) $filters['market_id']);
        }

        if (! empty($filters['concessionaire_id'])) {
            $query->where('cn.id', (int) $filters['concessionaire_id']);
        }

        if ($this->searchQuery !== '') {
            $q = strtolower($this->searchQuery);
            $query->where(function ($w) use ($q): void {
                $w->whereRaw('LOWER(l.code) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(l.name) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(COALESCE(cn.full_name, \'\')) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(COALESCE(acl.contract_number, \'\')) LIKE ?', ['%'.$q.'%']);
            });
        }

        $query
            ->orderByRaw("LOWER(COALESCE(cn.full_name, ''))")
            ->orderByRaw("LOWER(COALESCE(l.code, ''))")
            ->orderBy('l.id');

        return $query;
    }

    private function lastPaidPeriodByPrefix(mixed $localIds, string $prefix): array
    {
        $localIds = is_array($localIds) ? $localIds : [];
        if ($localIds === []) {
            return [];
        }

        $fxHelper = $this->fxHelper ?? app(FxConversionHelper::class);
        $at = Carbon::today();

        $paid = [];

        Charge::query()
            ->join('charge_statuses as cs', 'cs.id', '=', 'charges.charge_status_id')
            ->whereNull('charges.deleted_at')
            ->whereIn('charges.local_id', $localIds)
            ->where('charges.kind', 'like', strtoupper($prefix).'%')
            ->whereIn('cs.code', ['ISSUED', 'PARTIAL', 'SETTLED'])
            ->select([
                'charges.id as id',
                'charges.local_id',
                'charges.currency',
                'charges.amount_minor',
                'charges.period',
                'cs.code as status_code',
            ])
            ->orderBy('charges.id')
            ->chunkById(500, function (Collection $chunk) use (&$paid, $fxHelper, $at) {
                $outstandingMap = $fxHelper->chargesOutstandingCurrencyMinorBatch($chunk, $at);

                foreach ($chunk as $charge) {
                    $cid = (int) $charge->getKey();
                    $outstanding = (int) ($outstandingMap[$cid] ?? 0);
                    $statusCode = strtoupper((string) ($charge->getAttribute('status_code') ?? ''));
                    if ($statusCode !== 'SETTLED' && abs($outstanding) > 1) {
                        continue;
                    }

                    $localId = (int) ($charge->getAttribute('local_id') ?? 0);
                    if ($localId <= 0) {
                        continue;
                    }

                    $periodRaw = $charge->getAttribute('period');
                    if ($periodRaw === null || $periodRaw === '') {
                        continue;
                    }

                    $period = Carbon::parse((string) $periodRaw)->format('Y-m');
                    $prev = $paid[$localId] ?? null;
                    if ($prev === null || strcmp($period, $prev) > 0) {
                        $paid[$localId] = $period;
                    }
                }
            }, 'charges.id', 'id');

        return $paid;
    }

    private function debtByType(mixed $localIds): array
    {
        $localIds = is_array($localIds) ? $localIds : [];
        if ($localIds === []) {
            return [
                'rent_minor_by_local' => [],
                'condo_minor_by_local' => [],
                'rent_currency_by_local' => [],
                'condo_currency_by_local' => [],
                'rent_bs_minor_by_local' => [],
                'condo_bs_minor_by_local' => [],
            ];
        }

        $rent = [];
        $condo = [];
        $rentCurrency = [];
        $condoCurrency = [];
        $rentBs = [];
        $condoBs = [];

        $fxHelper = $this->fxHelper ?? app(FxConversionHelper::class);
        $at = Carbon::today();

        Charge::query()
            ->join('charge_statuses as cs', 'cs.id', '=', 'charges.charge_status_id')
            ->whereIn('cs.code', ['ISSUED', 'PARTIAL'])
            ->whereNull('charges.deleted_at')
            ->whereIn('charges.local_id', $localIds)
            ->select([
                'charges.id as id',
                'charges.local_id',
                'charges.kind',
                'charges.currency',
                'charges.amount_minor',
                'charges.amount_bs_minor_issued',
            ])
            ->orderBy('charges.id')
            ->chunkById(500, function (Collection $chunk) use (&$rent, &$condo, &$rentCurrency, &$condoCurrency, &$rentBs, &$condoBs, $fxHelper, $at) {
                $outstandingMap = $fxHelper->chargesOutstandingCurrencyMinorBatch($chunk, $at);

                foreach ($chunk as $charge) {
                    $cid = (int) $charge->getKey();
                    $localId = (int) ($charge->getAttribute('local_id') ?? 0);
                    if ($localId <= 0) {
                        continue;
                    }

                    $kind = strtoupper((string) ($charge->getAttribute('kind') ?? ''));
                    $outstanding = (int) ($outstandingMap[$cid] ?? 0);
                    if ($outstanding <= 0) {
                        continue;
                    }

                    $currency = strtoupper((string) ($charge->getAttribute('currency') ?? ''));
                    $bsOutstanding = $currency === 'VES'
                        ? $outstanding
                        : ((int) ($fxHelper->toVes($outstanding, $currency, $at) ?? 0));

                    if (str_starts_with($kind, 'CONDO')) {
                        $condo[$localId] = (int) ($condo[$localId] ?? 0) + $outstanding;
                        if ($currency !== '') {
                            $existing = (string) ($condoCurrency[$localId] ?? '');
                            if ($existing === '' || $existing === $currency) {
                                $condoCurrency[$localId] = $currency;
                            } elseif ($existing !== 'MIXED') {
                                $condoCurrency[$localId] = 'MIXED';
                            }
                        }
                        if ($bsOutstanding > 0) {
                            $condoBs[$localId] = (int) ($condoBs[$localId] ?? 0) + $bsOutstanding;
                        }
                    } elseif (str_starts_with($kind, 'RENT')) {
                        $rent[$localId] = (int) ($rent[$localId] ?? 0) + $outstanding;
                        if ($currency !== '') {
                            $existing = (string) ($rentCurrency[$localId] ?? '');
                            if ($existing === '' || $existing === $currency) {
                                $rentCurrency[$localId] = $currency;
                            } elseif ($existing !== 'MIXED') {
                                $rentCurrency[$localId] = 'MIXED';
                            }
                        }
                        if ($bsOutstanding > 0) {
                            $rentBs[$localId] = (int) ($rentBs[$localId] ?? 0) + $bsOutstanding;
                        }
                    }
                }
            }, 'charges.id', 'id');

        return [
            'rent_minor_by_local' => $rent,
            'condo_minor_by_local' => $condo,
            'rent_currency_by_local' => $rentCurrency,
            'condo_currency_by_local' => $condoCurrency,
            'rent_bs_minor_by_local' => $rentBs,
            'condo_bs_minor_by_local' => $condoBs,
        ];
    }

    private function fmtCurrencyMinor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
