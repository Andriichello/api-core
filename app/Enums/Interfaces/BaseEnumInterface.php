<?php

namespace Core\Enums\Interfaces;

use BackedEnum;

/**
 * Trait BaseEnumInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Enums\Interfaces
 */
interface BaseEnumInterface extends BackedEnum
{
    /**
     * Returns a list of enumeration names.
     *
     * @return array
     */
    public static function names(): array;

    /**
     * Returns a list of enumeration values.
     *
     * @return array
     */
    public static function values(): array;

    /**
     * Returns a list of enumeration names to values.
     *
     * @return array
     */
    public static function list(): array;
}
