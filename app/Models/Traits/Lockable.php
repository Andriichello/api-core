<?php

namespace Core\Models\Traits;

use Core\Exceptions\RecordLockedException;
use Core\Models\BaseModel;
use Core\Models\Interfaces\LockableInterface;

/**
 * Trait Lockable.
 *
 * For records, which are locked in some state, such as an invoice, which
 * is past its draft, and which implement LockableInterface with it. A locked
 * record's attributes can't change, except for `unlockedAttributes()` and the
 * timestamps, and it can't be deleted: RecordLockedException is thrown. A
 * change, which unlocks the record, is allowed as a whole, such as an invoice
 * going back to its draft, which clears what it froze, so whatever unlocks a
 * record must be guarded itself (see TransitionsStatus). Like other model
 * events, it doesn't guard updates and deletes of queries, or changes within
 * `Model::withoutEvents()`.
 *
 * @mixin BaseModel
 * @see LockableInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait Lockable
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootLockable(): void
    {
        static::updating(fn (self $model) => $model->ensureChangeIsUnlocked());

        static::deleting(function (self $model) {
            if ($model->wasLocked()) {
                throw RecordLockedException::deleting($model);
            }
        });
    }

    /**
     * Determine if the record is locked, judging by its attributes.
     *
     * @return bool
     */
    abstract public function isLocked(): bool;

    /**
     * Get the attributes, which may change, while the record is locked.
     *
     * @return array<int, string>
     */
    public function unlockedAttributes(): array
    {
        return [];
    }

    /**
     * Determine if the record was locked, before its unsaved changes.
     *
     * @return bool
     */
    public function wasLocked(): bool
    {
        $original = clone $this;
        $original->setRawAttributes($this->getRawOriginal(), true);

        return $original->isLocked();
    }

    /**
     * Make sure the record's change is allowed: it was unlocked, the change
     * unlocks it, or it changes only the attributes, which may change, while
     * it is locked.
     *
     * @return void
     * @throws RecordLockedException
     */
    protected function ensureChangeIsUnlocked(): void
    {
        if (!$this->wasLocked() || !$this->isLocked()) {
            return;
        }

        $allowed = array_merge(
            $this->unlockedAttributes(),
            array_filter([$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()])
        );

        $locked = array_values(array_diff(array_keys($this->getDirty()), $allowed));

        if (!empty($locked)) {
            throw RecordLockedException::changing($this, $locked);
        }
    }
}
