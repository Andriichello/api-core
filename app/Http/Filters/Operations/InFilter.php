<?php

namespace Core\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\OperationFilter;
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

        $placeholders = implode(', ', array_fill(0, count($value), 'LOWER(?)'));
        $operator = $this->hasFlag(FilterFlag::Not) ? 'NOT IN' : 'IN';
        $raw = "LOWER({$column}) {$operator} ({$placeholders})";

        $this->hasFlag(FilterFlag::CaseInsensitive)
            ? $query->whereRaw($raw, $value, $boolean)
            : $query->where($column, $operator, $value, $boolean);
    }
}
