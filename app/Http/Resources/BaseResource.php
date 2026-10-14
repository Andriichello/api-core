<?php

namespace Core\Http\Resources;

use Core\Helpers\Helpers;
use Core\Http\Resources\Interfaces\AllowsAppendsInterface;
use Core\Http\Resources\Interfaces\AllowsIncludesInterface;
use Core\Http\Resources\Traits\AllowsAppends;
use Core\Http\Resources\Traits\AllowsIncludes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
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
     * did not eager-load the relationship).
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

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
