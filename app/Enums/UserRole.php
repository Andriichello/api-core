<?php

namespace Core\Enums;

use Core\Enums\Interfaces\BaseEnumInterface;
use Core\Enums\Traits\BaseEnum;

/**
 * Enum UserRole.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Enums
 */
enum UserRole: string implements BaseEnumInterface
{
    use BaseEnum;

    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'super-admin';
}
