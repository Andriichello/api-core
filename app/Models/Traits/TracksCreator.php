<?php

namespace Core\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait TracksCreator.
 *
 * For models, which record the user, who created them, in their
 * `created_by_user_id` column: a new record gets the signed-in user, unless
 * it is given one. Records created without a signed-in user (console, jobs)
 * have none. The column should be a nullable foreign key, which is set to
 * null, when the user is deleted:
 * `$table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete()`.
 *
 * @mixin BaseModel
 * @property int|null $created_by_user_id
 * @property User|null $creator
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait TracksCreator
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootTracksCreator(): void
    {
        static::creating(function (self $model) {
            if ($model->getAttribute('created_by_user_id') === null) {
                $model->setAttribute('created_by_user_id', Auth::id());
            }
        });
    }

    /**
     * User, who created the model.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
