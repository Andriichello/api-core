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
            $result = array_merge(
                $result,
                static::parseNode($field, $nodes)
            );
        }

        return $result;
    }

    /**
     * Parses the filter node into a structured format.
     *
     * @param string $field
     * @param mixed $node
     * @param string[] $path
     *
     * @return array{
     *     field: string,
     *     operation: FilterOperation,
     *     flags: FilterFlag[],
     *     value: mixed,
     * }
     * @throws InvalidArgumentException
     */
    public static function parseNode(string $field, mixed $node, array $path = []): array
    {
        // leaf → operation reached
        if (!is_array($node)) {
            $operation = array_pop($path);

            try {
                $operation = $operation === null
                    ? FilterOperation::EQ
                    : FilterOperation::from($operation);
            } catch (Throwable) {
                throw new InvalidArgumentException('Invalid filter operation provided: ' . $operation);
            }

            try {
                $flags = array_map(
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

            return [
                'field' => $field,
                'operation' => $operation,
                'flags' => $flags,
                'value' => $node,
            ];
        }

        $result = [];

        foreach ($node as $key => $child) {
            $result = array_merge(
                $result,
                static::parseNode($field, $child, [...$path, $key])
            );
        }

        return $result;
    }
}
