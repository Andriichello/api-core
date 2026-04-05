<?php

namespace Core\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\OperationFilter;
use Core\Queries\BaseQuery;

/**
 * Class LteFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
class LteFilter extends OperationFilter
{
    /**
     * Filter operation to be applied.
     *
     * @var FilterOperation|null
     */
    protected ?FilterOperation $operation = FilterOperation::LTE;

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
        $operator = $this->hasFlag(FilterFlag::Not) ? '>' : '<=';

        $raw = "LOWER({$column}) {$operator} LOWER(?)";

        $this->hasNoFlag(FilterFlag::CaseInsensitive)
            ? $query->where($column, $operator, $value, $boolean)
            : $query->whereRaw($raw, [$value], $boolean);
    }
}
