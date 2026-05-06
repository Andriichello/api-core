<?php

namespace Core\Repositories\Traits;

use Core\Helpers\Helpers;
use Core\Http\Resources\BaseResource;
use Core\Repositories\CrudRepository;
use Exception;
use Illuminate\Http\Request;

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
     * Get an array of requested includes validated against the Resource.
     *
     * @param Request $request
     *
     * @return array
     * @throws Exception
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
                throw new Exception("The '$include' include is not allowed on " . $resourceClass);
            }

            $validIncludes[] = $include;
        }

        return $validIncludes;
    }
}
