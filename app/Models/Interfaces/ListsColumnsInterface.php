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

    /**
     * Returns a list of columns that may be filtered and sorted on by default:
     * the model's columns without its hidden ones.
     *
     * @return array<int, string>
     */
    public function listOfQueryableColumns(): array;
}
