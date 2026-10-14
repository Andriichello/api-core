<?php

namespace Core\Http\Filters;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Queries\BaseQuery;

/**
 * Class BaseFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
abstract class BaseFilter
{
    /**
     * Table on which the filter is applied.
     *
     * @var string|null
     */
    protected ?string $table = null;

    /**
     * Filter operation to be applied.
     *
     * @var FilterOperation|null
     */
    protected ?FilterOperation $operation = null;

    /**
     * Filter flags to be applied.
     *
     * @var FilterFlag[]
     */
    protected array $flags = [];

    /**
     * BaseFilter constructor.
     */
    public function __construct(?string $table = null)
    {
        $this->table = $table;
    }

    /**
     * Set filter operation to be applied.
     *
     * @param FilterOperation $operation
     *
     * @return static
     */
    public function setOperation(FilterOperation $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * Set filter flags to be applied.
     *
     * @param array $flags
     *
     * @return static
     */
    public function setFlags(array $flags): static
    {
        $this->flags = $flags;

        return $this;
    }

    /**
     * Returns true if given flags (all of them) are set on the filter.
     *
     * @param FilterFlag $flag
     * @param FilterFlag ...$more
     *
     * @return bool
     */
    public function hasFlag(FilterFlag $flag, FilterFlag ...$more): bool
    {
        array_unshift($more, $flag);

        foreach ($more as $value) {
            if (!in_array($value, $this->flags)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if given flags (at least one of them) are not set on the filter.
     *
     * @param FilterFlag $flag
     * @param FilterFlag ...$more
     *
     * @return bool
     */
    public function hasNoFlag(FilterFlag $flag, FilterFlag ...$more): bool
    {
        array_unshift($more, $flag);

        foreach ($more as $value) {
            if (in_array($value, $this->flags)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the given column, prefixed with the filter's table,
     * unless the column already names its table.
     *
     * @param string $column
     *
     * @return string
     */
    protected function qualify(string $column): string
    {
        if (empty($this->table) || str_contains($column, '.')) {
            return $column;
        }

        return $this->table . '.' . $column;
    }

    /**
     * Get the given column, qualified and wrapped, to be written into raw SQL.
     *
     * @param BaseQuery $query
     * @param string $column
     *
     * @return string
     */
    protected function wrap(BaseQuery $query, string $column): string
    {
        return $query->getQuery()
            ->getGrammar()
            ->wrap($this->qualify($column));
    }

    /**
     * Compare the column to the value with the given operator,
     * case-insensitively when the filter has the `ci` flag.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @param string $boolean
     *
     * @return void
     */
    protected function compare(
        BaseQuery $query,
        string $column,
        string $operator,
        mixed $value,
        string $boolean = 'and'
    ): void {
        $this->hasFlag(FilterFlag::CaseInsensitive)
            ? $query->whereRaw('LOWER(' . $this->wrap($query, $column) . ") $operator LOWER(?)", [$value], $boolean)
            : $query->where($this->qualify($column), $operator, $value, $boolean);
    }

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
    abstract public function apply(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void;
}
