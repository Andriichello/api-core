<?php

namespace Core\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Class RecordLockedException.
 *
 * Thrown, when a locked record (see Lockable), or a record of a locked one
 * (see LockedByParent), is about to change. It answers with 409 and isn't
 * reported.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Exceptions
 */
class RecordLockedException extends ConflictHttpException
{
    /**
     * RecordLockedException constructor.
     *
     * @param Model $record The locked record.
     * @param string $message
     * @param array<int, string> $attributes The attributes, which can't change.
     */
    public function __construct(
        public readonly Model $record,
        string $message,
        public readonly array $attributes = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Make an exception of changing the given attributes of a locked record.
     *
     * @param Model $record
     * @param array<int, string> $attributes
     *
     * @return self
     */
    public static function changing(Model $record, array $attributes): self
    {
        return new self(
            $record,
            'The record is locked, so its ' . implode(', ', $attributes) . " can't be changed.",
            $attributes
        );
    }

    /**
     * Make an exception of deleting a locked record.
     *
     * @param Model $record
     *
     * @return self
     */
    public static function deleting(Model $record): self
    {
        return new self($record, "The record is locked, so it can't be deleted.");
    }

    /**
     * Make an exception of changing a record, which belongs to a locked one.
     *
     * @param Model $record The locked record.
     *
     * @return self
     */
    public static function changingChildOf(Model $record): self
    {
        return new self($record, "The record, to which it belongs, is locked, so it can't be changed.");
    }
}
