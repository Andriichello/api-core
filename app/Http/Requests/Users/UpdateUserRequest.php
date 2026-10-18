<?php

namespace Core\Http\Requests\Users;

use Core\Http\Requests\Crud\UpdateRequest;
use Core\Http\Requests\Traits\WithAssignableRoles;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class UpdateUserRequest.
 *
 * Updates a user's name, timezone, locale and roles, which must be ones,
 * which the signed-in user may assign. The email and the password aren't
 * among them: a person's password is shared by all of their accounts, so
 * only the person sets it, and so is their email, which proves who they are.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Users
 */
class UpdateUserRequest extends UpdateRequest
{
    use WithAssignableRoles;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'string', 'max:5'],
            ...$this->rolesRules(),
        ];
    }
}
