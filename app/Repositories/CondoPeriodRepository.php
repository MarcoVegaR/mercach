<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CondoPeriodRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CondoPeriodRepository extends BaseRepository implements CondoPeriodRepositoryInterface
{
    protected string $modelClass = \App\Models\CondoPeriod::class;

    protected function searchable(): array
    {
        return ['note'];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'market_id', 'period', 'status', 'finalized_at', 'reopened_at', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): array
    {
        // Latest period first
        return ['period', 'desc'];
    }

    protected function activeColumn(): string
    {
        return 'is_active';
    }

    /**
     * Always include counts and total sum for index rendering.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function withRelations(Builder $builder): Builder
    {
        // withCount for children and withSum for total amount
        return $builder
            ->with(['market:id,name'])
            ->withCount(['expenses', 'participants'])
            ->withSum('expenses as total_usd_minor', 'amount_usd_minor');
    }

    protected function filterMap(): array
    {
        return [
            'market_id' => function (Builder $b, $v): void {
                $b->where('market_id', (int) $v);
            },
            'status' => function (Builder $b, $v): void {
                $b->where('status', (string) $v);
            },
            'is_active' => function (Builder $b, $v): void {
                $b->where('is_active', (bool) $v);
            },
            // Exact match on first-day-of-month date
            'period' => function (Builder $b, $v): void {
                $b->whereDate('period', '=', (string) $v);
            },
            // Optional between filter for ranges
            'period_between' => function (Builder $b, $v): void {
                if (is_array($v)) {
                    if (! empty($v['from'])) {
                        $b->whereDate('period', '>=', $v['from']);
                    }
                    if (! empty($v['to'])) {
                        $b->whereDate('period', '<=', $v['to']);
                    }
                }
            },
            // Placeholder for charges flag (no-op until charges domain exists)
            'has_charges' => function (Builder $b, $v): void {
                // When charges domain exists, implement join/exists here
            },
        ];
    }

    /**
     * Global search for CondoPeriods: filter by period month/day text.
     * Supports queries like "2025-09" or "2025-09-01" (substring match).
     * Uses Postgres to_char + ILIKE for case-insensitive search.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applySearch(Builder $builder, string $searchTerm): Builder
    {
        $needle = trim(strtolower($searchTerm));
        if ($needle === '') {
            return $builder;
        }

        // Escape LIKE wildcards
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';

        return $builder->where(function (Builder $q) use ($like) {
            // YYYY-MM and YYYY-MM-DD formatted search
            $q->whereRaw("to_char(period, 'YYYY-MM') ILIKE ?", [$like])
                ->orWhereRaw("to_char(period, 'YYYY-MM-DD') ILIKE ?", [$like]);
        });
    }
}
