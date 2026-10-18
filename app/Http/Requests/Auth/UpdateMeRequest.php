<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\Crud\UpdateRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class UpdateMeRequest.
 *
 * Updates the signed-in user's own name, timezone and locale. The email and
 * the password aren't among them, as they change through their own routes
 * (`change-password`). As with any update, a body without any of these
 * fields is refused.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class UpdateMeRequest extends UpdateRequest
{
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
        ];
    }
}
