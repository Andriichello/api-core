<?php

namespace Core\Http\Requests\Files;

use Core\Http\Requests\Crud\UpdateRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class UpdateFileRequest.
 *
 * Renames a file, which changes only the name, under which it is downloaded.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Files
 */
class UpdateFileRequest extends UpdateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'name' => ['sometimes', 'string', 'max:255'],
            ]
        );
    }
}
