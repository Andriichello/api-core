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
     * Set filter operation to be applied, which can only be the filter's own.
     *
     * @param FilterOperation $operation
     *
     * @return static
     * @throws InvalidArgumentException When the operation isn't the filter's own.
     */
    public function setOperation(FilterOperation $operation): static
    {
        if ($operation !== $this->operation) {
            throw new InvalidArgumentException(
                "The '{$operation->value}' operation is not allowed. Allowed operations: {$this->operation?->value}."
            );
        }

        return $this;
    }
}
