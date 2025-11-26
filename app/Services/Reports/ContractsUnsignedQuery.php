<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query builder for Unsigned Contracts report.
 */
class ContractsUnsignedQuery
{
    private string $searchQuery = '';

    private ?int $contractTypeId = null;

    private ?int $contractStatusId = null;

    private ?string $startFrom = null;

    private ?string $startTo = null;

    private string $sortColumn = 'start_date';

    private string $sortDirection = 'desc';

    /**
     * Set search query.
     */
    public function search(string $query): self
    {
        $this->searchQuery = trim($query);

        return $this;
    }

    /**
     * Filter by contract type.
     */
    public function filterType(?int $typeId): self
    {
        $this->contractTypeId = $typeId;

        return $this;
    }

    /**
     * Filter by contract status.
     */
    public function filterStatus(?int $statusId): self
    {
        $this->contractStatusId = $statusId;

        return $this;
    }

    /**
     * Filter by start date range.
     */
    public function startBetween(?string $from, ?string $to): self
    {
        $this->startFrom = $from;
        $this->startTo = $to;

        return $this;
    }

    /**
     * Set sorting.
     */
    public function orderBy(string $column, string $direction = 'desc'): self
    {
        $sortable = ['number', 'start_date', 'end_date'];
        $this->sortColumn = in_array($column, $sortable, true) ? $column : 'start_date';
        $this->sortDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    /**
     * Apply filters from request array.
     *
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        if (! empty($filters['contract_type_id'])) {
            $this->filterType((int) $filters['contract_type_id']);
        }

        if (! empty($filters['contract_status_id'])) {
            $this->filterStatus((int) $filters['contract_status_id']);
        }

        $startBetween = (array) ($filters['start_between'] ?? []);
        if (! empty($startBetween['from']) || ! empty($startBetween['to'])) {
            $this->startBetween(
                ! empty($startBetween['from']) ? (string) $startBetween['from'] : null,
                ! empty($startBetween['to']) ? (string) $startBetween['to'] : null
            );
        }

        return $this;
    }

    /**
     * Execute with pagination.
     *
     * @return LengthAwarePaginator<int, Contract>
     */
    public function paginate(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->buildQuery()
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Execute and get all results.
     *
     * @return Collection<int, Contract>
     */
    public function get(): Collection
    {
        return $this->buildQuery()->get();
    }

    /**
     * Transform contracts to report row format.
     *
     * @param  Collection<int, Contract>|LengthAwarePaginator<int, Contract>  $contracts
     *
     * @phpstan-return Collection<int, array{
     *     id: int,
     *     number: string,
     *     contract_type: string,
     *     contract_status: string,
     *     contract_status_code: string,
     *     start_date: string,
     *     end_date: string|null,
     * }>
     */
    public function transform($contracts): Collection
    {
        $collection = $contracts instanceof LengthAwarePaginator
            ? collect($contracts->items())
            : $contracts;

        /** @phpstan-ignore-next-line */
        return $collection
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'number' => (string) ($c->number ?? ''),
                'contract_type' => (string) ($c->type->name ?? ''),
                'contract_status' => (string) ($c->status->name ?? ''),
                'contract_status_code' => (string) ($c->status->code ?? ''),
                'start_date' => (string) $c->start_date,
                'end_date' => $c->end_date ? (string) $c->end_date : null,
            ])
            ->values();
    }

    /**
     * Transform for export with Spanish column names.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return Collection<int, array{
     *     'Número': string,
     *     'Tipo': string,
     *     'Estado': string,
     *     'Fecha inicio': string,
     *     'Fecha fin': string,
     * }>
     */
    public function transformForExport(Collection $contracts): Collection
    {
        return $contracts->map(fn (Contract $c) => [
            'Número' => (string) ($c->number ?? ''),
            'Tipo' => (string) ($c->type->name ?? ''),
            'Estado' => (string) ($c->status->name ?? ''),
            'Fecha inicio' => (string) $c->start_date,
            'Fecha fin' => $c->end_date ? (string) $c->end_date : '',
        ]);
    }

    /**
     * Get filter options for the UI.
     *
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    public static function getFilterOptions(): array
    {
        $types = DB::table('contract_types')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();

        $statuses = DB::table('contract_statuses')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();

        return [
            'contract_types' => $types,
            'contract_statuses' => $statuses,
        ];
    }

    /**
     * Build the base query.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Contract>
     */
    private function buildQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Contract::query()
            ->with(['type:id,name', 'status:id,name,code'])
            ->whereNull('signed_at')
            ->whereNull('deleted_at');

        if ($this->searchQuery !== '') {
            $query->where('number', 'like', "%{$this->searchQuery}%");
        }

        if ($this->contractTypeId !== null) {
            $query->where('contract_type_id', $this->contractTypeId);
        }

        if ($this->contractStatusId !== null) {
            $query->where('contract_status_id', $this->contractStatusId);
        }

        if ($this->startFrom !== null) {
            $query->whereDate('start_date', '>=', $this->startFrom);
        }

        if ($this->startTo !== null) {
            $query->whereDate('start_date', '<=', $this->startTo);
        }

        $query->orderBy($this->sortColumn, $this->sortDirection)->orderBy('id', 'desc');

        return $query;
    }
}
