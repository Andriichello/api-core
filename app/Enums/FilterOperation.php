<?php

namespace Core\Enums;

use Core\Enums\Interfaces\BaseEnumInterface;
use Core\Enums\Traits\BaseEnum;

/**
 * Enum FilterOperation.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Enums
 */
enum FilterOperation: string implements BaseEnumInterface
{
    use BaseEnum;

    // Equality:
    case EQ = 'eq';
    case IN = 'in';
    case NULL = 'null';

    // Greater:
    case GT = 'gt';
    case GTE = 'gte';

    // Less:
    case LT = 'lt';
    case LTE = 'lte';
}
