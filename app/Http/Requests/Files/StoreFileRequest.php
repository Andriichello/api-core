<?php

namespace Core\Http\Requests\Files;

use Core\Http\Requests\Crud\StoreRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\File as FileRule;

/**
 * Class StoreFileRequest.
 *
 * Uploads a file, which is attached under its own name, unless `name` is
 * given.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Files
 */
class StoreFileRequest extends StoreRequest
{
    /**
     * Get the validation rules that apply to the request. The file's size and
     * type are limited by `api-core.files.max_size` and `extensions`: both its
     * extension and the one, which its contents are recognized as, must be
     * allowed.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $extensions = config('api-core.files.extensions');

        return array_merge(
            parent::rules(),
            [
                'file' => [
                    'required',
                    FileRule::types($extensions)
                        ->extensions($extensions)
                        ->max(config('api-core.files.max_size')),
                ],
                'name' => ['sometimes', 'string', 'max:255'],
            ]
        );
    }
}
