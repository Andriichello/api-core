<?php

namespace Core\Http\Resources\Traits;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Core\Helpers\Helpers;
use Core\Http\Resources\BaseResource;

/**
 * Trait AllowsIncludes.
 *
 * Contains logic for getting the list of relations,
 * which should be included for given request.
 * Throws exception when asked include is not allowed.
 *
 * By default, no includes will be allowed.
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
     * @var array<int|string, string>
     */
    protected ?array $passedIncludes = null;

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
        $includes = Arr::wrap($includes);
        $allowed = $this->listOfAllowedIncludes();

        foreach ($includes as $include) {
            if (!in_array($include, $allowed)) {
                $relation = Str::of($include)
                    ->before('.')
                    ->value();

                if (!in_array($relation, $allowed)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get include value. If it's not allowed then
     * an exception will be thrown.
     *
     * @param string $include
     * @param array $all (List of all includes, required for nested includes)
     *
     * @return mixed
     * @throws Exception
     */
    public function getAllowedInclude(string $include, array $all = []): mixed
    {
        if (!$this->hasAllowedIncludes($include)) {
            $message = "The '$include' include is not allowed on "
                . static::class;

            throw new Exception($message);
        }

        $relation = Str::of($include)
            ->before('.')
            ->value();

        $value = data_get($this, $relation);

        $nested = [];

        foreach ($all as $name) {
            if (str_starts_with($name, "$relation.")) {
                $nested[] = Str::of($name)
                    ->after("$relation.")
                    ->value();
            }
        }

        $resource = $this->resourceOfAllowedInclude($relation);

        if ($value && $resource) {
            /** @var BaseResource $resource */
            $resource = new $resource($value);
            $resource->setPassedIncludes($nested);
            $resource->setPassedAppends([]);

            return $resource;
        }

        return $value;
    }

    /**
     * Get an array of include keys to values. By default,
     * all allowed includes will be returned. If given includes
     * are not allowed then an exception will be thrown.
     *
     * @param string|array|null $includes
     *
     * @return array
     * @throws Exception
     */
    public function getAllowedIncludes(string|array $includes = null): array
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
     * are not allowed then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws Exception
     */
    public function getRequestedIncludes(Request $request): array
    {
        return $this->getAllowedIncludes(
            $this->passedIncludes ?? Helpers::requestedIncludes($request)
        );
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
