<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Class ResetPasswordRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class ResetPasswordRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request. The new password
     * follows the application's `Password::defaults()`.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Get the credentials, with which the password broker checks the token
     * and resets the password. The email is lowercase, as it is stored.
     *
     * @return array{token: string, email: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'token' => $this->string('token')->value(),
            'email' => Str::lower($this->string('email')->value()),
            'password' => $this->string('password')->value(),
        ];
    }
}
