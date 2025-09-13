<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Request validation for showing a single role.
 *
 * Defines whitelisted relations, counts, and appends
 * that can be requested when viewing a role.
 *
 * @author Laravel Boilerplate
 */
class RoleShowRequest extends BaseShowRequest
{
    /**
     * Get the allowed relations for eager loading.
     *
     * @return array<string>
     */
    protected function allowedRelations(): array
    {
        return [
            'permissions',
        ];
    }

    /**
     * Get the allowed relations for counting.
     *
     * @return array<string>
     */
    protected function allowedCounts(): array
    {
        return [
            'permissions',
            'users',
        ];
    }

    // NOTE: Counts are provided centrally by RoleRepository::withRelations() for
    // Index and Show consistency (permissions_count, users_count). We keep this
    // previous override commented to document the approach but rely on the repository.
    /*
    public function toShowQuery(): \App\DTO\ShowQuery
    {
        $validated = $this->validated();

        $with = $validated['with'] ?? [];
        $withCount = $validated['withCount'] ?? [];

        $withCount = array_values(array_unique(array_merge($withCount, ['users', 'permissions'])));

        return \App\DTO\ShowQuery::fromArray([
            'with' => $with,
            'withCount' => $withCount,
            'append' => $validated['append'] ?? [],
            'withTrashed' => $validated['withTrashed'] ?? false,
        ]);
    }
    */

    /**
     * Get the allowed attributes to append.
     *
     * @return array<string>
     */
    protected function allowedAppends(): array
    {
        return [];
    }
}
