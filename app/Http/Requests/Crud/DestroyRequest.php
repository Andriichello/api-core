<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithTarget;
use Core\Models\BaseModel;
use Core\Queries\BaseQuery;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Class DestroyRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class DestroyRequest extends CrudRequest implements WithTargetInterface
{
    use WithTarget {
        targetQuery as protected scopedTargetQuery;
    }

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
     * Get a query instance for finding the target model. When deleting
     * permanently, a soft-deleted model is found too, so it can be removed.
     *
     * @param BaseModel|string $model
     *
     * @return BaseQuery
     */
    public function targetQuery(BaseModel|string $model): BaseQuery
    {
        $query = $this->scopedTargetQuery($model);

        return $this->force()
            ? $query->withoutGlobalScope(SoftDeletingScope::class)
            : $query;
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
