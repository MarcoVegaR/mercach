<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DebtTransferItemRepositoryInterface;

class DebtTransferItemRepository extends BaseRepository implements DebtTransferItemRepositoryInterface
{
    protected string $modelClass = \App\Models\DebtTransferItem::class;

    protected function allowedSorts(): array
    {
        return ['id', 'created_at'];
    }
}
