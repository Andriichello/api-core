<?php

namespace Core\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Core\Models\BaseModel;

/**
 * Interface CrudRepositoryInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories\Interfaces
 */
interface CrudRepositoryInterface
{
    /**
     * Find the model by the id.
     *
     * @param mixed $id
     *
     * @return BaseModel|null
     */
    public function find(mixed $id): ?BaseModel;

    /**
     * Find the model by the id or throw an exception.
     *
     * @param mixed $id
     *
     * @return BaseModel|null
     */
    public function findOrFail(mixed $id): ?BaseModel;

    /**
     * Create model with given attributes.
     *
     * @param array $attributes
     *
     * @return BaseModel
     */
    public function create(array $attributes): BaseModel;

    /**
     * Update the model with given attributes.
     *
     * @param BaseModel $model
     * @param array $attributes
     *
     * @return bool
     */
    public function update(BaseModel $model, array $attributes): bool;

    /**
     * Update or create the model with given attributes.
     *
     * @param array $attributes
     *
     * @return Builder|BaseModel
     */
    public function updateOrCreate(array $attributes): Builder|BaseModel;

    /**
     * Delete the given model.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function delete(BaseModel $model): bool;

    /**
     * Force delete the given model.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function forceDelete(BaseModel $model): bool;
}
