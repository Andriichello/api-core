<?php

namespace Core\Policies\Interfaces;

use Core\Models\User;

/**
 * Interface ChecksPermissionsInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Policies\Interfaces
 */
interface ChecksPermissionsInterface
{
    /**
     * Determines if `arePermissionsMet()` should be called on given ability.
     *
     * @param User|null $user
     * @param string|null $ability
     *
     * @return bool
     */
    public function shouldCheckPermissions(mixed $user, ?string $ability = null): bool;

    /**
     * Determines if given abilities exist in the database
     * as permissions for the given resource.
     *
     * @param string|array $abilities Ability or list of abilities
     * @param string|null $resource Policy's resource if null
     *
     * @return bool
     */
    public function arePermissionsDefined(string|array $abilities, ?string $resource = null): bool;

    /**
     * Determines if user has met all the permissions
     * for the given resource.
     *
     * @param User|null $user
     * @param string|array $abilities Ability or list of abilities
     * @param string|null $resource Policy's resource if null
     *
     * @return bool
     */
    public function arePermissionsMet(mixed $user, string|array $abilities, ?string $resource = null): bool;
}
