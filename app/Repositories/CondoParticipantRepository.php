<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CondoParticipantRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CondoParticipantRepository extends BaseRepository implements CondoParticipantRepositoryInterface
{
    protected string $modelClass = \App\Models\CondoParticipant::class;

    protected function searchable(): array
    {
        return [];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'condo_period_id', 'local_id', 'area_m2_snapshot', 'included', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): array
    {
        return ['id', 'asc'];
    }

    protected function activeColumn(): string
    {
        return 'is_active';
    }

    protected function filterMap(): array
    {
        return [
            'condo_period_id' => function (Builder $b, $v): void {
                $b->where('condo_period_id', (int) $v);
            },
            'included' => function (Builder $b, $v): void {
                $b->where('included', (bool) $v);
            },
            'local_id' => function (Builder $b, $v): void {
                $b->where('local_id', (int) $v);
            },
        ];
    }
}
