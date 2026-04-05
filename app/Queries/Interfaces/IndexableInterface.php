<?php

namespace Core\Queries\Interfaces;

use Core\Queries\BaseQuery;
use Illuminate\Http\Request;

/**
 * Interface IndexableInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries\Interfaces
 */
interface IndexableInterface
{
    /**
     * Apply index query conditions.
     *
     * @param Request $request
     *
     * @return BaseQuery
     */
    public function index(Request $request): BaseQuery;
}
