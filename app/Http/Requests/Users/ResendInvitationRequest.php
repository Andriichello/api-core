<?php

namespace Core\Http\Requests\Users;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithTarget;

/**
 * Class ResendInvitationRequest.
 *
 * Emails the invitation to a user's account again, which needs the ability
 * to update the user.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Users
 */
class ResendInvitationRequest extends CrudRequest implements WithTargetInterface
{
    use WithTarget;

    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'update';
}
