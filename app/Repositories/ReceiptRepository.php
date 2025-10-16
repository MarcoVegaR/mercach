<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ReceiptRepositoryInterface;

class ReceiptRepository extends BaseRepository implements ReceiptRepositoryInterface
{
    protected string $modelClass = \App\Models\Receipt::class;

    protected function searchable(): array
    {
        return ['id', 'receipt_number', 'series_code'];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'receipt_number', 'issued_at', 'created_at'];
    }
}
