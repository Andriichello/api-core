<?php

namespace Core\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Class StatusTransitionException.
 *
 * Thrown, when a record's status is about to change to one, which its
 * current status doesn't lead to (see TransitionsStatus). It answers with
 * 409 and isn't reported.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Exceptions
 */
class StatusTransitionException extends ConflictHttpException
{
    /**
     * StatusTransitionException constructor.
     *
     * @param Model $record
     * @param string|null $from The current status.
     * @param string|null $to The status, which it can't change to.
     */
    public function __construct(
        public readonly Model $record,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {
        parent::__construct(sprintf(
            "The status can't change from %s to %s.",
            $from ?? 'none',
            $to ?? 'none'
        ));
    }
}
