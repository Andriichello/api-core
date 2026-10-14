<?php

namespace Core\Http\Filters\Operations;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Queries\BaseQuery;

/**
 * Class ContainsFilter.
 *
 * Matches the columns that contain the value as it is given: unlike
 * with `like`, `%` and `_` in the value are no wildcards, so the value
 * a user typed can be passed as is.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters\Operations
 */
class ContainsFilter extends LikeFilter
{
    /**
     * Character, which escapes `%`, `_` and itself in the value. Not a
     * backslash, which MySQL would need to be written differently.
     *
     * @var string
     */
    protected const string ESCAPE = '!';

    /**
     * Filter operation to be applied.
     *
     * @var FilterOperation|null
     */
    protected ?FilterOperation $operation = FilterOperation::CONTAINS;

    /**
     * Add a single comparison of the column containing the value.
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
        $column = $this->wrap($query, $column);
        $escape = static::ESCAPE;

        $sql = $this->hasFlag(FilterFlag::CaseInsensitive)
            ? "LOWER($column) $operator LOWER(?) ESCAPE '$escape'"
            : "$column $operator ? ESCAPE '$escape'";

        $query->whereRaw($sql, ['%' . $this->escape((string) $value) . '%'], $boolean);
    }

    /**
     * Escape the characters that `LIKE` would take for wildcards.
     *
     * @param string $value
     *
     * @return string
     */
    protected function escape(string $value): string
    {
        $escape = static::ESCAPE;

        return str_replace(
            [$escape, '%', '_'],
            [$escape . $escape, $escape . '%', $escape . '_'],
            $value
        );
    }
}
