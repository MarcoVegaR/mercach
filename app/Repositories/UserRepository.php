<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository implementation for User operations.
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * The model class to be used by this repository.
     */
    protected string $modelClass = User::class;

    /**
     * Define searchable fields.
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return ['name', 'email'];
    }

    /**
     * Allowed sort fields.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'name', 'email', 'is_active', 'created_at', 'roles_count'];
    }

    /**
     * Default sort order.
     *
     * @return array{string,string}
     */
    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * Column used for active state.
     */
    protected function activeColumn(): string
    {
        return 'is_active';
    }

    /**
     * Eager load/count relations.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function withRelations(Builder $builder): Builder
    {
        // Load roles names and count roles for each user
        return $builder->with(['roles:id,name'])->withCount('roles');
    }

    /**
     * Custom filter mapping.
     *
     * @return array<string, callable>
     */
    protected function filterMap(): array
    {
        return [
            // LIKE filters for name/email (case-insensitive)
            'name' => function (Builder $builder, $value) {
                if ($value !== null && $value !== '') {
                    $builder->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower((string) $value).'%']);
                }
            },
            'email' => function (Builder $builder, $value) {
                if ($value !== null && $value !== '') {
                    $builder->whereRaw('LOWER(email) LIKE ?', ['%'.strtolower((string) $value).'%']);
                }
            },
            // Filter by a specific role ID via relation
            'role_id' => function (Builder $builder, $value) {
                if ($value !== null && $value !== '') {
                    $builder->whereHas('roles', function ($q) use ($value) {
                        $q->where('id', (int) $value);
                    });
                }
            },
            // Exact boolean match for active state
            'is_active' => function (Builder $builder, $value) {
                if ($value !== null && $value !== '') {
                    $builder->where('is_active', (bool) $value);
                }
            },
            // Date range on created_at
            'created_between' => function (Builder $builder, $value) {
                if (is_array($value)) {
                    if (isset($value['from']) && $value['from'] !== '') {
                        $builder->whereDate('created_at', '>=', $value['from']);
                    }
                    if (isset($value['to']) && $value['to'] !== '') {
                        $builder->whereDate('created_at', '<=', $value['to']);
                    }
                }
            },
        ];
    }
}
