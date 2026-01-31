<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ChargeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ChargeRepository extends BaseRepository implements ChargeRepositoryInterface
{
    protected string $modelClass = \App\Models\Charge::class;

    /**
     * Include allocation aggregates needed by index UI.
     */
    protected function builder(): Builder
    {
        return parent::builder()
            ->select('charges.*')
            ->selectSub(function ($q) {
                $q->from('payment_allocations')
                    ->selectRaw('COALESCE(SUM(amount_bs_minor), 0)')
                    ->whereColumn('payment_allocations.charge_id', 'charges.id');
            }, 'allocated_bs_minor');
    }

    /**
     * Global searchable columns on charges plus additional EXISTS searches for related names.
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        // Use direct columns for base LIKE search; related names handled in applySearch override
        return [
            'currency', 'kind', 'source',
        ];
    }

    protected function allowedSorts(): array
    {
        // Allow sorting by most useful columns (must be real DB columns on charges)
        return [
            'id', 'market_id', 'local_id', 'contract_id', 'condo_period_id',
            'debtor_type', 'debtor_id', 'kind', 'currency',
            'amount_minor', 'period', 'issued_on', 'due_on',
            'charge_status_id', 'source', 'created_at',
        ];
    }

    protected function filterMap(): array
    {
        return [
            // Filter charges by concessionaire via contracts → locals
            'concessionaire_id' => function (Builder $builder, $value): void {
                $cid = (int) $value;
                if ($cid <= 0) {
                    return;
                }
                $builder->where(function ($w) use ($cid) {
                    $w->whereExists(function ($q) use ($cid) {
                        $q->select(DB::raw('1'))
                            ->from('contract_local as cl')
                            ->join('concessionaire_contract as cc', 'cc.contract_id', '=', 'cl.contract_id')
                            ->whereColumn('cl.local_id', 'charges.local_id')
                            ->where('cc.concessionaire_id', '=', $cid);
                    })->orWhere(function ($q) use ($cid) {
                        $q->where('charges.debtor_type', 'CONCESSIONAIRE')
                            ->where('charges.debtor_id', $cid);
                    });
                });
            },
        ];
    }

    /**
     * Include related names in global search without joining (uses EXISTS).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applySearch(Builder $builder, string $searchTerm): Builder
    {
        if ($searchTerm === '') {
            return $builder;
        }
        // Apply base search on direct columns
        $builder = parent::applySearch($builder, $searchTerm);

        $s = strtolower($searchTerm);

        // Local name
        $builder->orWhereExists(function ($q) use ($s) {
            $q->select(DB::raw('1'))
                ->from('locals')
                ->whereColumn('locals.id', 'charges.local_id')
                ->whereRaw('LOWER(locals.name) LIKE ?', ['%'.$s.'%']);
        });

        // Market name
        $builder->orWhereExists(function ($q) use ($s) {
            $q->select(DB::raw('1'))
                ->from('markets')
                ->whereColumn('markets.id', 'charges.market_id')
                ->whereRaw('LOWER(markets.name) LIKE ?', ['%'.$s.'%']);
        });

        // Contract number
        $builder->orWhereExists(function ($q) use ($s) {
            $q->select(DB::raw('1'))
                ->from('contracts')
                ->whereColumn('contracts.id', 'charges.contract_id')
                ->whereRaw('LOWER(CAST(contracts.number AS TEXT)) LIKE ?', ['%'.$s.'%']);
        });

        return $builder;
    }

    protected function activeColumn(): string
    {
        // Charges do not have a generic 'active' column
        return 'is_active'; // unused here
    }

    /**
     * Override upsert to be robust with PostgreSQL partial unique indexes by
     * performing per-row updateOrInsert using the provided unique keys and
     * enforcing deleted_at = null in the key to ignore soft-deleted rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string>|string  $uniqueBy
     * @param  array<string>  $updateColumns
     */
    public function upsert(array $rows, array|string $uniqueBy, array $updateColumns): int
    {
        $uniqueKeys = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];
        $table = (new $this->modelClass)->getTable();

        $affected = 0;
        foreach ($rows as $row) {
            // Build match keys
            $keys = [];
            foreach ($uniqueKeys as $k) {
                // ensure key exists in row to avoid null comparisons
                if (array_key_exists($k, $row)) {
                    $keys[$k] = $row[$k];
                }
            }
            // Ensure we don't collide with soft-deleted rows
            if (! array_key_exists('deleted_at', $keys)) {
                $keys['deleted_at'] = null;
            }

            // Build update payload
            $payload = [];
            foreach ($updateColumns as $col) {
                if (array_key_exists($col, $row)) {
                    $payload[$col] = $row[$col];
                }
            }
            // Ensure timestamps
            $payload['updated_at'] = $row['updated_at'] ?? now();
            if (! array_key_exists('created_at', $row)) {
                // add created_at so insert has it
                $payload['created_at'] = now();
            } else {
                $payload['created_at'] = $row['created_at'];
            }

            DB::table($table)->updateOrInsert($keys, $payload);
            $affected++;
        }

        return $affected;
    }
}
