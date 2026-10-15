<?php

namespace Core\Models\Traits;

use Core\Enums\UserRole;
use Core\Helpers\Helpers;
use Core\Models\User;
use Core\Repositories\RoleRepository;
use Spatie\Permission\Models\Role;

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
     * Determines if the current user is higher than the given role: one of
     * the user's roles ranks above it in `api-core.roles`, or the user is a
     * super admin. A role, which isn't defined there, is only below the super
     * admin.
     *
     * @param UserRole|Role|string $role The role to compare against
     *
     * @return bool
     */
    public function isHigherThanRole(UserRole|Role|string $role): bool
    {
        $name = match (true) {
            $role instanceof UserRole => $role->value,
            $role instanceof Role => $role->name,
            default => $role,
        };

        $ownRank = $this->rankOfHighestRole();

        if ($ownRank === null) {
            return false;
        }

        $rank = app(RoleRepository::class)->rankOf($name);

        return $rank === null ? $ownRank === 0 : $ownRank < $rank;
    }

    /**
     * Get the rank of the user's highest role, which is defined, where 0 is
     * the super admin, or null, when the user has none of them.
     *
     * @return int|null
     */
    public function rankOfHighestRole(): ?int
    {
        $roles = app(RoleRepository::class);

        return $this->getRoleNames()
            ->map(fn (string $name) => $roles->rankOf($name))
            ->filter(fn (?int $rank) => $rank !== null)
            ->min();
    }

    /**
     * Returns the roles, which are available to the current user: the user's
     * own roles and the lower ones, from the highest to the lowest.
     *
     * @return array<int, string>
     */
    public function availableRoles(): array
    {
        return array_values(array_filter(
            app(RoleRepository::class)->names(),
            fn (string $role) => $this->isOfRole($role) || $this->isHigherThanRole($role)
        ));
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
     * Determines if the current user can impersonate a given user: a super
     * admin can, and so can a main account its linked accounts.
     *
     * @param User $user
     *
     * @return bool
     */
    public function canImpersonateUser(User $user): bool
    {
        return $this->isOfRole(UserRole::SuperAdmin)
            || $this->id === $user->main_account_id;
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
