<?php

namespace Core\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Queries\BaseQuery;
use InvalidArgumentException;

/**
 * Class GtFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
class GtFilter extends OperationFilter
{
    /**
     * Filter operation to be applied.
     *
     * @var FilterOperation|null
     */
    protected ?FilterOperation $operation = FilterOperation::GT;

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
                'Case insensitive flag is not supported for GT filter'
            );
        }

        $operator = $this->hasFlag(FilterFlag::Not) ? '<=' : '>';

        $query->where($column, $operator, $value, $boolean);
    }
}
