<?php

namespace Core\Models;

use Closure;
use Core\Database\Factories\SequenceFactory;
use Core\Queries\SequenceQuery;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;

/**
 * Class Sequence.
 *
 * A sequence of numbers of a model (its owner), under a key, such as
 * `invoices` or `invoices:client:5:2026`, which numbered records allocate
 * their numbers from with `next()`. A number is allocated under a lock of
 * the sequence's row, so concurrent allocations wait for each other, and
 * within the caller's transaction, so a rolled back allocation gives the
 * number back. A sequence starts at 1, unless `setNext()` sets it.
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $key
 * @property int $next_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property BaseModel $model
 *
 * @method static SequenceQuery query()
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
#[UseFactory(SequenceFactory::class)]
class Sequence extends BaseModel
{
    /** @use HasFactory<SequenceFactory> */
    use HasFactory;

    /**
     * Attempts of an allocation, which a deadlock interrupts: two first
     * allocations of a sequence may deadlock, while they create its row.
     *
     * @var int
     */
    public const int ATTEMPTS = 3;

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'next_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'model_id' => 'int',
        'next_number' => 'int',
    ];

    /**
     * Model, which owns the sequence.
     *
     * @return MorphTo
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Allocate the next number of the owner's sequence under the key.
     *
     * @param Model $owner
     * @param string $key
     *
     * @return int
     * @throws LogicException When the owner isn't saved.
     */
    public static function next(Model $owner, string $key): int
    {
        return static::withLockedRow($owner, $key, function (Sequence $sequence) {
            $number = $sequence->next_number;

            $sequence->forceFill(['next_number' => $number + 1])->save();

            return $number;
        });
    }

    /**
     * Set the number, which the owner's sequence under the key allocates
     * next, such as to continue the numbering of another system.
     *
     * @param Model $owner
     * @param string $key
     * @param int $number
     *
     * @return void
     * @throws InvalidArgumentException When the number is less than 1.
     * @throws LogicException When the owner isn't saved.
     */
    public static function setNext(Model $owner, string $key, int $number): void
    {
        if ($number < 1) {
            throw new InvalidArgumentException("The next number of a sequence must be at least 1, $number given.");
        }

        static::withLockedRow($owner, $key, fn (Sequence $sequence) => $sequence
            ->forceFill(['next_number' => $number])
            ->save());
    }

    /**
     * Get the number, which the owner's sequence under the key would allocate
     * next, without allocating it, such as to preview it. Another allocation
     * may take it in the meantime.
     *
     * @param Model $owner
     * @param string $key
     *
     * @return int
     */
    public static function peek(Model $owner, string $key): int
    {
        $number = static::query()
            ->whereOwnerAndKey($owner, $key)
            ->value('next_number');

        return $number === null ? 1 : (int) $number;
    }

    /**
     * Run the callback with the owner's sequence under the key, whose row is
     * locked until the transaction, in which it runs, ends. The row of a new
     * sequence is created first, unless a concurrent allocation creates it.
     *
     * @param Model $owner
     * @param string $key
     * @param Closure(Sequence): mixed $callback
     *
     * @return mixed
     * @throws LogicException When the owner isn't saved.
     */
    protected static function withLockedRow(Model $owner, string $key, Closure $callback): mixed
    {
        if (!$owner->exists) {
            throw new LogicException($owner::class . ' must be saved, before it allocates numbers.');
        }

        $connection = static::query()->getModel()->getConnection();

        return $connection->transaction(function () use ($owner, $key, $callback) {
            $sequence = static::lockedQuery($owner, $key)->first();

            if ($sequence === null) {
                $now = static::query()->getModel()->freshTimestampString();

                static::query()->insertOrIgnore([
                    'model_type' => $owner->getMorphClass(),
                    'model_id' => $owner->getKey(),
                    'key' => $key,
                    'next_number' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $sequence = static::lockedQuery($owner, $key)->firstOrFail();
            }

            return $callback($sequence);
        }, static::ATTEMPTS);
    }

    /**
     * Get the query of the owner's sequence under the key, which locks its
     * row for an update.
     *
     * @param Model $owner
     * @param string $key
     *
     * @return SequenceQuery
     */
    protected static function lockedQuery(Model $owner, string $key): SequenceQuery
    {
        return static::query()
            ->whereOwnerAndKey($owner, $key)
            ->lockForUpdate();
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param DatabaseBuilder $query
     *
     * @return SequenceQuery
     */
    public function newEloquentBuilder($query): SequenceQuery
    {
        return new SequenceQuery($query);
    }
}
