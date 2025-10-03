<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CondoParticipantRepositoryInterface;
use App\Contracts\Services\CondoParticipantServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\CondoParticipant;
use App\Models\CondoPeriod;
use App\Models\Local;

class CondoParticipantService extends BaseService implements CondoParticipantServiceInterface
{
    public function __construct(
        CondoParticipantRepositoryInterface $repo,
        \Psr\Container\ContainerInterface $container,
    ) {
        parent::__construct($repo, $container);
    }

    public function seedDefaults(int $periodId): int
    {
        // Exclusions-only model: no se requiere sembrar.
        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function bulkStore(int $periodId, array $items): int
    {
        return $this->transaction(function () use ($periodId, $items) {
            /** @var CondoPeriod $period */
            $period = CondoPeriod::query()->findOrFail($periodId);
            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('Período bloqueado para edición (FINAL o con cargos).');
            }

            $rows = [];
            $now = now();
            foreach ($items as $payload) {
                $rows[] = [
                    'condo_period_id' => $period->getKey(),
                    'local_id' => (int) $payload['local_id'],
                    'area_m2_snapshot' => (string) $payload['area_m2_snapshot'],
                    'included' => (bool) $payload['included'],
                    'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Use column-based conflict target for partial unique index
            return $this->repo->upsert($rows, ['condo_period_id', 'local_id'], ['area_m2_snapshot', 'included', 'is_active', 'updated_at']);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function bulkExcludeFiltered(int $periodId, array $filters = []): int
    {
        return $this->transaction(function () use ($periodId, $filters) {
            /** @var CondoPeriod $period */
            $period = CondoPeriod::query()->findOrFail($periodId);
            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('Período bloqueado para edición (FINAL o con cargos).');
            }

            // Resolve local IDs to exclude (by market + filters)
            $marketId = (int) $period->getAttribute('market_id');
            $localIds = $this->resolveLocalIdsByFilters($marketId, $filters);
            if (empty($localIds)) {
                return 0;
            }

            // Build exclusion rows (included=false) and upsert
            $locals = Local::query()->whereIn('id', $localIds)->get(['id', 'area_m2']);
            $now = now();
            $rows = [];
            foreach ($locals as $local) {
                $rows[] = [
                    'condo_period_id' => $period->getKey(),
                    'local_id' => (int) $local->getAttribute('id'),
                    'area_m2_snapshot' => (string) $local->getAttribute('area_m2'),
                    'included' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            return $this->repo->upsert($rows, ['condo_period_id', 'local_id'], ['area_m2_snapshot', 'included', 'is_active', 'updated_at']);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function bulkIncludeFiltered(int $periodId, array $filters = []): int
    {
        return $this->transaction(function () use ($periodId, $filters) {
            /** @var CondoPeriod $period */
            $period = CondoPeriod::query()->findOrFail($periodId);
            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('Período bloqueado para edición (FINAL o con cargos).');
            }

            // Resolve local IDs to re-include (delete exclusions)
            $marketId = (int) $period->getAttribute('market_id');
            $localIds = $this->resolveLocalIdsByFilters($marketId, $filters);
            if (empty($localIds)) {
                return 0;
            }

            return CondoParticipant::query()
                ->where('condo_period_id', $periodId)
                ->whereIn('local_id', $localIds)
                ->delete();
        });
    }

    /**
     * Resolve local IDs by market and provided filters (q, local_id, local_ids).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int>
     */
    private function resolveLocalIdsByFilters(int $marketId, array $filters): array
    {
        $q = Local::query()->where('market_id', $marketId);
        if (isset($filters['local_id'])) {
            $q->where('id', (int) $filters['local_id']);
        }
        if (isset($filters['local_ids']) && is_array($filters['local_ids'])) {
            $ids = array_map('intval', $filters['local_ids']);
            if (! empty($ids)) {
                $q->whereIn('id', $ids);
            }
        }
        if (isset($filters['q']) && is_string($filters['q']) && trim($filters['q']) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $filters['q'])).'%';
            $q->where(function ($qq) use ($like) {
                $qq->where('code', 'ILIKE', $like)
                    ->orWhere('name', 'ILIKE', $like);
            });
        }

        return $q->pluck('id')->map(fn ($v) => (int) $v)->all();
    }
}
