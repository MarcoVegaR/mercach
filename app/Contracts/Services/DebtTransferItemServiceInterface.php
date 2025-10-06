<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Model;

interface DebtTransferItemServiceInterface extends ServiceInterface
{
    /** @return array<string, mixed> */
    public function toItem(Model $model): array;
}
