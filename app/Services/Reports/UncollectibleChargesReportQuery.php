<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ChargeCollectibilityEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UncollectibleChargesReportQuery
{
    private ?string $markedFrom = null;

    private ?string $markedTo = null;

    private string $status = 'current';

    private ?int $marketId = null;

    private ?string $currency = null;

    private string $searchQuery = '';

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        $markedBetween = (array) ($filters['marked_between'] ?? []);
        $this->markedFrom = ! empty($markedBetween['from']) ? (string) $markedBetween['from'] : null;
        $this->markedTo = ! empty($markedBetween['to']) ? (string) $markedBetween['to'] : null;

        $status = (string) ($filters['status'] ?? 'current');
        $this->status = in_array($status, ['current', 'restored', 'all'], true) ? $status : 'current';
        $this->marketId = ! empty($filters['market_id']) ? (int) $filters['market_id'] : null;
        $this->currency = ! empty($filters['currency']) ? strtoupper((string) $filters['currency']) : null;

        return $this;
    }

    public function search(string $query): self
    {
        $this->searchQuery = trim($query);

        return $this;
    }

    /**
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function paginate(int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->buildQuery()
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function get(int $limit = 5000): Collection
    {
        return $this->buildQuery()->limit($limit)->get();
    }

    /**
     * @param  LengthAwarePaginator<int, \stdClass>|Collection<int, \stdClass>  $results
     * @return Collection<int, array<string, mixed>>
     */
    public function transform($results): Collection
    {
        $collection = $results instanceof LengthAwarePaginator ? collect($results->items()) : $results;

        /** @var Collection<int, array<string, mixed>> $transformed */
        $transformed = $collection->map(fn ($row) => [
            'event_id' => (int) $row->event_id,
            'charge_id' => (int) $row->charge_id,
            'marked_at' => (string) $row->marked_at,
            'restored_at' => $row->restored_at !== null ? (string) $row->restored_at : null,
            'is_current' => (bool) $row->is_current,
            'currency' => (string) $row->currency,
            'kind' => (string) ($row->kind ?? ''),
            'kind_label' => $this->kindLabel((string) ($row->kind ?? '')),
            'period' => $row->period !== null ? (string) $row->period : null,
            'due_on' => $row->due_on !== null ? (string) $row->due_on : null,
            'status_code' => (string) ($row->status_code ?? ''),
            'market_name' => (string) ($row->market_name ?? ''),
            'local_code' => (string) ($row->local_code ?? ''),
            'concessionaire_name' => (string) ($row->concessionaire_name ?? ''),
            'reason' => (string) ($row->reason ?? ''),
            'marked_by' => (string) ($row->marked_by ?? ''),
            'declared_outstanding_amount_minor' => (int) ($row->declared_outstanding_amount_minor ?? 0),
            'declared_outstanding_bs_minor' => (int) ($row->declared_outstanding_bs_minor ?? 0),
            'current_outstanding_amount_minor' => (int) ($row->current_outstanding_amount_minor ?? 0),
            'current_outstanding_bs_minor' => (int) ($row->current_outstanding_bs_minor ?? 0),
        ])->values();

        return $transformed;
    }

    /**
     * @param  Collection<int, \stdClass>  $results
     * @return Collection<int, array<string, mixed>>
     */
    public function transformForExport(Collection $results): Collection
    {
        /** @var Collection<int, array<string, mixed>> $transformed */
        $transformed = $this->transform($results)->map(fn (array $row) => [
            'ID Cargo' => $row['charge_id'],
            'Fecha incobrable' => $row['marked_at'],
            'Estado incobrabilidad' => $row['is_current'] ? 'Actual' : 'Restaurado',
            'Fecha restauracion' => $row['restored_at'] ?? '',
            'Mercado' => $row['market_name'],
            'Local' => $row['local_code'],
            'Cesionario' => $row['concessionaire_name'],
            'Tipo cargo' => $row['kind_label'],
            'Periodo' => $row['period'] ?? '',
            'Vence' => $row['due_on'] ?? '',
            'Moneda' => $row['currency'],
            'Saldo declarado moneda menor' => $row['declared_outstanding_amount_minor'],
            'Saldo declarado Bs menor' => $row['declared_outstanding_bs_minor'],
            'Saldo actual moneda menor' => $row['current_outstanding_amount_minor'],
            'Saldo actual Bs menor' => $row['current_outstanding_bs_minor'],
            'Motivo' => $row['reason'],
            'Marcado por' => $row['marked_by'],
        ]);

        return $transformed;
    }

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        $row = DB::query()->fromSub($this->buildQueryWithoutOrder(), 'u')
            ->selectRaw('COUNT(*)::int as count')
            ->selectRaw('COALESCE(SUM(declared_outstanding_amount_minor), 0)::bigint as declared_outstanding_amount_minor')
            ->selectRaw('COALESCE(SUM(declared_outstanding_bs_minor), 0)::bigint as declared_outstanding_bs_minor')
            ->selectRaw('COALESCE(SUM(current_outstanding_amount_minor), 0)::bigint as current_outstanding_amount_minor')
            ->selectRaw('COALESCE(SUM(current_outstanding_bs_minor), 0)::bigint as current_outstanding_bs_minor')
            ->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'declared_outstanding_amount_minor' => (int) ($row->declared_outstanding_amount_minor ?? 0),
            'declared_outstanding_bs_minor' => (int) ($row->declared_outstanding_bs_minor ?? 0),
            'current_outstanding_amount_minor' => (int) ($row->current_outstanding_amount_minor ?? 0),
            'current_outstanding_bs_minor' => (int) ($row->current_outstanding_bs_minor ?? 0),
        ];
    }

    /**
     * @return Collection<int, array{currency:string,count:int,current_outstanding_amount_minor:int}>
     */
    public function totalsByCurrency(): Collection
    {
        return DB::query()->fromSub($this->buildQueryWithoutOrder(), 'u')
            ->selectRaw("UPPER(COALESCE(currency, 'VES')) as currency")
            ->selectRaw('COUNT(*)::int as count')
            ->selectRaw('COALESCE(SUM(current_outstanding_amount_minor), 0)::bigint as current_outstanding_amount_minor')
            ->where('current_outstanding_amount_minor', '>', 0)
            ->groupByRaw("UPPER(COALESCE(currency, 'VES'))")
            ->orderByRaw("CASE UPPER(COALESCE(currency, 'VES')) WHEN 'USD' THEN 1 WHEN 'EUR' THEN 2 WHEN 'VES' THEN 3 ELSE 4 END")
            ->get()
            ->map(fn ($row) => [
                'currency' => (string) $row->currency,
                'count' => (int) $row->count,
                'current_outstanding_amount_minor' => (int) $row->current_outstanding_amount_minor,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function appliedFilters(): array
    {
        return [
            'marked_between' => ['from' => $this->markedFrom, 'to' => $this->markedTo],
            'status' => $this->status,
            'market_id' => $this->marketId,
            'currency' => $this->currency,
            'q' => $this->searchQuery,
        ];
    }

    private function buildQuery(): Builder
    {
        return $this->buildQueryWithoutOrder()
            ->orderByDesc('marked_at')
            ->orderByDesc('event_id');
    }

    private function buildQueryWithoutOrder(): Builder
    {
        $latestRestore = DB::table('charge_collectibility_events')
            ->select('charge_id')
            ->selectRaw('MAX(occurred_at) as restored_at')
            ->where('action', ChargeCollectibilityEvent::ActionRestored)
            ->groupBy('charge_id');

        $contractConcessionaires = DB::table('concessionaire_contract as cc')
            ->join('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->select('cc.contract_id')
            ->selectRaw("STRING_AGG(cn.full_name, ', ' ORDER BY cc.is_primary DESC, cn.full_name) as concessionaire_names")
            ->groupBy('cc.contract_id');

        $query = DB::table('charge_collectibility_events as e')
            ->join('charges as ch', 'ch.id', '=', 'e.charge_id')
            ->join('charge_statuses as cs', 'cs.id', '=', 'ch.charge_status_id')
            ->leftJoin('markets as m', 'm.id', '=', 'ch.market_id')
            ->leftJoin('locals as l', 'l.id', '=', 'ch.local_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->leftJoinSub($latestRestore, 'restores', 'restores.charge_id', '=', 'ch.id')
            ->leftJoinSub($contractConcessionaires, 'contract_concessionaires', 'contract_concessionaires.contract_id', '=', 'ch.contract_id')
            ->leftJoin('concessionaires as debtor_cn', function ($join): void {
                $join->on('debtor_cn.id', '=', 'ch.debtor_id')->where('ch.debtor_type', '=', 'CONCESSIONAIRE');
            })
            ->where('e.action', ChargeCollectibilityEvent::ActionMarkedUncollectible)
            ->whereNull('ch.deleted_at')
            ->selectRaw('e.id as event_id')
            ->selectRaw('ch.id as charge_id')
            ->selectRaw('e.occurred_at as marked_at')
            ->selectRaw('restores.restored_at')
            ->selectRaw('(ch.uncollectible_at IS NOT NULL) as is_current')
            ->selectRaw('ch.currency')
            ->selectRaw('ch.kind')
            ->selectRaw('ch.period')
            ->selectRaw('ch.due_on')
            ->selectRaw('cs.code as status_code')
            ->selectRaw('m.name as market_name')
            ->selectRaw('l.code as local_code')
            ->selectRaw("COALESCE(contract_concessionaires.concessionaire_names, debtor_cn.full_name, '') as concessionaire_name")
            ->selectRaw('e.reason')
            ->selectRaw('u.name as marked_by')
            ->selectRaw('e.outstanding_amount_minor as declared_outstanding_amount_minor')
            ->selectRaw('e.outstanding_bs_minor as declared_outstanding_bs_minor')
            ->selectRaw('CASE WHEN ch.uncollectible_at IS NULL THEN 0 ELSE e.outstanding_amount_minor END::bigint as current_outstanding_amount_minor')
            ->selectRaw('CASE WHEN ch.uncollectible_at IS NULL THEN 0 ELSE e.outstanding_bs_minor END::bigint as current_outstanding_bs_minor');

        if ($this->markedFrom !== null) {
            $query->whereDate('e.occurred_at', '>=', $this->markedFrom);
        }

        if ($this->markedTo !== null) {
            $query->whereDate('e.occurred_at', '<=', $this->markedTo);
        }

        if ($this->status === 'current') {
            $query->whereNotNull('ch.uncollectible_at');
        } elseif ($this->status === 'restored') {
            $query->whereNull('ch.uncollectible_at');
        }

        if ($this->marketId !== null) {
            $query->where('ch.market_id', $this->marketId);
        }

        if ($this->currency !== null) {
            $query->whereRaw("UPPER(COALESCE(ch.currency, 'VES')) = ?", [$this->currency]);
        }

        if ($this->searchQuery !== '') {
            $q = strtolower($this->searchQuery);
            $query->where(function ($where) use ($q): void {
                $where->whereRaw('CAST(ch.id AS TEXT) = ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(l.code, \'\')) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw("LOWER(COALESCE(contract_concessionaires.concessionaire_names, debtor_cn.full_name, '')) LIKE ?", ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(COALESCE(e.reason, \'\')) LIKE ?', ['%'.$q.'%']);
            });
        }

        return $query;
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'RENT_EUR_FIXED' => 'Alquiler fijo',
            'RENT_EUR_M2' => 'Tasa de uso',
            'CONDO_USD' => 'Condominio',
            'FINE' => 'Multa',
            'ADJ' => 'Ajuste',
            'CESION_DERECHOS' => 'Cesión de derechos',
            default => $kind !== '' ? $kind : 'Cargo',
        };
    }
}
