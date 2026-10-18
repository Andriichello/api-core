<?php

namespace Core\Http\Requests\Traits;

use Core\Http\Requests\BaseRequest;
use Core\Models\User;
use Illuminate\Validation\Rule;

/**
 * Trait WithAssignableRoles.
 *
 * For requests, which give a user `roles`: only the roles, which the
 * signed-in user may assign (`User::availableRoles()`), are valid: their own
 * roles and the lower ones.
 *
 * @mixin BaseRequest
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Traits
 */
trait WithAssignableRoles
{
    /**
     * Get the validation rules of the roles.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rolesRules(): array
    {
        $user = $this->user();

        return [
            'roles' => ['sometimes', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::in($user instanceof User ? $user->availableRoles() : []),
            ],
        ];
    }
}
