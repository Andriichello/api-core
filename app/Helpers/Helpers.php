<?php

namespace Core\Helpers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use Core\Providers\MorphServiceProvider;

/**
 * Class Helpers.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Helpers
 */
class Helpers
{
    /**
     * Determines whether a class or an object uses the given trait(s).
     *
     * @param string|object $class
     * @param string ...$traits
     *
     * @return bool
     */
    public static function usesTrait(string|object $class, string ...$traits): bool
    {
        $uses = class_uses_recursive($class);

        foreach ($traits as $trait) {
            if (in_array($trait, $uses, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Casts a given object to an array (recursive).
     *
     * @param array|object $object
     *
     * @return array
     */
    public static function objectToArray(array|object $object): array
    {
        $array = (array) $object;

        foreach ($array as &$attribute) {
            if (is_object($attribute) || is_array($attribute)) {
                $attribute = Helpers::objectToArray($attribute);
            }
        }

        return $array;
    }

    /**
     * Returns the value of the requested parameter.
     *
     * @param string $key
     * @param mixed|null $default
     * @param Request|null $request
     *
     * @return mixed
     */
    protected static function requested(
        string $key,
        mixed $default = null,
        ?Request $request = null
    ): mixed {
        return $request ? $request->input($key, $default) : RequestFacade::input($key, $default);
    }

    /**
     * Returns an array of requested `append`.
     *
     * @param Request|null $request
     *
     * @return array<int, string>
     */
    public static function requestedAppends(?Request $request = null): array
    {
        $string = static::requested('append', '', $request);
        $appends = array_filter(explode(',', $string));

        return array_values(array_unique($appends));
    }

    /**
     * Returns an array of requested `sort`. Sort directions
     * are the values and the keys are the sort columns.
     *
     * @param Request|null $request
     *
     * @return array<string, string>
     */
    public static function requestedSorts(?Request $request = null): array
    {
        $string = static::requested('sort', '', $request);
        $values = array_filter(explode(',', $string));

        $sorts = [];

        foreach ($values as $value) {
            $column = ltrim($value, '-');
            $direction = str_starts_with($value, '-') ? 'desc' : 'asc';

            $sorts[$column] = $direction;
        }

        return $sorts;
    }

    /**
     * Returns an array of requested `filter`.
     *
     * @param Request|null $request
     *
     * @return array
     */
    public static function requestedFilters(?Request $request = null): array
    {
        return FilterHelper::parse((array) static::requested('filter', [], $request));
    }

    /**
     * Returns an array of requested `include`.
     *
     * @param Request|null $request
     *
     * @return array
     */
    public static function requestedIncludes(?Request $request = null): array
    {
        $string = static::requested('include', '', $request);

        $includes = [];

        foreach (array_filter(explode(',', $string)) as $include) {
            if (!str_contains($include, '.')) {
                $includes[] = $include;
                continue;
            }

            $accumulator = '';
            foreach (explode('.', $include) as $part) {
                $accumulator .= (empty($accumulator) ? '' : '.') . $part;
                $includes[] = $accumulator;
            }
        }

        return array_unique($includes);
    }

    /**
     * Returns the given array of items if they are scalar
     * or items' attribute value if they are objects or arrays.
     *
     * @param mixed ...$items
     * @param string|int $key
     *
     * @return array
     */
    public static function extract(string|int $key, mixed ...$items): array
    {
        $closure = function (mixed $item) use ($key) {
            if (is_array($item) || is_object($item)) {
                return data_get($item, $key);
            }

            return $item;
        };

        return array_map($closure, $items);
    }

    /**
     * Returns the morph slug of the given resource.
     *
     * @param string|object $resource
     *
     * @return string
     */
    public static function morphOf(string|object $resource): string
    {
        $resource = is_object($resource) ? get_class($resource) : $resource;

        if (str_contains($resource, '\\') || class_exists($resource)) {
            return data_get(
                array_flip(Relation::$morphMap),
                $resource,
                MorphServiceProvider::slugWithFolderOf($resource)
            );
        }

        return MorphServiceProvider::slugOf($resource);
    }
}
