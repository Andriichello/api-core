<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Class ForgotPasswordRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class ForgotPasswordRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Get the credentials, by which the password broker finds the user.
     * The email is lowercase, as it is stored.
     *
     * @return array{email: string}
     */
    public function credentials(): array
    {
        return [
            'email' => Str::lower($this->string('email')->value()),
        ];
    }
}
