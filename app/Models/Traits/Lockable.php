<?php

namespace Core\Models\Traits;

use Core\Exceptions\RecordLockedException;
use Core\Helpers\Helpers;
use Core\Models\BaseModel;
use Core\Models\Interfaces\LockableInterface;
use Illuminate\Database\Eloquent\SoftDeletes;

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
     * it is locked, or restores it from a soft delete.
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
            array_filter([$this->getCreatedAtColumn(), $this->getUpdatedAtColumn(), $this->restoredColumn()])
        );

        $locked = array_values(array_diff(array_keys($this->getDirty()), $allowed));

        if (!empty($locked)) {
            throw RecordLockedException::changing($this, $locked);
        }
    }

    /**
     * Get the column of the soft delete, when the change clears it, which
     * restores the record as it was. Setting it would delete the record,
     * which a locked record refuses, as `delete()` does.
     *
     * @return string|null
     */
    protected function restoredColumn(): ?string
    {
        if (!Helpers::usesTrait($this, SoftDeletes::class)) {
            return null;
        }

        // As SoftDeletes::getDeletedAtColumn() names it, which not every model of the trait has
        $column = defined(static::class . '::DELETED_AT') ? constant(static::class . '::DELETED_AT') : 'deleted_at';

        return $this->isDirty($column) && $this->getAttribute($column) === null ? $column : null;
    }
}
