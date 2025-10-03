<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\CondoPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface CondoPeriodServiceInterface extends ServiceInterface
{
    /** Create or fetch the CondoPeriod by (market_id, period date). */
    public function upsertByMarketAndPeriod(int $marketId, string $period): CondoPeriod;

    /** Mark as FINAL and stamp finalized_by/at. */
    public function finalize(CondoPeriod $period, User $by): CondoPeriod;

    /** Reopen to DRAFT when allowed; stamp reopened_by/at. */
    public function reopen(CondoPeriod $period, User $by): CondoPeriod;

    /** Transactional cascade soft-delete (period + children). */
    public function deleteCascade(Model|int|string $modelOrId): bool;

    /**
     * Load workspace data with optional dynamic relations/counts.
     *
     * @param  array<string>  $with
     * @param  array<string>  $withCount
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function loadShowData(Model $model, array $with = [], array $withCount = []): array;
}
