<?php

namespace Core\Repositories\Interfaces;

use Closure;
use Core\Http\Resources\BaseResource;
use Illuminate\Database\Eloquent\Model;
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
     * Get an array of requested includes validated against the Resource,
     * nested includes against the resources of the relations they are
     * under. If an include is not allowed, then a validation exception
     * (422) will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws Throwable
     */
    public function getValidatedIncludes(Request $request): array;

    /**
     * Get eager loads of the given includes, which load only the related
     * models that the user may list (by the `index()` of their queries),
     * so that an include reaches no more than listing them would.
     *
     * @param array<int, string> $includes
     * @param Model|null $user
     *
     * @return array<string, Closure>
     */
    public function scopedEagerLoads(array $includes, ?Model $user): array;
}
