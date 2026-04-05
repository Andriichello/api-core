<?php

namespace Core\Http\Filters;

use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\EqFilter;
use Core\Http\Filters\Operations\GtFilter;
use Core\Http\Filters\Operations\GteFilter;
use Core\Http\Filters\Operations\InFilter;
use Core\Http\Filters\Operations\LikeFilter;
use Core\Http\Filters\Operations\LtFilter;
use Core\Http\Filters\Operations\LteFilter;
use Core\Http\Filters\Operations\NullFilter;
use Core\Queries\BaseQuery;
use InvalidArgumentException;

/**
 * Class DefaultFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
class DefaultFilter extends BaseFilter
{
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
        if (empty($this->operation)) {
            throw new InvalidArgumentException(
                'Filter operation must be set before applying filter'
            );
        }

        $filter = match ($this->operation) {
            FilterOperation::EQ => new EqFilter($this->table),
            FilterOperation::IN => new InFilter($this->table),
            FilterOperation::NULL => new NullFilter($this->table),
            FilterOperation::GT => new GtFilter($this->table),
            FilterOperation::GTE => new GteFilter($this->table),
            FilterOperation::LT => new LtFilter($this->table),
            FilterOperation::LTE => new LteFilter($this->table),
            FilterOperation::LIKE => new LikeFilter($this->table),
        };

        // Will throw an exception if there is an operation mismatch
        $filter->setOperation($this->operation);
        $filter->setFlags($this->flags);

        $filter->apply($query, $column, $value, $boolean);
    }
}
