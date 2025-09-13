<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\UserServiceInterface;
use App\DTO\ListQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Service implementation for User operations.
 */
class UserService extends BaseService implements UserServiceInterface
{
    /**
     * Transform a User model to array representation (SSOT for front/export).
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        /** @var User $user */
        $user = $model;

        return [
            'id' => $user->getAttribute('id'),
            'name' => (string) $user->getAttribute('name'),
            'email' => (string) $user->getAttribute('email'),
            'is_active' => (bool) ($user->getAttribute('is_active') ?? true),
            'roles' => $user->relationLoaded('roles') ? $user->roles->pluck('name')->map(fn ($v) => (string) $v)->toArray() : [],
            'roles_count' => (int) ($user->getAttribute('roles_count') ?? ($user->relationLoaded('roles') ? $user->roles->count() : 0)),
            'created_at' => $user->getAttribute('created_at'),
        ];
    }

    /**
     * Transform a User model to a stable "show" representation for the UI.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        /** @var User $m */
        $m = $model;

        $createdAt = $m->getAttribute('created_at');
        $updatedAt = $m->getAttribute('updated_at');
        $item = [
            'id' => $m->getAttribute('id'),
            'name' => (string) $m->getAttribute('name'),
            'email' => (string) $m->getAttribute('email'),
            'is_active' => (bool) ($m->getAttribute('is_active') ?? true),
            'created_at' => method_exists($createdAt, 'toISOString') ? $createdAt->toISOString() : (string) $createdAt,
            'updated_at' => method_exists($updatedAt, 'toISOString') ? $updatedAt->toISOString() : (string) $updatedAt,
        ];

        // Include roles_count only if it's actually loaded/present
        $rolesCount = $m->getAttribute('roles_count');
        if ($rolesCount !== null) {
            $item['roles_count'] = $rolesCount;
        }

        // Include roles only if relation is loaded
        if ($m->relationLoaded('roles')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles */
            $roles = $m->roles;
            $item['roles'] = $roles->map(
                /** @param \App\Models\Role $r */
                fn ($r): array => ['id' => $r->id, 'name' => $r->name]
            )->all();
        }

        return $item;
    }

    /**
     * Default export columns for users.
     *
     * @return array<string, string>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'name' => 'Nombre',
            'email' => 'Email',
            'roles_count' => 'Roles',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * Default export filename.
     */
    protected function defaultExportFilename(string $format, ListQuery $query): string
    {
        return 'users_export_'.date('Ymd_His').'.'.$format;
    }

    /**
     * Provide model class for BaseService when deriving filenames, etc.
     */
    protected function repoModelClass(): string
    {
        return User::class;
    }

    /**
     * Before creating a user: hash password if provided and drop confirmation.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeCreate(array &$attributes): void
    {
        if (array_key_exists('password', $attributes)) {
            $pwd = $attributes['password'];
            if ($pwd === null || $pwd === '') {
                unset($attributes['password']);
            } else {
                $attributes['password'] = Hash::make((string) $pwd);
            }
        }
        unset($attributes['password_confirmation']);
    }

    /**
     * Before updating a user: hash password if provided; if empty, do not update it.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeUpdate(Model $model, array &$attributes): void
    {
        if (array_key_exists('password', $attributes)) {
            $pwd = $attributes['password'];
            if ($pwd === null || $pwd === '') {
                // Do not alter password if no value provided
                unset($attributes['password']);
            } else {
                $attributes['password'] = Hash::make((string) $pwd);
            }
        }
        unset($attributes['password_confirmation']);
    }

    /**
     * Hook called after creating a user.
     * Syncs roles if provided via roles_ids (Spatie HasRoles uses MorphToMany, not handled by BaseService).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterCreate(Model $model, array $attributes): void
    {
        if ($model instanceof User && array_key_exists('roles_ids', $attributes)) {
            $ids = array_map(static fn ($v) => (int) $v, (array) ($attributes['roles_ids'] ?? []));
            // Sync via relation to keep pivot accurate
            try {
                $model->roles()->sync($ids);
            } catch (\Throwable) {
                // Fallback to Spatie helper
                $model->syncRoles($ids);
            }
        }
    }

    /**
     * Hook called after updating a user.
     * Syncs roles if provided via roles_ids.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterUpdate(Model $model, array $attributes): void
    {
        if ($model instanceof User && array_key_exists('roles_ids', $attributes)) {
            $ids = array_map(static fn ($v) => (int) $v, (array) ($attributes['roles_ids'] ?? []));
            try {
                $model->roles()->sync($ids);
            } catch (\Throwable) {
                $model->syncRoles($ids);
            }
        }
    }
}
