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

        $this->hasFlag(FilterFlag::CaseInsensitive)
            ? $this->applyCI($query, $column, $value, $boolean)
            : $query->whereIn($column, $value, $boolean, not: $this->hasFlag(FilterFlag::Not));
    }

    /**
     * Apply a case-insensitive filter to the given query builder instance.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     *
     * @return void
     */
    protected function applyCI(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void
    {
        $operator = $this->hasFlag(FilterFlag::Not) ? 'NOT IN' : 'IN';
        $placeholders = implode(', ', array_fill(0, count($value), 'LOWER(?)'));

        $query->whereRaw("LOWER({$column}) {$operator} ({$placeholders})", $value, $boolean);
    }
}
