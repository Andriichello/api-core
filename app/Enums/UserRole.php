<?php

namespace Core\Enums;

use Core\Enums\Interfaces\BaseEnumInterface;
use Core\Enums\Traits\BaseEnum;

/**
 * Enum UserRole.
 *
 * The super admin, api-core's own role above all others, which needs no
 * permissions, and the default roles of `api-core.roles`, which an
 * application may replace with its own (see RoleRepository).
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
