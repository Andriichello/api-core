<?php

namespace Core\Policies\Traits;

use Core\Helpers\Helpers;
use Core\Models\User;
use Core\Policies\BasePolicy;
use Core\Policies\Interfaces\ChecksPermissionsInterface;
use Illuminate\Support\Arr;
use Spatie\Permission\PermissionRegistrar;

/**
 * Trait ChecksPermissions.
 *
 * Checks the `{ability} {morph}` permissions (e.g. `update models.users`),
 * which `PermissionRepository` creates. Super admins skip the check, and
 * a permission that is not defined is only met by them, unless
 * `isMissingPermissionMet()` says otherwise. Permissions are read from
 * Spatie's cached registry, not queried on every check.
 *
 * @mixin BasePolicy
 * @see ChecksPermissionsInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Policies\Traits
 */
trait ChecksPermissions
{
    /**
     * Determines if `arePermissionsMet()` should be called on given ability.
     *
     * @param User|null $user
     * @param string|null $ability
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function shouldCheckPermissions(mixed $user, ?string $ability = null): bool
    {
        if ($user && $user->isSuperAdmin()) {
            return false;
        }

        return $this->getModel() !== null;
    }

    /**
     * Get the names of the permissions for given abilities on the resource.
     *
     * @param string|array $abilities Ability or list of abilities
     * @param string|null $resource Policy's resource if null
     *
     * @return array<int, string>
     */
    protected function getPermissionNames(string|array $abilities, ?string $resource = null): array
    {
        $morph = Helpers::morphOf($resource ?: $this->getModel());

        return array_values(
            array_unique(
                array_map(
                    fn (string $ability) => $ability . ' ' . $morph,
                    Arr::wrap($abilities)
                )
            )
        );
    }

    /**
     * Determines if a permission with the given name is defined.
     *
     * @param string $name
     *
     * @return bool
     */
    protected function isPermissionDefined(string $name): bool
    {
        return app(PermissionRegistrar::class)
            ->getPermissions(['name' => $name])
            ->isNotEmpty();
    }

    /**
     * Determines if given abilities exist in the database
     * as permissions for the given resource.
     *
     * @param string|array $abilities Ability or list of abilities
     * @param string|null $resource Policy's resource if null
     *
     * @return bool
     */
    public function arePermissionsDefined(string|array $abilities, ?string $resource = null): bool
    {
        foreach ($this->getPermissionNames($abilities, $resource) as $name) {
            if (!$this->isPermissionDefined($name)) {
                return false;
            }
        }

        return true;
    }

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
    public function arePermissionsMet(mixed $user, string|array $abilities, ?string $resource = null): bool
    {
        if (!$user) {
            return false;
        }

        foreach ($this->getPermissionNames($abilities, $resource) as $name) {
            $isMet = $this->isPermissionDefined($name)
                ? $user->checkPermissionTo($name)
                : $this->isMissingPermissionMet($user, $name);

            if (!$isMet) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines if a permission, which is not defined, is met
     * by the given user. Only super admins meet it by default.
     *
     * @param User $user
     * @param string $permission
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    protected function isMissingPermissionMet(mixed $user, string $permission): bool
    {
        return (bool) $user->isSuperAdmin();
    }
}
