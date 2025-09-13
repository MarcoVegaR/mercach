<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Request validation for showing a single user.
 */
class UserShowRequest extends BaseShowRequest
{
    /**
     * Allowed relations to eager load.
     *
     * @return array<string>
     */
    protected function allowedRelations(): array
    {
        return ['roles'];
    }

    /**
     * Allowed relations to count.
     *
     * @return array<string>
     */
    protected function allowedCounts(): array
    {
        return ['roles'];
    }

    /**
     * Allowed appended attributes.
     *
     * @return array<string>
     */
    protected function allowedAppends(): array
    {
        return [];
    }
}
