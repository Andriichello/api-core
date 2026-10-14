<?php

namespace Core\Helpers;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use InvalidArgumentException;
use Throwable;

/**
 * Class FilterHelper.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Helpers
 */
class FilterHelper
{
    /**
     * Parses the filters array into a structured format.
     *
     * @param array $filters
     *
     * @return array<array{
     *     field: string,
     *     operation: FilterOperation,
     *     flags: FilterFlag[],
     *     value: mixed,
     * }>
     * @throws InvalidArgumentException
     */
    public static function parse(array $filters): array
    {
        $result = [];

        foreach ($filters as $field => $nodes) {
            $parsed = static::parseNode($field, $nodes);

            // Flatten the result if parseNode returned nested arrays
            array_push($result, ...static::flatten($parsed));
        }

        return $result;
    }

    /**
     * Parses the filter node into a structured format.
     * Always returns an array of filter entries.
     *
     * @param string $field
     * @param mixed $node
     * @param string[] $path
     *
     * @return array<array{
     *     field: string,
     *     operation: FilterOperation,
     *     flags: FilterFlag[],
     *     value: mixed,
     * }>
     * @throws InvalidArgumentException
     */
    public static function parseNode(string $field, mixed $node, array $path = []): array
    {
        $isScalar = is_scalar($node);
        $isNumericArray = is_array($node) &&
            array_keys($node) === range(0, count($node) - 1) &&
            array_all($node, fn ($item) => is_scalar($item));

        // Leaf → operation reached
        if ($isScalar || $isNumericArray) {
            $operation = static::parseOperation($path);
            $flags = static::parseFlags($path);

            // Don't allow array of values for non-IN operations
            if ($isNumericArray && $operation !== FilterOperation::IN) {
                throw new InvalidArgumentException('Array of values is only supported by IN operation');
            }

            return [
                [
                    'field' => $field,
                    'operation' => $operation,
                    'flags' => $flags,
                    'value' => $node,
                ],
            ];
        }

        $result = [];

        foreach ($node as $key => $child) {
            $parsed = is_int($key)
                ? static::parseNode($field, $child, $path)
                : static::parseNode($field, $child, [...$path, $key]);

            // parseNode always returns an array of entries
            array_push($result, ...$parsed);
        }

        return $result;
    }

    /**
     * Parses operation from the given path.
     *
     * @param array $path
     * @param FilterOperation $default
     *
     * @return FilterOperation
     * @throws InvalidArgumentException
     */
    protected static function parseOperation(
        array &$path,
        FilterOperation $default = FilterOperation::EQ
    ): FilterOperation {
        $operation = array_last($path);

        if ($operation === null) {
            return $default;
        }

        try {
            $parsed = FilterOperation::tryFrom($operation);

            if ($parsed) {
                // Remove operation from the path
                array_pop($path);
            }

            return $parsed ?? $default;
        } catch (Throwable) {
            throw new InvalidArgumentException('Invalid filter operation provided: ' . $operation);
        }
    }

    /**
     * Parses flags from the given path.
     *
     * @param array $path
     *
     * @return array
     * @throws InvalidArgumentException
     */
    protected static function parseFlags(array &$path): array
    {
        if (empty($path)) {
            return [];
        }

        try {
            return array_map(
                function ($flag) {
                    try {
                        return $flag instanceof FilterFlag
                            ? $flag : FilterFlag::from($flag);
                    } catch (Throwable) {
                        throw new InvalidArgumentException('Invalid filter flag provided: ' . $flag);
                    }
                },
                $path
            );
        } catch (Throwable) {
            throw new InvalidArgumentException('Invalid filter flag provided');
        }
    }

    /**
     * Flattens a nested array structure into a single-level array.
     *
     * @param mixed $value
     *
     * @return array
     */
    protected static function flatten(mixed $value): array
    {
        // If it's already a filter entry (has required keys), return it as a single-item array
        if (is_array($value) && isset($value['field']) && isset($value['operation'])) {
            return [$value];
        }

        // If it's an array, recursively flatten
        if (is_array($value)) {
            $result = [];
            foreach ($value as $item) {
                array_push($result, ...static::flatten($item));
            }

            return $result;
        }

        // Otherwise return as-is
        return [$value];
    }
}
