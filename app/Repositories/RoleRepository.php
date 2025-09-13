<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Repository implementation for Role operations.
 *
 * @author Laravel Boilerplate
 */
class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    /**
     * The model class to be used by this repository.
     */
    protected string $modelClass = Role::class;

    /**
     * Define searchable fields
     */
    protected function searchable(): array
    {
        return [
            'name',
        ];
    }

    /**
     * Get allowed sort fields for roles.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'name', 'guard_name', 'created_at', 'permissions_count', 'users_count', 'is_active'];
    }

    /**
     * Default sort order
     */
    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * Use 'is_active' column for roles' active state.
     */
    protected function activeColumn(): string
    {
        return 'is_active';
    }

    /**
     * Apply eager loading for role relationships.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function withRelations($builder): Builder
    {
        // Load permissions relationship with description and apply withCount
        $builder = $builder->with('permissions:id,name,description')->withCount('permissions');

        // Add users_count via pivot table to avoid Role::users relation (guard-dependent)
        $builder->selectSub(
            DB::table('model_has_roles')
                ->selectRaw('COUNT(*)')
                ->whereColumn('model_has_roles.role_id', 'roles.id')
                ->where('model_has_roles.model_type', \App\Models\User::class),
            'users_count'
        );

        return $builder;
    }

    /**
     * Get filter mapping for roles.
     *
     * @return array<string, callable>
     */
    protected function filterMap(): array
    {
        return [
            'guard_name' => function (Builder $builder, $value) {
                $builder->where('guard_name', $value);
            },
            'created_between' => function (Builder $builder, $value) {
                if (isset($value['from'])) {
                    $builder->whereDate('created_at', '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $builder->whereDate('created_at', '<=', $value['to']);
                }
            },
            'permissions' => function (Builder $builder, $value) {
                if (is_array($value) && ! empty($value)) {
                    $builder->whereHas('permissions', function ($query) use ($value) {
                        $query->whereIn('permissions.name', $value);
                    });
                }
            },
            'permissions_count_min' => function (Builder $builder, $value) {
                $builder->has('permissions', '>=', (int) $value);
            },
            'permissions_count_max' => function (Builder $builder, $value) {
                $builder->has('permissions', '<=', (int) $value);
            },
            'users_count_min' => function (Builder $builder, $value) {
                $value = (int) $value;
                $builder->whereRaw(
                    '(SELECT COUNT(*) FROM model_has_roles mhr WHERE mhr.role_id = roles.id AND mhr.model_type = ?) >= ?',
                    [\App\Models\User::class, $value]
                );
            },
            'users_count_max' => function (Builder $builder, $value) {
                $value = (int) $value;
                $builder->whereRaw(
                    '(SELECT COUNT(*) FROM model_has_roles mhr WHERE mhr.role_id = roles.id AND mhr.model_type = ?) <= ?',
                    [\App\Models\User::class, $value]
                );
            },
            'is_active' => function (Builder $builder, $value) {
                $builder->where('is_active', (bool) $value);
            },
        ];
    }

    /**
     * Ensure show queries include the same relations and counts as list views.
     * This guarantees permissions_count and users_count are present consistently.
     */
    public function showById(int|string $id, \App\DTO\ShowQuery $query): \Illuminate\Database\Eloquent\Model
    {
        $builder = $this->withRelations($this->builder());
        $this->applyShowQuery($builder, $query);

        return $builder->findOrFail($id);
    }

    /**
     * Show by UUID with consistent relations and counts.
     */
    public function showByUuid(string $uuid, \App\DTO\ShowQuery $query): \Illuminate\Database\Eloquent\Model
    {
        $builder = $this->withRelations($this->builder());
        $this->applyShowQuery($builder, $query);

        return $builder->where('uuid', $uuid)->firstOrFail();
    }
}
