<?php

namespace Core\Repositories\Interfaces;

use Core\Models\BaseModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     * @return BaseModel
     * @throws ModelNotFoundException
     */
    public function findOrFail(mixed $id): BaseModel;

    /**
     * Create the model with given attributes, which belongs to the parents:
     * the models, which its BelongsTo relations, named by the keys, point to.
     *
     * @param array $attributes
     * @param array<string, BaseModel> $parents
     *
     * @return BaseModel
     */
    public function create(array $attributes, array $parents = []): BaseModel;

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
     * Update the model, which matches the given attributes, with the values,
     * or create it with both.
     *
     * @param array $attributes
     * @param array $values
     *
     * @return BaseModel
     */
    public function updateOrCreate(array $attributes, array $values = []): BaseModel;

    /**
     * Delete the given model.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function delete(BaseModel $model): bool;

    /**
     * Restore the given soft-deleted model.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function restore(BaseModel $model): bool;

    /**
     * Force delete the given model.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function forceDelete(BaseModel $model): bool;
}
