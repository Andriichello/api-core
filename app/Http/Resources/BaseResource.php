<?php

namespace Core\Http\Resources;

use Core\Helpers\Helpers;
use Core\Http\Resources\Interfaces\AllowsAppendsInterface;
use Core\Http\Resources\Interfaces\AllowsIncludesInterface;
use Core\Http\Resources\Traits\AllowsAppends;
use Core\Http\Resources\Traits\AllowsIncludes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Class BaseResource.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class BaseResource extends JsonResource implements
    AllowsAppendsInterface,
    AllowsIncludesInterface
{
    use AllowsAppends;
    use AllowsIncludes;

    /**
     * List of columns that are allowed as
     * appends on request.
     *
     * @var array<int|string, string>
     */
    protected array $allowedAppends = [];

    /**
     * List of columns that are allowed as
     * includes on request.
     *
     * @var array<int|string, string>
     */
    protected array $allowedIncludes = [];

    /**
     * Transform the resource into an array.
     *
     * Merges resolved include and append in the base attribute array,
     * omitting any entry whose value is a MissingValue (i.e., the repository
     * did not eager-load the relationship). A model gives its attributes
     * only, so relations it loaded for itself (such as `roles` by `hasRole()`)
     * are left out, unless they are requested includes.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource instanceof Model
            ? $this->resource->attributesToArray()
            : parent::toArray($request);

        foreach ($this->getRequested($request) as $key => $value) {
            if ($value instanceof MissingValue) {
                unset($data[$key]);

                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Get slug of the current resource. The same slug
     * is used for naming the routes.
     *
     * @return string
     */
    public static function singularSlug(): string
    {
        return Str::of(static::class)
            ->afterLast('\\')
            ->replaceLast('Resource', '')
            ->kebab()
            ->value();
    }

    /**
     * Get slug of the current resource. The same slug
     * is used for naming the routes.
     *
     * @return string
     */
    public static function pluralSlug(): string
    {
        return Str::of(static::singularSlug())
            ->plural()
            ->value();
    }

    /**
     * Determines if the current route has the slug in the name.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function isTarget(Request $request): bool
    {
        $route = $request->route();

        if ($route instanceof Route) {
            $name = $route->getName();
        }

        return str_contains($name ?? '', static::singularSlug())
            || str_contains($name ?? '', static::pluralSlug());
    }

    /**
     * Make sure the given includes and appends are allowed, as rendering the
     * resource would, but without its model, so that a request can be
     * refused before it changes anything. An append under a relation is
     * checked by the resource the relation is included as, when it is
     * included, and left out otherwise, as rendering leaves it out.
     *
     * @param array<int, string> $includes
     * @param array<int, string> $appends
     *
     * @return void
     * @throws ValidationException
     */
    public function validateRequested(array $includes, array $appends): void
    {
        foreach ($includes as $include) {
            if (!$this->hasAllowedIncludes($include)) {
                throw $this->notAllowedInclude($include);
            }
        }

        foreach ($appends as $append) {
            if (!str_contains($append, '.')) {
                if (!$this->hasAllowedAppends($append)) {
                    throw $this->notAllowedAppend($append);
                }

                continue;
            }

            $relation = Str::before($append, '.');
            $nested = array_values(array_map(
                fn (string $include) => Str::after($include, "$relation."),
                array_filter($includes, fn (string $include) => str_starts_with($include, "$relation."))
            ));

            if (!empty($nested) || in_array($relation, $includes, true)) {
                // A relation included as it is is wrapped in a resource, which allows no appends
                ($this->resourceForNestedIncludes($relation) ?? new BaseResource(null))
                    ->validateRequested($nested, [Str::after($append, '.')]);
            }
        }
    }

    /**
     * Get an array of requested includes and appends.
     * If requested includes or appends are not allowed,
     * then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws Throwable
     */
    public function getRequested(Request $request): array
    {
        $requested = $this->getRequestedIncludes($request);

        $appends = $this->getPassedAppends()
            ?? $this->getAppendsOfRequest()
            ?? Helpers::requestedAppends($request);

        foreach ($appends as $append) {
            if (str_contains($append, '.')) {
                $relation = Str::of($append)
                    ->before('.')
                    ->value();

                $include = data_get($requested, $relation);
                if ($include instanceof BaseResource || $include instanceof BaseCollection) {
                    $passed = $include->getPassedAppends() ?? [];

                    $passed[] = Str::of($append)
                        ->after("$relation.")
                        ->value();

                    $include->setPassedAppends($passed);
                }

                continue;
            }

            $requested[$append] = $this->getAllowedAppend($append);
        }

        return $requested;
    }
}
