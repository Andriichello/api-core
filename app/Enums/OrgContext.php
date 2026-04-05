<?php

namespace Core\Enums;

use Core\Enums\Interfaces\BaseEnumInterface;
use Core\Enums\Traits\BaseEnum;

/**
 * Enum OrgContext.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Enums
 */
enum OrgContext: int implements BaseEnumInterface
{
    use BaseEnum;

    /** Visible and usable by all tenants (Read-only for them). */
    case Global = -1;

    /** Strictly for internal API/System operations. */
    case Internal = 0;
}
