<?php

namespace Core\Enums\Traits;

use BackedEnum;

/**
 * Trait BaseEnum.
 *
 * @mixin BackedEnum
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Enums\Traits
 */
trait BaseEnum
{
    /**
     * Returns a list of enumeration names.
     *
     * @return array
     */
    public static function names(): array
    {
        $names = [];

        foreach (static::cases() as $case) {
            $names[] = $case->name;
        }

        return $names;
    }

    /**
     * Returns a list of enumeration values.
     *
     * @return array
     */
    public static function values(): array
    {
        $values = [];

        foreach (static::cases() as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    /**
     * Returns a list of enumeration names to values.
     *
     * @return array
     */
    public static function list(): array
    {
        $list = [];

        foreach (static::cases() as $case) {
            $list[$case->name] = $case->value;
        }

        return $list;
    }
}
