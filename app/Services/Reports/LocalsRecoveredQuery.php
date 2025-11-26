<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query builder for Recovered Locals report.
 *
 * Shows locals that were recovered (contracts terminated).
 */
class LocalsRecoveredQuery
{
    private ?string $recoveredFrom = null;

    private ?string $recoveredTo = null;

    /**
     * Filter by recovery date range.
     */
    public function recoveredBetween(?string $from, ?string $to): self
    {
        $this->recoveredFrom = $from;
        $this->recoveredTo = $to;

        return $this;
    }

    /**
     * Apply filters from request array.
     *
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        $recoveredBetween = (array) ($filters['recovered_between'] ?? []);
        if (! empty($recoveredBetween['from']) || ! empty($recoveredBetween['to'])) {
            $this->recoveredBetween(
                ! empty($recoveredBetween['from']) ? (string) $recoveredBetween['from'] : null,
                ! empty($recoveredBetween['to']) ? (string) $recoveredBetween['to'] : null
            );
        }

        return $this;
    }

    /**
     * Execute with pagination.
     *
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function paginate(int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->buildQuery()
            ->paginate($perPage, [
                'csh.occurred_at as recovered_at',
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'm.name as market_name',
                'c.id as contract_id',
                'c.number as contract_number',
                'cn.full_name as concessionaire_name',
            ], 'page', $page)
            ->withQueryString();
    }

    /**
     * Execute and get all results (limited for export).
     *
     * @return Collection<int, \stdClass>
     */
    public function get(int $limit = 5000): Collection
    {
        return $this->buildQuery()
            ->limit($limit)
            ->get([
                'csh.occurred_at as recovered_at',
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'm.name as market_name',
                'c.id as contract_id',
                'c.number as contract_number',
                'cn.full_name as concessionaire_name',
            ]);
    }

    /**
     * Transform results to report row format.
     *
     * @param  Collection<int, \stdClass>|LengthAwarePaginator<int, \stdClass>  $results
     * @return Collection<int, array{
     *     recovered_at: string,
     *     local_id: int,
     *     local_code: string,
     *     local_name: string,
     *     market_name: string,
     *     contract_id: int,
     *     contract_number: string,
     *     concessionaire_name: string,
     * }>
     */
    public function transform($results): Collection
    {
        $collection = $results instanceof LengthAwarePaginator
            ? collect($results->items())
            : $results;

        return $collection->map(fn ($row) => [
            'recovered_at' => (string) $row->recovered_at,
            'local_id' => (int) $row->local_id,
            'local_code' => (string) ($row->local_code ?? ''),
            'local_name' => (string) ($row->local_name ?? ''),
            'market_name' => (string) ($row->market_name ?? ''),
            'contract_id' => (int) $row->contract_id,
            'contract_number' => (string) ($row->contract_number ?? ''),
            'concessionaire_name' => (string) ($row->concessionaire_name ?? ''),
        ]);
    }

    /**
     * Transform for export with Spanish column names.
     *
     * @param  Collection<int, \stdClass>  $results
     * @return Collection<int, array{
     *     'Fecha recuperación': string,
     *     Local: string,
     *     Mercado: string,
     *     Contrato: string,
     *     Cesionario: string,
     * }>
     */
    public function transformForExport(Collection $results): Collection
    {
        return $results->map(fn ($row) => [
            'Fecha recuperación' => (string) $row->recovered_at,
            'Local' => (string) ($row->local_code ?: $row->local_name ?: ''),
            'Mercado' => (string) ($row->market_name ?? ''),
            'Contrato' => (string) ($row->contract_number ?? ''),
            'Cesionario' => (string) ($row->concessionaire_name ?? ''),
        ]);
    }

    /**
     * Build the base query.
     */
    private function buildQuery(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('contract_status_history as csh')
            ->join('contracts as c', 'c.id', '=', 'csh.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
            ->leftJoin('concessionaire_contract as cc', function ($join): void {
                $join->on('cc.contract_id', '=', 'c.id')->where('cc.is_primary', true);
            })
            ->leftJoin('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->where('csh.to_code', '=', 'TERM')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at');

        if ($this->recoveredFrom !== null) {
            $query->whereDate('csh.occurred_at', '>=', $this->recoveredFrom);
        }

        if ($this->recoveredTo !== null) {
            $query->whereDate('csh.occurred_at', '<=', $this->recoveredTo);
        }

        $query->orderBy('csh.occurred_at', 'desc')->orderBy('l.id');

        return $query;
    }
}
