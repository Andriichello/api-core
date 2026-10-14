<?php

namespace Core\Policies\Traits;

use Core\Models\User;
use Core\Policies\BasePolicy;
use Core\Policies\Interfaces\LimitsCUDInterface;
use Illuminate\Auth\Access\Response;

/**
 * Trait LimitsCUD.
 *
 * Limits creating, updating and deleting (force deleting included)
 * to super admins, whatever permissions other users have.
 *
 * @mixin BasePolicy
 * @see LimitsCUDInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Policies\Traits
 */
trait LimitsCUD
{
    /**
     * Perform pre-authorization checks. Be aware that the ability
     * method won't be called unless null is returned here.
     *
     * @param User|null $user
     * @param string $ability
     *
     * @return Response|bool|null
     */
    public function before(mixed $user, string $ability): Response|bool|null
    {
        $isCUD = in_array($ability, ['create', 'update', 'delete', 'forceDelete']);

        if ($isCUD && !$user?->isSuperAdmin()) {
            return false;
        }

        return parent::before($user, $ability);
    }
}
