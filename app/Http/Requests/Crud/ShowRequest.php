<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithTarget;

/**
 * Class ShowRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class ShowRequest extends CrudRequest implements WithTargetInterface
{
    use WithTarget;

    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'view';
}
