<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class RefreshRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class RefreshRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string', 'max:255'],
        ];
    }
}
