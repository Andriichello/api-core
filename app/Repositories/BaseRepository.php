<?php

namespace Core\Repositories;

use Illuminate\Database\Eloquent\SoftDeletes;
use Core\Helpers\Helpers;
use Core\Models\BaseModel;
use Core\Queries\BaseQuery;
use Core\Repositories\Interfaces\BaseRepositoryInterface;

/**
 * Class BaseRepository.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * Repository's model class.
     *
     * @var BaseModel|string
     */
    protected BaseModel|string $model = BaseModel::class;

    /**
     * Get the repository's model class.
     *
     * @return BaseModel|string
     */
    public function model(): BaseModel|string
    {
        return $this->model;
    }

    /**
     * Get a new instance of the repository's model class.
     *
     * @return BaseModel|null
     */
    public function instance(): ?BaseModel
    {
        $class = is_string($this->model)
            ? $this->model : get_class($this->model);

        return empty($class) ? null : new $class();
    }

    /**
     * Get the query builder for the repository's model.
     *
     * @return BaseQuery
     */
    public function builder(): BaseQuery
    {
        return ($this->model)::query();
    }

    /**
     * Determine if the repository's model is soft-deletable.
     *
     * @return bool
     */
    public function isSoftDeletable(): bool
    {
        return Helpers::usesTrait($this->model, SoftDeletes::class);
    }
}
