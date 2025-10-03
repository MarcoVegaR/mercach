<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CondoExpenseRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CondoExpenseRepository extends BaseRepository implements CondoExpenseRepositoryInterface
{
    protected string $modelClass = \App\Models\CondoExpense::class;

    protected function searchable(): array
    {
        return ['invoice_number'];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'condo_period_id', 'expense_type_id', 'amount_usd_minor', 'expense_date', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): array
    {
        return ['expense_date', 'asc'];
    }

    protected function activeColumn(): string
    {
        return 'is_active';
    }

    protected function filterMap(): array
    {
        return [
            'condo_period_id' => function (Builder $b, $v): void {
                $b->where('condo_period_id', (int) $v);
            },
            'expense_type_id' => function (Builder $b, $v): void {
                $b->where('expense_type_id', (int) $v);
            },
            'expense_date_between' => function (Builder $b, $v): void {
                if (is_array($v)) {
                    if (! empty($v['from'])) {
                        $b->whereDate('expense_date', '>=', $v['from']);
                    }
                    if (! empty($v['to'])) {
                        $b->whereDate('expense_date', '<=', $v['to']);
                    }
                }
            },
        ];
    }
}
