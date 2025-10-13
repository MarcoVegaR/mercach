<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PaymentAllocationRepositoryInterface;

class PaymentAllocationRepository extends BaseRepository implements PaymentAllocationRepositoryInterface
{
    protected string $modelClass = \App\Models\PaymentAllocation::class;

    protected function allowedSorts(): array
    {
        return [
            'id', 'payment_id', 'charge_id', 'local_id', 'debtor_type', 'debtor_id', 'amount_bs_minor', 'created_at',
        ];
    }
}
