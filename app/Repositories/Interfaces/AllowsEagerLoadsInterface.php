<?php

namespace Core\Repositories\Interfaces;

use Core\Http\Resources\BaseResource;
use Illuminate\Http\Request;
use Throwable;

/**
 * Interface AllowsEagerLoadsInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories\Interfaces
 */
interface AllowsEagerLoadsInterface
{
    /**
     * Get the resource class associated with this repository.
     *
     * @return class-string<BaseResource>
     */
    public function resourceClass(): string;

    /**
     * Get an array of requested includes validated against the Resource.
     *
     * @param Request $request
     *
     * @return array
     * @throws Throwable
     */
    public function getValidatedIncludes(Request $request): array;
}
