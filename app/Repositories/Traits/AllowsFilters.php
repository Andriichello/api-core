<?php

namespace Core\Repositories\Traits;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Helpers\FilterHelper;
use Core\Http\Filters\BaseFilter;
use Core\Http\Filters\DateFilter;
use Core\Http\Filters\DefaultFilter;
use Core\Repositories\CrudRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Trait AllowsFilters.
 *
 * Contains logic for getting the list of filters,
 * which should be applied for the given request.
 * Throws a validation exception (422) when asked filter
 * is not allowed or the filter parameter is malformed.
 *
 * By default, all model's fillable columns along with
 * its primary key and timestamps will be allowed,
 * except for its hidden columns.
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
     * to filter on. Hidden columns are never among
     * them, unless they are listed explicitly. When
     * false, only the listed filters are allowed.
     *
     * @var bool
     */
    protected bool $allowAllFilters = true;

    /**
     * List of filters allowed on request.
     *
     * @var array<int|string, string|BaseFilter|array{0: string, 1?: string|null, 2?: string|null}>
     */
    protected array $allowedFilters = [];

    /**
     * Get the list of filters allowed on request.
     *
     * @return array<int, string>
     */
    public function listOfAllowedFilters(): array
    {
        // Only the listed ones (none, when the list is empty), unless all are allowed
        $all = $this->allowAllFilters
            ? array_merge($this->allowedFilters, $this->instance()?->listOfQueryableColumns() ?? [])
            : $this->allowedFilters;

        $list = [];

        foreach ($all as $key => $value) {
            $list[] = is_string($key) ? $key : $value;
        }

        return $list;
    }

    /**
     * Get the class for the given filter: the one it is allowed with, or
     * DateFilter for a timestamp or a date column of the model, and
     * DefaultFilter for any other one.
     *
     * @param string $filter
     *
     * @return string
     */
    public function classOfAllowedFilter(string $filter): string
    {
        $entry = data_get($this->allowedFilters, $filter);

        if (is_string($entry)) {
            return $entry;
        }

        return data_get($entry, 0)
            ?? ($this->isDateFilter($filter) ? DateFilter::class : DefaultFilter::class);
    }

    /**
     * Determine if the given filter is on a column of the model, which is
     * a timestamp (such as `created_at`) or has a date or datetime cast.
     *
     * @param string $filter
     *
     * @return bool
     */
    public function isDateFilter(string $filter): bool
    {
        $model = $this->instance();
        $table = $this->tableOfAllowedFilter($filter);

        if (!$model || ($table !== null && $table !== $model->getTable())) {
            return false;
        }

        $column = $this->columnOfAllowedFilter($filter) ?? $filter;

        if (in_array($column, $model->getDates(), true)) {
            return true;
        }

        $cast = $model->getCasts()[$column] ?? null;

        return is_string($cast)
            && preg_match('/^(immutable_)?(date|datetime|timestamp)(:|$)/', $cast) === 1;
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
        $entry = data_get($this->allowedFilters, $filter);

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
        $entry = data_get($this->allowedFilters, $filter);

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
     * are not allowed, then a validation exception (422)
     * will be thrown.
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
     * @throws ValidationException
     */
    public function getAllowedFilters(array $filters, string|array|null $names = null): array
    {
        $names = $names !== null ? Arr::wrap($names)
            : $this->listOfAllowedFilters();

        $allowed = [];

        $fields = array_map(
            fn ($filter) => $filter['field'] ?? null,
            $filters
        );

        $fields = array_unique(array_filter($fields));

        foreach ($names as $name) {
            if (in_array($name, $fields)) {
                if (!$this->hasAllowedFilters($name)) {
                    throw $this->notAllowedFilter($name);
                }

                array_push(
                    $allowed,
                    ...array_filter(
                        $filters,
                        fn ($filter) => $filter['field'] === $name
                    )
                );
            }
        }

        return $allowed;
    }

    /**
     * Get an array of requested filters. If the filter parameter
     * is malformed, or names a filter that is not allowed, then
     * a validation exception (422) will be thrown.
     *
     * @param Request $request
     *
     * @return array<array{
     *      field: string,
     *      operation: FilterOperation,
     *      flags: FilterFlag[],
     *      value: mixed,
     *  }>
     * @throws ValidationException
     */
    public function getRequestedFilters(Request $request): array
    {
        $input = $request->input('filter');

        if ($input === null || $input === '') {
            return [];
        }

        if (!is_array($input)) {
            throw ValidationException::withMessages([
                'filter' => [
                    'The filter parameter must name the filters, such as filter[name][eq]=value.',
                ],
            ]);
        }

        $filters = [];

        foreach ($input as $field => $node) {
            $field = (string) $field;

            if (!$this->hasAllowedFilters($field)) {
                throw $this->notAllowedFilter($field);
            }

            try {
                array_push($filters, ...FilterHelper::parseNode($field, $node));
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    "filter.$field" => [$exception->getMessage()],
                ]);
            }
        }

        return $this->getAllowedFilters(
            $filters,
            array_values(array_unique(array_column($filters, 'field')))
        );
    }

    /**
     * Get a validation exception (422) for the given filter,
     * which is not allowed, naming the filters that are.
     *
     * @param string $name
     *
     * @return ValidationException
     */
    protected function notAllowedFilter(string $name): ValidationException
    {
        $allowed = array_unique($this->listOfAllowedFilters());

        return ValidationException::withMessages([
            "filter.$name" => [
                "The '$name' filter is not allowed. " . (empty($allowed)
                    ? 'No filters are allowed.'
                    : 'Allowed filters: ' . implode(', ', $allowed) . '.'),
            ],
        ]);
    }
}
