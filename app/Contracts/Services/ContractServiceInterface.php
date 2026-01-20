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
     * Terminate an active/extended/expired contract (VIG/EXT/VENC -> TERM) and free locals.
     */
    public function terminate(Contract $contract): Contract;

    /**
     * Extend a contract (VIG/EXT/VENC -> EXT), updating end_date and storing optional PDF. Block when unsigned.
     */
    public function extend(Contract $contract, string $newEndDate, ?UploadedFile $pdf = null): Contract;

    /**
     * Mark overdue signed contracts as VENC (no local free). Returns affected count.
     */
    public function expireOverdue(): int;

    /**
     * Sign a provisional/unsigned contract (set signed_at; optionally update number, end_date and replace PDF).
     */
    public function sign(Contract $contract, ?UploadedFile $pdf = null, ?string $number = null, ?string $endDate = null): Contract;

    public function assign(Contract $contract, int $newConcessionaireId, string $effectiveDate, ?string $reason = null, ?int $createdByUserId = null): Contract;
}
