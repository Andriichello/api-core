<?php

namespace Core\Http\Controllers\Traits;

use Core\Http\Controllers\Interfaces\WithPaginationInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Trait WithPagination.
 *
 * Reads `page[number]` and `page[size]` from the request (the names come
 * from `config/pagination.php`), caps the size at the configured maximum
 * and paginates query builders with them.
 *
 * @see WithPaginationInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers\Traits
 */
trait WithPagination
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
    ): Builder|EloquentBuilder {
        return $builder->forPage(
            $this->getPageNumber($request),
            $this->getPageSize($request)
        );
    }

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
    ): Paginator|LengthAwarePaginator {
        $request ??= request();

        $pageParameter = config('pagination.pagination_parameter');
        $numberParameter = config('pagination.number_parameter');

        $paginator = $builder
            ->{$this->getPaginationMethod()}(
                $this->getPageSize($request),
                ['*'],
                $pageParameter . '.' . $numberParameter,
                $this->getPageNumber($request)
            )
            ->setPageName($pageParameter . '[' . $numberParameter . ']')
            ->appends(Arr::except($request->query(), [
                $pageParameter . '.' . $numberParameter,
            ]));

        if (!is_null(config('pagination.base_url'))) {
            $paginator->setPath(config('pagination.base_url'));
        }

        return $paginator;
    }

    /**
     * Get the maximum size of the page for pagination.
     *
     * @return int
     */
    public function getMaxPageSize(): int
    {
        return config('pagination.max_results', 1000);
    }

    /**
     * Get the default size of the page for pagination.
     *
     * @return int
     */
    public function getDefaultPageSize(): int
    {
        return config('pagination.default_size', 100);
    }

    /**
     * Get the name of method, which should be used on query builder
     * for pagination.
     *
     * @return string
     */
    public function getPaginationMethod(): string
    {
        return config('pagination.use_simple_pagination', false)
            ? 'simplePaginate' : 'paginate';
    }

    /**
     * Get the size of the page for pagination: the requested one,
     * capped at the maximum, or the default one when the requested
     * size is missing or not a positive number.
     *
     * @param Request|null $request
     *
     * @return int
     */
    public function getPageSize(?Request $request = null): int
    {
        $parameter = config('pagination.pagination_parameter')
            . '.' . config('pagination.size_parameter');

        $size = ($request ?? request())
            ->integer($parameter, $this->getDefaultPageSize());

        if ($size < 1) {
            $size = $this->getDefaultPageSize();
        }

        return min($size, $this->getMaxPageSize());
    }

    /**
     * Get the number of the page for pagination: the requested one,
     * or 1 when it is missing or not a positive number.
     *
     * @param Request|null $request
     *
     * @return int
     */
    public function getPageNumber(?Request $request = null): int
    {
        $value = ($request ?? request())->input(config('pagination.pagination_parameter'));

        if (is_array($value)) {
            $value = data_get($value, config('pagination.number_parameter'));
        }

        return (is_numeric($value) && $value > 0) ? (int) $value : 1;
    }
}
