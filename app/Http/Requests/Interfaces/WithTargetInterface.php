<?php

namespace Core\Http\Requests\Interfaces;

use Core\Models\BaseModel;
use Core\Queries\BaseQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Interface WithTargetInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Interfaces
 */
interface WithTargetInterface
{
    /**
     * Get target id.
     *
     * @return mixed
     */
    public function getId(): mixed;

    /**
     * Set target id.
     *
     * @param mixed $value
     *
     * @return static
     */
    public function setId(mixed $value): static;

    /**
     * Get a query instance for finding the target model.
     *
     * @param BaseModel|string $model
     *
     * @return BaseQuery
     */
    public function targetQuery(BaseModel|string $model): BaseQuery;

    /**
     * Get a target model.
     *
     * @param BaseModel|string $model
     *
     * @return BaseModel|null
     */
    public function target(BaseModel|string $model): ?BaseModel;

    /**
     * Get a target model or fail.
     *
     * @param BaseModel|string $model
     *
     * @return BaseModel
     * @throws ModelNotFoundException
     */
    public function targetOrFail(BaseModel|string $model): BaseModel;
}
