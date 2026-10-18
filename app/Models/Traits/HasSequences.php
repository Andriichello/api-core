<?php

namespace Core\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\Sequence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasSequences.
 *
 * For models, which own sequences of numbers (see Sequence), such as
 * a profile, whose invoices are numbered under `invoices`. Deleting the
 * model deletes its sequences, though a soft-deleted model keeps them,
 * until it is deleted permanently.
 *
 * @mixin BaseModel
 * @property Collection<int, Sequence> $sequences
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait HasSequences
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootHasSequences(): void
    {
        static::deleted(function (self $model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->sequences()->delete();
        });
    }

    /**
     * Sequences of the model.
     *
     * @return MorphMany
     */
    public function sequences(): MorphMany
    {
        return $this->morphMany(Sequence::class, 'model');
    }

    /**
     * Allocate the next number of the model's sequence under the key.
     *
     * @param string $key
     *
     * @return int
     */
    public function nextInSequence(string $key): int
    {
        return Sequence::next($this, $key);
    }

    /**
     * Set the number, which the model's sequence under the key allocates next.
     *
     * @param string $key
     * @param int $number
     *
     * @return void
     */
    public function setNextInSequence(string $key, int $number): void
    {
        Sequence::setNext($this, $key, $number);
    }
}
