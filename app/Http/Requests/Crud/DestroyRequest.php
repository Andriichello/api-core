<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithTarget;

/**
 * Class DestroyRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class DestroyRequest extends CrudRequest implements WithTargetInterface
{
    use WithTarget;

    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'delete';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'force' => [
                    'boolean',
                ],
            ]
        );
    }

    /**
     * Get ability, which should be checked on the request. Deleting
     * permanently is an ability of its own: `forceDelete`.
     *
     * @return string|null
     */
    public function getAbility(): ?string
    {
        return $this->force() ? 'forceDelete' : $this->ability;
    }

    /**
     * Get force parameter.
     *
     * @return bool
     */
    public function force(): bool
    {
        return $this->boolean('force');
    }
}
