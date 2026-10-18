<?php

namespace Core\Http\Resources\Traits;

use Core\Helpers\Helpers;
use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Http\Resources\Interfaces\AllowsIncludesInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Trait AllowsIncludes.
 *
 * Contains logic for getting the list of relations which should be included
 * for a given request. Throws an exception when a requested include is not
 * allowed. Relations are only resolved when they have already been eager-loaded
 * by the repository — lazy loading is never triggered.
 *
 * A nested include (`organization.users`) is allowed only by the resource that
 * its relation is included as (or by the resource of the items of its collection),
 * so nothing can be nested under a relation, which is included as it is.
 *
 * By default, no includes will be allowed.
 *
 * @mixin BaseResource
 * @see AllowsIncludesInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources\Traits
 */
trait AllowsIncludes
{
    /**
     * List of columns that are allowed as
     * includes on request.
     *
     * @var array<int|string, string>
     */
    protected array $allowedIncludes = [];

    /**
     * List of includes that were passed from parent
     * resource. Needed for nested includes.
     *
     * @var array<int|string, string>|null
     */
    protected ?array $passedIncludes = null;

    /**
     * List of includes the request asks for, parsed once
     * by the collection of the resource for all its items.
     * Used instead of parsing the request again, when no
     * includes were passed.
     *
     * @var array<int, string>|null
     */
    protected ?array $includesOfRequest = null;

    /**
     * Get a list of includes that were passed from parent
     * resource. Needed for nested includes.
     *
     * @return array|null
     */
    public function getPassedIncludes(): ?array
    {
        return $this->passedIncludes;
    }

    /**
     * Set a list of includes that are passed from the parent
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
     * Get list of includes the request asks for, as the
     * collection of the resource parsed it, or null.
     *
     * @return array|null
     */
    public function getIncludesOfRequest(): ?array
    {
        return $this->includesOfRequest;
    }

    /**
     * Set list of includes the request asks for, parsed
     * once by the collection of the resource, so that
     * none of its items parses the request again.
     *
     * @param array|null $includes
     *
     * @return $this
     */
    public function setIncludesOfRequest(?array $includes): static
    {
        $this->includesOfRequest = $includes;

        return $this;
    }

    /**
     * Get list of columns that are allowed as
     * includes on request.
     *
     * @return array<int, string>
     */
    public function listOfAllowedIncludes(): array
    {
        $list = [];

        foreach ($this->allowedIncludes ?? [] as $key => $value) {
            $list[] = is_string($key) ? $key : $value;
        }

        return $list;
    }

    /**
     * Get resource for the given include.
     *
     * @param string $include
     *
     * @return string|null
     */
    public function resourceOfAllowedInclude(string $include): ?string
    {
        return data_get($this->allowedIncludes ?? [], $include);
    }

