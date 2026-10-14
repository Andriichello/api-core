<?php

namespace Core\Policies\Interfaces;

use Core\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Interface LimitsCUDInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Policies\Interfaces
 */
interface LimitsCUDInterface
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
    public function before(mixed $user, string $ability): Response|bool|null;
}
