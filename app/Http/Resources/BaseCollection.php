<?php

namespace Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Class BaseCollection.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class BaseCollection extends ResourceCollection
{
    /**
     * List of columns that were passed from parent
     * resource. Needed for nested appends.
     *
     * @var array<int, string>
     */
    protected ?array $passedAppends = null;

    /**
     * List of includes that were passed from parent
     * resource. Needed for nested includes.
     *
     * @var array<int|string, string>
     */
    protected ?array $passedIncludes = null;

    /**
     * Get the list of columns that were passed from parent
     * resource. Needed for nested appends.
     *
     * @return array|null
     */
    public function getPassedAppends(): ?array
    {
        return $this->passedAppends;
    }

    /**
     * Set the list of columns that were passed from parent
     * resource. Needed for nested appends.
     *
     * @param array|null $appends
     *
     * @return $this
     */
    public function setPassedAppends(?array $appends): static
    {
        $this->passedAppends = $appends;

        return $this;
    }

    /**
     * Get list of includes that were passed from parent
     * resource. Needed for nested includes.
     *
     * @return array|null
     */
    public function getPassedIncludes(): ?array
    {
        return $this->passedIncludes;
    }

    /**
     * Set list of includes that are passed from parent
     * resource. Needed for nested includes.
     *
     * @param array|null $includes
     *
     * @return $this
     */
    public function setPassedIncludes(?array $includes): static
    {
        $this->passedIncludes = $includes;

        return $this;
    }

    /**
     * Transform collection into an array.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray(Request $request): array
    {
        $collection = $this->collection->map(
            function (BaseResource $resource) use ($request) {
                $appends = $this->getPassedAppends();
                $includes = $this->getPassedIncludes();

                $resource->setPassedAppends($appends);
                $resource->setPassedIncludes($includes);

                return $resource->toArray($request);
            }
        );

        return $collection->all();
    }
}
