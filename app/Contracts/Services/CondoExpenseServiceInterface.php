<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\CondoExpense;
use App\Models\CondoPeriod;

interface CondoExpenseServiceInterface extends ServiceInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function bulkStore(int $periodId, array $items): int;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOne(CondoPeriod $period, array $payload): CondoExpense;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOne(CondoExpense $expense, array $payload): CondoExpense;

    public function deleteOne(CondoExpense $expense): void;
}
