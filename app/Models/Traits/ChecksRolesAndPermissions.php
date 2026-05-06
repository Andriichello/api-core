<?php

namespace Core\Models\Traits;

use Core\Enums\UserRole;
use Core\Helpers\Helpers;
use Spatie\Permission\Models\Role;
use Core\Models\User;

/**
 * Trait ChecksRolesAndPermissions.
 *
 * @mixin User
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait ChecksRolesAndPermissions
{
    /**
     * Check if a user has a given role attached.
     *
     * @param UserRole|string $role
     *
     * @return bool
     */
    public function isOfRole(mixed $role): bool
    {
        if ($role instanceof UserRole) {
            $role = $role->value;
        }

        return $this->hasRole($role);
    }

    /**
     * Check if a user has a Guest role.
     *
     * @return bool
     */
    public function isGuest(): bool
    {
        return $this->isOfRole(UserRole::Guest);
    }

    /**
     * Check if a user has a User role.
     *
     * @return bool
     */
    public function isUser(): bool
    {
        return $this->isOfRole(UserRole::User);
    }

    /**
     * Check if a user has an Admin role.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->isOfRole(UserRole::Admin);
    }

    /**
     * Check if a user has a Super Admin role.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->isOfRole(UserRole::SuperAdmin);
    }

    /**
     * Check if the user has any Admin role.
     *
     * @return bool
     */
    public function hasRightsOfAdmin(): bool
    {
        return $this->isOfRole(UserRole::SuperAdmin) ||
            $this->isOfRole(UserRole::Admin);
    }

    /**
     * Determines if the current user is higher than the given one (by role).
     *
     * @param User $user
     *
     * @return bool
     */
    public function isHigherThan(User $user): bool
    {
        // Cannot be higher without any roles
        if ($this->roles->isEmpty()) {
            return false;
        }
        // Is higher because given user has no roles,
        // but current user has at least one role
        if ($user->roles->isEmpty()) {
            return true;
        }

        foreach ($user->roles as $role) {
            if (!$this->isHigherThanRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines if the current user is higher than the given role.
     *
     * @param UserRole|Role|string $role The role to compare against
     *
     * @return bool
     */
    public function isHigherThanRole(UserRole|Role|string $role): bool
    {
        if (is_string($role)) {
            $role = UserRole::from($role);
        }

        if ($role instanceof Role) {
            $role = UserRole::from($role->name);
        }

        // Define the role hierarchy from highest to lowest.
        $roles = [
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::User,
            UserRole::Guest,
        ];

        foreach ($roles as $ceiling) {
            // If the current role matches the provided role,
            // the user does not have a higher role.
            if ($role === $ceiling) {
                return false;
            }

            // If the current user's role matches the ceiling (a higher role in the hierarchy),
            // return true as their role is higher than the provided one.
            if ($this->isOfRole($ceiling)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns an array of roles that are available to current user.
     *
     * @return array
     */
    public function availableRoles(): array
    {
        return array_filter(
            UserRole::values(),
            fn(string $role) => $this->isOfRole($role)
                || $this->isHigherThanRole($role)
        );
    }

    /**
     * Determines if the current user has ability on the resource.
     *
     * @param string $ability
     * @param string $resource
     *
     * @return bool
     */
    public function canOn(string $ability, string $resource): bool
    {
        return $this->can($ability . ' ' . Helpers::morphOf($resource));
    }

    /**
     * Determines if the current user can impersonate a given user.
     *
     * @param User $user
     *
     * @return bool
     */
    public function canImpersonateUser(User $user): bool
    {
        return $this->isOfRole(UserRole::SuperAdmin);
    }

    /**
     * Determines if the current user can be impersonated by a given user.
     *
     * @param User $user
     *
     * @return bool
     */
    public function canBeImpersonatedByUser(User $user): bool
    {
        return $user->canImpersonateUser($this);
    }
}
