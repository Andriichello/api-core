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
}
