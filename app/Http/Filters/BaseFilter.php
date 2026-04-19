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
