<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Class SwitchAccountRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class SwitchAccountRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->withoutTrashed(),
                Rule::notIn([$this->user()?->getAuthIdentifier()]),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.not_in' => 'You are already signed in to this account.',
        ];
    }

    /**
     * Get the account to switch to, when the user may switch to it: a main
     * account may switch to its linked accounts, and a super admin to any.
     *
     * @return User
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function account(): User
    {
        /** @var User $user */
        $user = $this->user();
        $account = User::query()->findOrFail($this->integer('user_id'));

        if (!$user->canImpersonateUser($account)) {
            throw new AuthorizationException('You may not switch to this account.');
        }

        if (!$account->canSignIn()) {
            throw ValidationException::withMessages([
                'user_id' => 'This account is suspended.',
            ]);
        }

        return $account;
    }
}
