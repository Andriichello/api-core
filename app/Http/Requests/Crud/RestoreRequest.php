<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithTarget;
use Core\Models\BaseModel;
use Core\Queries\BaseQuery;

/**
 * Class RestoreRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class RestoreRequest extends CrudRequest implements WithTargetInterface
{
    use WithTarget {
        targetQuery as protected scopedTargetQuery;
    }

    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'restore';

    /**
     * Get a query instance for finding the target model: only a soft-deleted
     * one, which can be restored.
     *
     * @param BaseModel|string $model
     *
     * @return BaseQuery
     */
    public function targetQuery(BaseModel|string $model): BaseQuery
    {
        return $this->scopedTargetQuery($model)->whereTrashed();
    }
}
