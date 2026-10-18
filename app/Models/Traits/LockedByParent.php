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
 * record is locked, as RecordLockedException is thrown. The model names that
 * record in `lockingParent()`.
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
     * Make sure the record, which the model is a part of, isn't locked.
     *
     * @return void
     * @throws RecordLockedException
     */
    protected function ensureParentIsUnlocked(): void
    {
        $parent = $this->lockingParent();

        if ($parent !== null && $parent->isLocked()) {
            throw RecordLockedException::changingChildOf($parent);
        }
    }
}
