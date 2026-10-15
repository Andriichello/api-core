<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * Class ChangePasswordRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class ChangePasswordRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request. The current password
     * is the person's one (`User::getAuthPassword()`), and the new one follows
     * the application's `Password::defaults()` and differs from it, so a password,
     * which someone else set, isn't kept by setting it again.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::defaults(),
            ],
        ];
    }
}