    /**
     * Determines if all given columns are allowed as
     * includes on request.
     *
     * @param string|array $includes
     *
     * @return bool
     */
    public function hasAllowedIncludes(string|array $includes): bool
    {
        $allowed = $this->listOfAllowedIncludes();

        foreach (Arr::wrap($includes) as $include) {
            $relation = Str::before($include, '.');

            if (!in_array($relation, $allowed, true)) {
                return false;
            }

            if ($relation === $include) {
                continue;
            }

            // A nested include is allowed only by the resource of the relation it is under
            $resource = $this->resourceForNestedIncludes($relation);

            if (!$resource?->hasAllowedIncludes(Str::after($include, '.'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get an empty instance of the resource, which allows the includes
     * nested under the given relation: the resource the relation is
     * included as, or the resource of the items of its collection.
     * Null when the relation is included as it is, so that nothing
     * can be nested under it.
     *
     * @param string $relation
     *
     * @return BaseResource|null
     */
    protected function resourceForNestedIncludes(string $relation): ?BaseResource
    {
        $class = $this->resourceOfAllowedInclude($relation);

        if (is_string($class) && is_a($class, BaseCollection::class, true)) {
            $class = new $class([])->resourceOfItems();
        }

        if (is_string($class) && is_a($class, BaseResource::class, true)) {
            return new $class(null);
        }

        return null;
    }

    /**
     * Get include value. If it's not allowed then
     * a validation exception (422) will be thrown.
     *
     * @param string $include
     * @param array $all (List of all includes, required for nested includes)
     *
     * @return mixed
     * @throws Throwable
     */
    public function getAllowedInclude(string $include, array $all = []): mixed
    {
        if (!$this->hasAllowedIncludes($include)) {
            throw $this->notAllowedInclude($include);
        }

        $relation = Str::of($include)
            ->before('.')
            ->value();

        $isLoaded = $this->resource instanceof Model &&
            $this->resource->relationLoaded($relation);

        if (!$isLoaded) {
            return new MissingValue;
        }

        $value = $this->resource->getRelation($relation);

        $nested = [];

        foreach ($all as $name) {
            if (str_starts_with($name, "$relation.")) {
                $nested[] = Str::of($name)
                    ->after("$relation.")
                    ->value();
            }
        }

        if (!$value) {
            return $value;
        }

        // A relation included as it is gives the attributes of its models, like any resource
        $resource = $this->resourceOfAllowedInclude($relation)
            ?? ($value instanceof Collection ? BaseCollection::class : BaseResource::class);

        /** @var BaseResource $resource */
        $resource = new $resource($value);
        $resource->setPassedIncludes($nested);
        $resource->setPassedAppends([]);

        return $resource;
    }

    /**
     * Get an array of include keys to values. By default,
     * all allowed includes will be returned. If given includes
     * are not allowed then an exception will be thrown.
     *
     * @param string|array|null $includes
     *
     * @return array
     * @throws ValidationException
     */
    public function getAllowedIncludes(string|array|null $includes = null): array
    {
        $includes = $includes !== null ? Arr::wrap($includes)
            : $this->listOfAllowedIncludes();

        $values = [];

        foreach ($includes as $include) {
            $relation = Str::of($include)
                ->before('.')
                ->value();

            $values[$relation] = $this->getAllowedInclude($include, $includes);
        }

        return $values;
    }

    /**
     * Get an array of requested includes. If requested includes
     * are not allowed, then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws Throwable
     */
    public function getRequestedIncludes(Request $request): array
    {
        return $this->getAllowedIncludes(
            $this->passedIncludes ?? $this->includesOfRequest ?? Helpers::requestedIncludes($request)
        );
    }

    /**
     * Get a validation exception (422) for the given include, which is
     * not allowed, naming the includes that are allowed where it stops
     * being allowed: a nested include is refused by the resource of the
     * relation it is under.
     *
     * @param string $include
     * @param string $prefix (Path of the relation the include is under, such as `organization.`)
     *
     * @return ValidationException
     */
    public function notAllowedInclude(string $include, string $prefix = ''): ValidationException
    {
        $relation = Str::before($include, '.');

        if ($relation !== $include && in_array($relation, $this->listOfAllowedIncludes(), true)) {
            $resource = $this->resourceForNestedIncludes($relation) ?? new BaseResource(null);

            return $resource->notAllowedInclude(Str::after($include, '.'), "$prefix$relation.");
        }

        $allowed = array_map(fn (string $name) => $prefix . $name, $this->listOfAllowedIncludes());

        $message = "The '$prefix$include' include is not allowed. " . match (true) {
            !empty($allowed) => 'Allowed includes: ' . implode(', ', $allowed) . '.',
            $prefix !== '' => "No includes are allowed under '" . rtrim($prefix, '.') . "'.",
            default => 'No includes are allowed.',
        };

        return ValidationException::withMessages([
            'include' => [$message],
        ]);
    }

    /**
     * Determines if given include is (probably) a nested one.
     *
     * @param string $include
     *
     * @return bool
     */
    public function isNestedInclude(string $include): bool
    {
        $parts = explode('.', $include);

        if (count($parts) < 2) {
            return false;
        }

        return Str::startsWith($include, $this->listOfAllowedIncludes());
    }
}
