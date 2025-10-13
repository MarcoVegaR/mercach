<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\BankTransactionRepositoryInterface;

class BankTransactionRepository extends BaseRepository implements BankTransactionRepositoryInterface
{
    protected string $modelClass = \App\Models\BankTransaction::class;

    protected function allowedSorts(): array
    {
        return ['id', 'payment_id', 'resp_code', 'created_at'];
    }
}
