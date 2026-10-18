<?php

namespace Core\Http\Requests\Settings;

use Core\Http\Requests\Crud\UpdateRequest;
use Illuminate\Validation\ValidationException;

/**
 * Class UpdateSettingsRequest.
 *
 * Updates settings of a model, whose values its body nests by their keys.
 * They are validated by the model's definitions of its settings (see
 * HasSettings), rather than by the request's rules.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Settings
 */
class UpdateSettingsRequest extends UpdateRequest
{
    /**
     * Handle a passed validation attempt: the body must be an object, but
     * its fields are the model's to check.
     *
     * @return void
     * @throws ValidationException
     */
    protected function passedValidation(): void
    {
        $this->ensureObjectBody();
    }
}
