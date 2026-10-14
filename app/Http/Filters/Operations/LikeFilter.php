<?php

namespace Core\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\OperationFilter;
use Core\Queries\BaseQuery;
use Illuminate\Support\Arr;

/**
 * Class LikeFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
class LikeFilter extends OperationFilter
{
    /**
     * Filter operation to be applied.
     *
     * @var FilterOperation|null
     */
    protected ?FilterOperation $operation = FilterOperation::LIKE;

    /**
     * Apply filter to the given query builder instance. Given a list of
     * values, any of them has to match, or with `not`, none of them may.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     *
     * @return void
     */
    public function apply(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void
    {
        $values = array_values(Arr::wrap($value));

        if (count($values) === 1) {
            $this->like($query, $column, $values[0], $boolean);

            return;
        }

        $join = $this->hasFlag(FilterFlag::Not) ? 'and' : 'or';

        $query->whereWrapped(function (BaseQuery $query) use ($column, $values, $join) {
            foreach ($values as $value) {
                $this->like($query, $column, $value, $join);
            }
        }, $boolean);
    }

    /**
     * Add a single like comparison of the column to the value.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     *
     * @return void
     */
    protected function like(BaseQuery $query, string $column, mixed $value, string $boolean): void
    {
        $operator = $this->hasFlag(FilterFlag::Not) ? 'NOT LIKE' : 'LIKE';

        $this->hasFlag(FilterFlag::CaseInsensitive)
            ? $query->whereRaw('LOWER(' . $this->wrap($query, $column) . ") $operator LOWER(?)", [$value], $boolean)
            : $query->where($this->qualify($column), $operator, $value, $boolean);
    }
}
