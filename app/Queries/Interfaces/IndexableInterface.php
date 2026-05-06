<?php

namespace Core\Queries\Interfaces;

use Core\Queries\BaseQuery;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface IndexableInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries\Interfaces
 */
interface IndexableInterface
{
    /**
     * Apply index query conditions for a given user.
     *
     * @param Model|null $user
     * @param array $params
     *
     * @return BaseQuery
     */
    public function index(?Model $user, array $params = []): BaseQuery;
}
