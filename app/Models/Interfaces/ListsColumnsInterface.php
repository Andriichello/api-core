<?php

namespace Core\Models\Interfaces;

/**
 * Interface ListsColumnsInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Interfaces
 */
interface ListsColumnsInterface
{
    /**
     * Returns a list of columns that are defined on a model.
     *
     * @param bool $guarded
     *
     * @return array<int, string>
     * @SuppressWarnings(PHPMD)
     */
    public function listOfColumns(bool $guarded = false): array;
}
