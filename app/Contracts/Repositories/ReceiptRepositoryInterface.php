<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\DTO\ListQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReceiptRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  list<string>  $with
     * @param  list<string>  $withCount
     * @return LengthAwarePaginator<int, Model>
     */
    public function list(ListQuery $query, array $with = [], array $withCount = []): LengthAwarePaginator;

    /**
     * @param  list<string>  $with
     * @param  list<string>  $withCount
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(ListQuery $query, array $with = [], array $withCount = []): LengthAwarePaginator;

    /**
     * @param  list<string>  $with
     */
    public function findById(int|string $id, array $with = []): ?Model;

    /**
     * @param  list<string>  $with
     */
    public function findOrFailById(int|string $id, array $with = []): Model;

    public function create(array $attributes): Model;
}
