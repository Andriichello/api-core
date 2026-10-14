<?php

namespace Core\Http\Controllers\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Interface WithPaginationInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers\Interfaces
 */
interface WithPaginationInterface
{
    /**
     * Paginate query builder.
     *
     * @param Builder|EloquentBuilder $builder
     * @param Request|null $request
     *
     * @return Builder|EloquentBuilder
     */
    public function paginateQuery(
        Builder|EloquentBuilder $builder,
        ?Request $request = null
    ): Builder|EloquentBuilder;

    /**
     * Paginate query builder and return the paginator.
     *
     * @param Builder|EloquentBuilder $builder
     * @param Request|null $request
     *
     * @return Paginator|LengthAwarePaginator
     */
    public function paginate(
        Builder|EloquentBuilder $builder,
        ?Request $request = null
    ): Paginator|LengthAwarePaginator;

    /**
     * Get the size of the page for pagination.
     *
     * @param Request|null $request
     *
     * @return int
     */
    public function getPageSize(?Request $request = null): int;

    /**
     * Get the number of the page for pagination.
     *
     * @param Request|null $request
     *
     * @return int
     */
    public function getPageNumber(?Request $request = null): int;
}
