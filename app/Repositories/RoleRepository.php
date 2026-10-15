<?php

namespace Core\Repositories;

use Core\Enums\UserRole;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Class RoleRepository.
 *
 * The roles of users, which an application defines in `api-core.roles`, from
 * the highest to the lowest, with their permissions, and api-core's super
 * admin above all of them.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
class RoleRepository
{
    /**
     * Create a new repository instance.
     *
     * @param PermissionRepository $permissions
     */
    public function __construct(
        protected PermissionRepository $permissions
    ) {}

    /**
     * Get the roles, which the application defines, from the highest to the
     * lowest, with the actions, which each of them has on the resources.
     *
     * @return array<int, array{name: string, permissions?: array<string, string|array<int, string>>}>
     */
    public function definitions(): array
    {
        return array_values((array) config('api-core.roles', []));
    }

    /**
     * Get the names of the roles from the highest to the lowest: the super
     * admin, and then the roles, which the application defines.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_values(array_unique([
            UserRole::SuperAdmin->value,
            ...array_column($this->definitions(), 'name'),
        ]));
    }

    /**
     * Get the rank of the role, where 0 is the highest one (the super admin),
     * or null, when the role isn't defined.
     *
     * @param string $role
     *
     * @return int|null
     */
    public function rankOf(string $role): ?int
    {
        $rank = array_search($role, $this->names(), true);

        return $rank === false ? null : $rank;
    }

    /**
     * Create the roles, which the application defines, and their permissions,
     * and give each of them exactly the defined permissions. The super admin
     * needs none. Roles, which aren't defined, are kept, as are the roles of
     * users.
     *
     * @return void
     */
    public function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        Role::findOrCreate(UserRole::SuperAdmin->value)->syncPermissions([]);

        foreach ($this->definitions() as $definition) {
            $permissions = [];

            foreach ($definition['permissions'] ?? [] as $resource => $actions) {
                array_push($permissions, ...$this->permissions->create($resource, ...Arr::wrap($actions)));
            }

            Role::findOrCreate($definition['name'])->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }
}
