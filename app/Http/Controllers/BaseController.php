<?php

namespace Core\Http\Controllers;

use Core\Http\Controllers\Interfaces\WithPaginationInterface;
use Core\Http\Controllers\Traits\WithPagination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Class BaseController.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers
 */
abstract class BaseController extends Controller implements WithPaginationInterface
{
    use AuthorizesRequests;
    use ValidatesRequests;
    use WithPagination;
}
