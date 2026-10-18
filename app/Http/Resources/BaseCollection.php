<?php

namespace Core\Http\Resources;

use Core\Helpers\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Class BaseCollection.
 *
 * A collection of base resources. Its subclasses collect the resource named in
 * their `$collects` (or the one named like them), since the attribute isn't
 * inherited.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
#[Collects(BaseResource::class)]
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
     * Get the resource class of the items of the collection.
     *
     * @return class-string<JsonResource>|null
     */
    public function resourceOfItems(): ?string
    {
        return $this->collects();
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
        $appends = $this->getPassedAppends();
        $includes = $this->getPassedIncludes();

        // Where nothing was passed, the items use what the request asks for, parsed
        // here once instead of by each of them, since reading the request is slow
        $appendsOfRequest = $appends === null ? Helpers::requestedAppends($request) : null;
        $includesOfRequest = $includes === null ? Helpers::requestedIncludes($request) : null;

        return $this->collection
            ->map(fn (BaseResource $resource) => $resource
                ->setPassedAppends($appends)
                ->setPassedIncludes($includes)
                ->setAppendsOfRequest($appendsOfRequest)
                ->setIncludesOfRequest($includesOfRequest)
                ->toArray($request))
            ->all();
    }
}
