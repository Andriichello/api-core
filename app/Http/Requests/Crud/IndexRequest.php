<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;

/**
 * Class IndexRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class IndexRequest extends CrudRequest
{
    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'viewAny';
}
