<?php

namespace Core\Repositories\Interfaces;

use Core\Models\BaseModel;
use Core\Queries\BaseQuery;

/**
 * Interface BaseRepositoryInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories\Interfaces
 */
interface BaseRepositoryInterface
{
    /**
     * Get a repository's model class.
     *
     * @return BaseModel|string
     */
    public function model(): BaseModel|string;

    /**
     * Get a new instance of the repository's model class.
     *
     * @return BaseModel|null
     */
    public function instance(): ?BaseModel;

    /**
     * Get a query builder for the repository's model.
     *
     * @return BaseQuery
     */
    public function builder(): BaseQuery;

    /**
     * Determine if the repository's model is soft-deletable.
     *
     * @return bool
     */
    public function isSoftDeletable(): bool;
}
