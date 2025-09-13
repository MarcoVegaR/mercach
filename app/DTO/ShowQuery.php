<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for show queries.
 *
 * Provides a structured way to pass show query parameters
 * including eager loading, counts, appends, and soft deletes.
 *
 * @author Laravel Boilerplate
 */
class ShowQuery
{
    /**
     * Create a new ShowQuery instance.
     *
     * @param  array<string>  $with  Relations to eager load
     * @param  array<string>  $withCount  Relations to count
     * @param  array<string>  $append  Attributes to append
     * @param  bool  $withTrashed  Include soft deleted records
     */
    public function __construct(
        public readonly array $with = [],
        public readonly array $withCount = [],
        public readonly array $append = [],
        public readonly bool $withTrashed = false,
    ) {}

    /**
     * Create from array of parameters.
     *
     * @param  array<string, mixed>  $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            with: $params['with'] ?? [],
            withCount: $params['withCount'] ?? [],
            append: $params['append'] ?? [],
            withTrashed: $params['withTrashed'] ?? false,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'with' => $this->with,
            'withCount' => $this->withCount,
            'append' => $this->append,
            'withTrashed' => $this->withTrashed,
        ];
    }

    /**
     * Check if eager loading is requested.
     */
    public function hasRelations(): bool
    {
        return ! empty($this->with);
    }

    /**
     * Check if counts are requested.
     */
    public function hasCounts(): bool
    {
        return ! empty($this->withCount);
    }

    /**
     * Check if appends are requested.
     */
    public function hasAppends(): bool
    {
        return ! empty($this->append);
    }
}
