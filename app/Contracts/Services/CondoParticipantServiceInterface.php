<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface CondoParticipantServiceInterface extends ServiceInterface
{
    /** Seed default participants from Locals for the period (included=true). */
    public function seedDefaults(int $periodId): int;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function bulkStore(int $periodId, array $items): int;

    /**
     * Update included=false for rows matching filters (server-side selection).
     *
     * @param  array<string, mixed>  $filters
     */
    public function bulkExcludeFiltered(int $periodId, array $filters = []): int;

    /**
     * Update included=true for rows matching filters (server-side selection).
     *
     * @param  array<string, mixed>  $filters
     */
    public function bulkIncludeFiltered(int $periodId, array $filters = []): int;
}
