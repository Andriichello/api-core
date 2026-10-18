<?php

namespace Core\Http\Requests\Users;

use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\Traits\WithAssignableRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Class InviteUserRequest.
 *
 * Invites a person to a new account in the organization, in which the
 * request acts. The email must be new to the organization, and the roles
 * must be ones, which the signed-in user may assign. It takes no password,
 * as the person chooses it, when they accept the invitation.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Users
 */
class InviteUserRequest extends StoreRequest
{
    use WithAssignableRoles;

    /**
     * Get form request fields' override values: the email in lowercase, so it
     * is compared, as it is stored.
     *
     * @return array<string, mixed>
     */
    protected function overrides(): array
    {
        $email = $this->input('email');

        return is_string($email) ? ['email' => Str::lower($email)] : [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $orgId = $this->orgId();

        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // The email of a deleted user is fine, as inviting it restores the user
                Rule::unique('users', 'email')
                    ->withoutTrashed()
                    ->where(fn ($query) => $orgId === null
                        ? $query->whereNull('organization_id')
                        : $query->where('organization_id', $orgId)),
            ],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'string', 'max:5'],
            ...$this->rolesRules(),
        ];
    }
}
