<?php

namespace Core\Repositories\Traits;

use Closure;
use Core\Helpers\Helpers;
use Core\Http\Resources\BaseResource;
use Core\Queries\Interfaces\IndexableInterface;
use Core\Repositories\CrudRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Trait AllowsEagerLoads.
 *
 * @mixin CrudRepository
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories\Traits
 */
trait AllowsEagerLoads
{
    /**
     * The resource class used to validate allowed includes it.
     * Overridden in the specific repository (e.g., UserRepository).
     *
     * @var class-string<BaseResource>
     */
    protected string $resourceClass = BaseResource::class;

    /**
     * Get the resource class associated with this repository.
     *
     * @return class-string<BaseResource>
     */
    public function resourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * Get an array of requested includes validated against the Resource,
     * nested includes against the resources of the relations they are
     * under. If an include is not allowed, then a validation exception
     * (422) will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws ValidationException
     */
    public function getValidatedIncludes(Request $request): array
    {
        $resourceClass = $this->resourceClass();

        $isNotResource = empty($resourceClass) ||
            !is_subclass_of($resourceClass, BaseResource::class);

        if ($isNotResource) {
            return [];
        }

        $requestedIncludes = Helpers::requestedIncludes($request);

        if (empty($requestedIncludes)) {
            return [];
        }

        // Instantiate a dummy resource (passing null) to access its rules
        /** @var BaseResource $resourceInstance */
        $resourceInstance = new $resourceClass(null);

        $validIncludes = [];

        foreach ($requestedIncludes as $include) {
            // Leverage the exact validation logic already built in AllowsIncludes
            if (!$resourceInstance->hasAllowedIncludes($include)) {
                throw $resourceInstance->notAllowedInclude($include);
            }

            $validIncludes[] = $include;
        }

        return $validIncludes;
    }

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
    public function scopedEagerLoads(array $includes, ?Model $user): array
    {
        $scope = function (Builder $query) use ($user) {
            if ($query instanceof IndexableInterface) {
                $query->index($user);
            }
        };

        $constrain = function (Relation $relation) use ($scope) {
            // A morphTo loads each type of the related models with a query of its own
            if ($relation instanceof MorphTo) {
                $callbacks = [];

                foreach (array_keys($relation->getDictionary()) as $type) {
                    $callbacks[Model::getActualClassNameForMorph($type)] = $scope;
                }

                $relation->constrain($callbacks);

                return;
            }

            $scope($relation->getQuery());
        };

        return array_fill_keys($includes, $constrain);
    }
}
