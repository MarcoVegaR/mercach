<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Request validation for Role index/listing operations.
 *
 * @author Laravel Boilerplate
 */
class RoleIndexRequest extends BaseIndexRequest
{
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
     * Get filter rules for roles.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'filters.guard_name' => ['nullable', 'string'],
            'filters.created_between.from' => ['nullable', 'date'],
            'filters.created_between.to' => ['nullable', 'date'],
            // Explicitly allow permissions filter (array of permission names)
            'filters.permissions' => ['nullable', 'array'],
            'filters.permissions.*' => ['string'],
            'filters.permissions_count_min' => ['nullable', 'integer', 'min:0'],
            'filters.permissions_count_max' => ['nullable', 'integer', 'min:0'],
            'filters.users_count_min' => ['nullable', 'integer', 'min:0'],
            'filters.users_count_max' => ['nullable', 'integer', 'min:0'],
            'filters.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the maximum number of items per page.
     */
    protected function maxPerPage(): int
    {
        return 100;
    }

    /**
     * Get the default number of items per page.
     */
    protected function defaultPerPage(): int
    {
        return 10;
    }
}
