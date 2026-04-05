<?php

namespace Core\Repositories\Traits;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\DefaultFilter;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Core\Helpers\Helpers;
use Core\Http\Filters\BaseFilter;
use Core\Repositories\CrudRepository;

/**
 * Trait AllowsFilters.
 *
 * Contains logic for getting the list of filters,
 * which should be applied for the given request.
 * Throws exception when asked filter is not allowed.
 *
 * By default, all model's fillable columns along with
 * its primary key and timestamps will be allowed.
 *
 * @mixin CrudRepository
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories\Traits
 */
trait AllowsFilters
{
    /**
     * Determines if all fillable columns as well as
     * timestamps and identifiers should be allowed
     * to filter on.
     *
     * @var bool
     */
    protected bool $allowAllFilters = true;

    /**
     * List of filters allowed on request.
     *
     * @var array<int|string, string|BaseFilter|array{string, ?string, ?string}>
     */
    protected array $allowedFilters = [];

    /**
     * Get the list of filters allowed on request.
     *
     * @return array<int, string>
     */
    public function listOfAllowedFilters(): array
    {
        $allowed = $this->allowedFilters ?? [];

        if (!$this->allowAllFilters && count($allowed)) {
            return $allowed;
        }

        $all = array_merge($allowed, $this->instance()?->listOfColumns() ?? []);
        $list = [];

        foreach ($all as $key => $value) {
            $list[] = is_string($key) ? $key : $value;
        }

        return $list;
    }

    /**
     * Get the class for the given filter.
     *
     * @param string $filter
     *
     * @return string
     */
    public function classOfAllowedFilter(string $filter): string
    {
        $entry = data_get($this->allowedFilters ?? [], $filter);

        if (is_string($entry)) {
            return $entry;
        }

        return data_get($entry, 0) ?? DefaultFilter::class;
    }

    /**
     * Get the column for the given filter.
     *
     * @param string $filter
     *
     * @return string|null
     */
    public function columnOfAllowedFilter(string $filter): ?string
    {
        $entry = data_get($this->allowedFilters ?? [], $filter);
        return data_get($entry, 1);
    }

    /**
     * Get the table for the given filter.
     *
     * @param string $filter
     *
     * @return string|null
     */
    public function tableOfAllowedFilter(string $filter): ?string
    {
        $entry = data_get($this->allowedFilters ?? [], $filter);
        return data_get($entry, 2);
    }

    /**
     * Determines if all given filters are allowed on request.
     *
     * @param string|array $names
     *
     * @return bool
     */
    public function hasAllowedFilters(string|array $names): bool
    {
        $names = Arr::wrap($names);
        $allowed = $this->listOfAllowedFilters();

        foreach ($names as $filter) {
            if (!in_array($filter, $allowed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get an array of filter keys to values. By default,
     * all allowed filters will be returned. If given filters
     * are not allowed, then an exception will be thrown.
     *
     * @param array $filters
     * @param string|array|null $names
     *
     * @return array<array{
     *       field: string,
     *       operation: FilterOperation,
     *       flags: FilterFlag[],
     *       value: mixed,
     *   }>
     * @throws Exception
     */
    public function getAllowedFilters(array $filters, string|array|null $names = null): array
    {
        $names = $names !== null ? Arr::wrap($names)
            : $this->listOfAllowedFilters();

        $allowed = [];

        $fields = array_map(
            fn($filter) => $filter['field'] ?? null,
            $filters
        );

        $fields = array_unique(array_filter($fields));

        foreach ($names as $name) {
            if (in_array($name, $fields)) {
                if (!$this->hasAllowedFilters($name)) {
                    $message = "The '$name' filter is not allowed on "
                        . static::class;

                    throw new Exception($message);
                }

                $allowed = array_merge(
                    $allowed,
                    ...array_filter(
                        $filters,
                        fn($filter) => $filter['field'] === $name
                    )
                );
            }
        }

        return $allowed;
    }

    /**
     * Get an array of requested filters. If requested filters
     * are not allowed, then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array<array{
     *      field: string,
     *      operation: FilterOperation,
     *      flags: FilterFlag[],
     *      value: mixed,
     *  }>
     * @throws Exception
     */
    public function getRequestedFilters(Request $request): array
    {
        return $this->getAllowedFilters(
            $filters = Helpers::requestedFilters($request),
            array_keys($filters)
        );
    }
}
