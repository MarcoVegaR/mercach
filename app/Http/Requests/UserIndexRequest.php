<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Request validation for User index/listing operations.
 */
class UserIndexRequest extends BaseIndexRequest
{
    /**
     * Allowed sort fields
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'name', 'email', 'is_active', 'created_at', 'roles_count'];
    }

    /**
     * Filter validation rules for users.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'filters.q' => ['nullable', 'string'],
            'filters.name' => ['nullable', 'string'],
            'filters.email' => ['nullable', 'string'],
            'filters.role_id' => ['nullable', 'integer'],
            'filters.is_active' => ['nullable', 'boolean'],
            'filters.created_between.from' => ['nullable', 'date'],
            'filters.created_between.to' => ['nullable', 'date'],
        ];
    }

    /**
     * Default per-page for users index.
     */
    protected function defaultPerPage(): int
    {
        return 10; // keep consistent with Roles default
    }
}
