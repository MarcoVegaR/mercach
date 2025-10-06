<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DebtTransferRepositoryInterface;

class DebtTransferRepository extends BaseRepository implements DebtTransferRepositoryInterface
{
    protected string $modelClass = \App\Models\DebtTransfer::class;

    protected function allowedSorts(): array
    {
        return ['id', 'executed_at', 'created_at'];
    }
}
