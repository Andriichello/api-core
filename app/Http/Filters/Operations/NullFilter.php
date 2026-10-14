<?php

namespace Core\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\OperationFilter;
use Core\Queries\BaseQuery;
use InvalidArgumentException;

/**
 * Class NullFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
class NullFilter extends OperationFilter
{
    /**
     * Filter operation to be applied.
     *
     * @var FilterOperation|null
     */
    protected ?FilterOperation $operation = FilterOperation::NULL;

    /**
     * Apply filter to the given query builder instance. An empty value, or
     * a true one, asks for the column to be null, and a false one (`false`,
     * `0`, `no`, `off`) asks for it not to be. The `not` flag inverts that.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function apply(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void
    {
        if ($this->hasFlag(FilterFlag::CaseInsensitive)) {
            throw new InvalidArgumentException("The 'ci' flag is not supported by the 'null' operation.");
        }

        $isNull = $this->isNull($value) !== $this->hasFlag(FilterFlag::Not);

        $isNull
            ? $query->whereNull($this->qualify($column), $boolean)
            : $query->whereNotNull($this->qualify($column), $boolean);
    }

    /**
     * Determine if the given value asks for the column to be null.
     *
     * @param mixed $value
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    protected function isNull(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $isNull = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($isNull === null) {
            throw new InvalidArgumentException(
                "The 'null' operation takes true or false, such as [null]=false."
            );
        }

        return $isNull;
    }
}
