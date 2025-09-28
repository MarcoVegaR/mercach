<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Contract;
use Illuminate\Http\UploadedFile;

interface ContractServiceInterface extends ServiceInterface
{
    /**
     * Extra data for index view (e.g., stats).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array;

    /**
     * Confirm a draft contract (BORR -> VIG) with validations and transitions.
     */
    public function confirm(Contract $contract): Contract;

    /**
     * Terminate an active/extended contract (VIG/EXT -> TERM) and free locals.
     */
    public function terminate(Contract $contract): Contract;

    /**
     * Extend a contract (VIG/EXT -> EXT), updating end_date and storing optional PDF.
     */
    public function extend(Contract $contract, string $newEndDate, ?UploadedFile $pdf = null): Contract;

    /**
     * Mark overdue active contracts as VENC and free locals. Returns affected count.
     */
    public function expireOverdue(): int;
}
