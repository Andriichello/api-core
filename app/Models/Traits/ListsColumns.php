<?php

namespace Core\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\Interfaces\ListsColumnsInterface;

/**
 * Trait ListsColumns.
 *
 * @mixin BaseModel
 * @implements ListsColumnsInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait ListsColumns
{
    /**
     * Returns a list of columns that are defined on a model.
     *
     * @param bool $guarded
     *
     * @return array<int, string>
     * @SuppressWarnings(PHPMD)
     */
    public function listOfColumns(bool $guarded = false): array
    {
        $columns = [
            $this->getKeyName(),
            ...$this->getFillable(),
        ];

        if ($guarded) {
            $columns = array_merge(
                $columns,
                $this->getGuarded(),
            );
        }

        if ($this->usesTimestamps()) {
            $columns[] = $this->getCreatedAtColumn();
            $columns[] = $this->getUpdatedAtColumn();
        }

        return array_values(array_unique($columns));
    }

    /**
     * Returns a list of columns that may be filtered and sorted on by default.
     * These are the model's columns without its hidden ones: a filter or a sort
     * on a password or a token would reveal it, one request at a time, without
     * the value ever being returned.
     *
     * @return array<int, string>
     */
    public function listOfQueryableColumns(): array
    {
        return array_values(
            array_diff(
                $this->listOfColumns(),
                $this->getHidden(),
            )
        );
    }
}
