<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;

/**
 * Class StoreRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class StoreRequest extends CrudRequest
{
    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'create';
}
