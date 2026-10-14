<?php

namespace Core\Enums;

use Core\Enums\Interfaces\BaseEnumInterface;
use Core\Enums\Traits\BaseEnum;

/**
 * Enum FilterFlag.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Enums
 */
enum FilterFlag: string implements BaseEnumInterface
{
    use BaseEnum;

    // Boolean:
    case Not = 'not';

    // Case-sensitivity:
    case CaseInsensitive = 'ci';
}
