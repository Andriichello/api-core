<?php

namespace Core\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Queries\BaseQuery;
use InvalidArgumentException;

/**
 * Class InFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
class InFilter extends OperationFilter
{
    /**
     * Filter operation to be applied.
     *
     * @var FilterOperation|null
     */
    protected ?FilterOperation $operation = FilterOperation::IN;

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
                'Case insensitive flag is not supported for IN filter'
            );
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (is_scalar($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'Value for IN filter must be an array'
            );
        }

        $this->hasFlag(FilterFlag::Not)
            ? $query->whereNotIn($column, $value, $boolean)
            : $query->whereIn($column, $value, $boolean);
    }
}
