<?php

namespace Core\Http\Requests\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Core\Http\Requests\BaseRequest;
use Core\Models\BaseModel;
use Core\Queries\BaseQuery;

/**
 * Trait WithTarget.
 *
 * @mixin BaseRequest
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Traits
 */
trait WithTarget
{
    /**
     * Target id.
     *
     * @var mixed
     */
    protected mixed $id;

    /**
     * Last loaded target model.
     *
     * @var BaseModel|null
     */
    protected ?BaseModel $target;

    /**
     * Get target id.
     *
     * @return mixed
     */
    public function getId(): mixed
    {
        if (!isset($this->id)) {
            $this->id = $this->route('id');
        }

        if (!isset($this->id)) {
            $this->id = $this->input('id');
        }

        return $this->id ?? null;
    }

    /**
     * Set target id.
     *
     * @param mixed $value
     *
     * @return static
     */
    public function setId(mixed $value): static
    {
        $this->id = $value;

        return $this;
    }

    /**
     * Get a query instance for finding the target model.
     *
     * @param BaseModel|string $model
     *
     * @return BaseQuery
     */
    public function targetQuery(BaseModel|string $model): BaseQuery
    {
        return $model::query()
            ->whereKey($this->getId())
            ->index($this);
    }

    /**
     * Get a target model.
     *
     * @param BaseModel|string $model
     *
     * @return BaseModel|null
     */
    public function target(BaseModel|string $model): ?BaseModel
    {
        if (isset($this->target) && is_a($this->target, $model)) {
            return $this->target;
        }

        return $this->target = $this->targetQuery($model)->first();
    }

    /**
     * Get a target model or fail.
     *
     * @param BaseModel|string $model
     *
     * @return BaseModel
     * @throws ModelNotFoundException
     */
    public function targetOrFail(BaseModel|string $model): BaseModel
    {
        if (isset($this->target) && is_a($this->target, $model)) {
            return $this->target;
        }

        return $this->target = $this->targetQuery($model)->firstOrFail();
    }
}
