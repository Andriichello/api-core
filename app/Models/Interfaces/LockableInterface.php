<?php

namespace Core\Models\Interfaces;

use Core\Models\Traits\Lockable;

/**
 * Interface LockableInterface.
 *
 * @see Lockable
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Interfaces
 */
interface LockableInterface
{
    /**
     * Determine if the record is locked, judging by its attributes, such as
     * a status, which is past the draft.
     *
     * @return bool
     */
    public function isLocked(): bool;

    /**
     * Determine if the record was locked, before its unsaved changes.
     *
     * @return bool
     */
    public function wasLocked(): bool;

    /**
     * Get the attributes, which may change, while the record is locked, such
     * as its status or notes.
     *
     * @return array<int, string>
     */
    public function unlockedAttributes(): array;
}
