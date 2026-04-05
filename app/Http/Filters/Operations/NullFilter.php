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
     * Apply filter to the given query builder instance.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     *
     * @return void
     * @SuppressWarnings(PHPMD)
     */
    public function apply(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void
    {
        if ($this->hasFlag(FilterFlag::CaseInsensitive)) {
            throw new InvalidArgumentException(
                'Case insensitive flag is not supported for NULL filter'
            );
        }

        $this->hasFlag(FilterFlag::Not)
            ? $query->whereNotNull($column, $boolean)
            : $query->whereNull($column, $boolean);
    }
}
