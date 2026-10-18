<?php

namespace Core\Helpers;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Providers\MorphServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Validation\ValidationException;

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
     * Returns the value of the requested parameter, which has to be a
     * comma-separated list. If it is not (such as `include[]=…`), then
     * a validation exception (422) will be thrown.
     *
     * @param string $key
     * @param Request|null $request
     *
     * @return string
     * @throws ValidationException
     */
    protected static function requestedList(
        string $key,
        ?Request $request = null
    ): string {
        $value = static::requested($key, '', $request);

        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            throw ValidationException::withMessages([
                $key => ["The $key parameter must be a comma-separated list."],
            ]);
        }

        return (string) $value;
    }

    /**
     * Returns an array of requested `append`.
     *
     * @param Request|null $request
     *
     * @return array<int, string>
     * @throws ValidationException
     */
    public static function requestedAppends(?Request $request = null): array
    {
        $string = static::requestedList('append', $request);
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
     * @throws ValidationException
     */
    public static function requestedSorts(?Request $request = null): array
    {
        $string = static::requestedList('sort', $request);
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
     * @return array<array{
     *      field: string,
     *      operation: FilterOperation,
     *      flags: FilterFlag[],
     *      value: mixed,
     *  }>
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
     * @throws ValidationException
     */
    public static function requestedIncludes(?Request $request = null): array
    {
        $string = static::requestedList('include', $request);

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
     * Returns the morph slug of the given resource: the alias of a class in
     * the morph map, or a slug made of the class name.
     *
     * @param string|object $resource
     *
     * @return string
     */
    public static function morphOf(string|object $resource): string
    {
        $resource = is_object($resource) ? get_class($resource) : $resource;

        if (str_contains($resource, '\\') || class_exists($resource)) {
            // Looked up as a whole, since a class name may contain dots (an anonymous one's has its file path)
            $alias = array_search($resource, Relation::$morphMap, true);

            return is_string($alias) ? $alias : MorphServiceProvider::slugWithFolderOf($resource);
        }

        return MorphServiceProvider::slugOf($resource);
    }
}
