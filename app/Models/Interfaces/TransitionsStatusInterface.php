<?php

namespace Core\Models\Interfaces;

use BackedEnum;
use Core\Models\Traits\TransitionsStatus;

/**
 * Interface TransitionsStatusInterface.
 *
 * @see TransitionsStatus
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Interfaces
 */
interface TransitionsStatusInterface
{
    /**
     * Get the statuses, to which each status may change.
     *
     * @return array<string, array<int, BackedEnum|string>>
     */
    public function statusTransitions(): array;

    /**
     * Get the column of the record's status.
     *
     * @return string
     */
    public function statusColumn(): string;

    /**
     * Determine if the record's stored status may change to the given one.
     *
     * @param BackedEnum|string $status
     *
     * @return bool
     */
    public function canTransitionTo(BackedEnum|string $status): bool;
}
