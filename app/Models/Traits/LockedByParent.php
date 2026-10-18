<?php

namespace Core\Models\Traits;

use Core\Exceptions\RecordLockedException;
use Core\Models\BaseModel;
use Core\Models\Interfaces\LockableInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait LockedByParent.
 *
 * For records, which are parts of a lockable one (see Lockable), such as the
 * items of an invoice: they can't be created, changed or deleted, while that
 * record is locked, nor moved out of it, as RecordLockedException is thrown.
 * The model names that record in `lockingParent()`, which is asked on a copy
 * of the model without its loaded relations, so the record is loaded as it
 * is stored (its relation may be loaded lazily there).
 *
 * @mixin BaseModel
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait LockedByParent
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootLockedByParent(): void
    {
        $ensure = fn (self $model) => $model->ensureParentIsUnlocked();

        static::creating($ensure);
        static::updating($ensure);
        static::deleting($ensure);
    }

    /**
     * Get the record, which the model is a part of, and which locks it.
     *
     * @return (Model&LockableInterface)|null
     */
    abstract public function lockingParent(): (Model&LockableInterface)|null;

    /**
     * Make sure the record, which the model is a part of, isn't locked, and
     * neither is the one, which it was a part of, so a part can't be moved
     * out of a locked record. Both are loaded as they are stored, as a loaded
     * one may be stale.
     *
     * @return void
     * @throws RecordLockedException
     */
    protected function ensureParentIsUnlocked(): void
    {
        $parents = [$this->storedLockingParent($this->getAttributes())];

        if ($this->exists) {
            $parents[] = $this->storedLockingParent($this->getRawOriginal());
        }

        foreach ($parents as $parent) {
            if ($parent !== null && $parent->isLocked()) {
                throw RecordLockedException::changingChildOf($parent);
            }
        }
    }

    /**
     * Get the record, which locks the model with the given attributes, as it
     * is stored: from a copy of the model without its loaded relations.
     *
     * @param array<string, mixed> $attributes
     *
     * @return (Model&LockableInterface)|null
     */
    protected function storedLockingParent(array $attributes): (Model&LockableInterface)|null
    {
        $model = clone $this;
        $model->setRawAttributes($attributes);
        $model->setRelations([]);
        $model->preventsLazyLoading = false;

        return $model->lockingParent();
    }
}
