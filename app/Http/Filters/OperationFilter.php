<?php

namespace Core\Http\Filters;

use Core\Enums\FilterOperation;
use InvalidArgumentException;

/**
 * Class OperationFilter.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
abstract class OperationFilter extends BaseFilter
{
    /**
     * Set filter operation to be applied.
     *
     * @param FilterOperation $operation
     *
     * @return static
     */
    public function setOperation(FilterOperation $operation): static
    {
        if ($operation !== $this->operation) {
            throw new InvalidArgumentException(
                'The ' . static::class . '\'s operation cannot be changed'
            );
        }

        return $this;
    }
}
