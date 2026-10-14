<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Traits\WithObjectBody;
use Illuminate\Validation\ValidationException;

/**
 * Class StoreRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class StoreRequest extends CrudRequest
{
    use WithObjectBody;

    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'create';

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     * @throws ValidationException
     */
    protected function passedValidation(): void
    {
        $this->ensureObjectBody();
    }
}
