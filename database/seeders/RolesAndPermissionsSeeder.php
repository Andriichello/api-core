<?php

namespace Database\Seeders;

use Core\Enums\UserRole;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\PermissionRepository;
use Core\Repositories\UserRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Class RolesAndPermissionsSeeder.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Database\Seeders
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * List of resources that are considered internal
     * and can only be created, updated, and deleted
     * by the SpeedGoat's stuff members.
     *
     * @var array
     */
    protected array $internal = [
        User::class,
        Organization::class,
    ];

    /**
     * List of resources that are considered external
     * and can be created, updated, and deleted by the
     * project members.
     *
     * @var array
     */
    protected array $external = [
        User::class,
        Organization::class,
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $usersWithRoles = User::query()->with('roles')->get()->toArray();

        // delete roles and permissions
        $this->truncateRolesAndPermissions();

        // create roles and permissions
        $this->processRole(UserRole::SuperAdmin->value);
        $this->processRole(UserRole::Admin->value);
        $this->processRole(UserRole::User->value);
        $this->processRole(UserRole::Guest->value);

        $this->processCustomPermissions();

        // assign super admin role to users without a project
        /** @var UserRepository $repo */
        $repo = app(UserRepository::class);

        foreach ($usersWithRoles as $userWithRoles) {
            /** @var User $userWithRoles */
            $roles = Arr::pluck($userWithRoles['roles'], 'name');
            if (empty($roles)) {
                continue;
            }

            $user = User::query()->find($userWithRoles['id']);
            $repo->assignRole($user, ...$roles);
        }
    }

    /**
     * Remove all roles and permissions from the database.
     *
     * @return void
     */
    public function truncateRolesAndPermissions(): void
    {
        foreach (Role::all() as $role) {
            $role->permissions()->detach();
        }

        /* @phpstan-ignore-next-line */
        Role::truncate();
        /* @phpstan-ignore-next-line */
        Permission::truncate();
    }

    /**
     * Create a role with permissions.
     *
     * @param string $role
     *
     * @return Role
     */
    public function processRole(string $role): Role
    {
        /** @var Role|null $model */
        $model = Role::query()
            ->where('guard_name', Guard::getDefaultName(Role::class))
            ->where('name', $role)
            ->first();

        /** @var Role $model */
        $model = $model ?? Role::create(['name' => $role]);

        /** @var PermissionRepository $repo */
        $repo = app(PermissionRepository::class);

        foreach ($this->permissionsFor($role) as $resource => $actions) {
            $model->givePermissionTo(
                $repo->create($resource, ...Arr::wrap($actions))
            );
        }

        return $model;
    }

    /**
     * Get a list of permissions for a given user role.
     *
     * @param string $role
     *
     * @return array
     */
    public function permissionsFor(string $role): array
    {
        $permissions = [];

        if ($role === UserRole::Guest->value) {
            return $this->processPermissionsForGuest($permissions);
        }

        if ($role === UserRole::User->value) {
            return $this->processPermissionsForUser($permissions);
        }

        if ($role === UserRole::Admin->value) {
            return $this->processPermissionsForAdmin($permissions);
        }

        return $permissions;
    }

    /**
     * Process permissions for a guest.
     *
     * @param array $permissions The array of permissions to process.
     * @return array The updated array of permissions.
     */
    public function processPermissionsForGuest(array $permissions): array
    {
        foreach ($this->internal as $resource) {
            if (in_array($resource, [Role::class, Permission::class])) {
                continue;
            }

            $permissions[$resource] = ['viewAny', 'view'];
        }

        foreach ($this->external as $resource) {
            $permissions[$resource] = ['viewAny', 'view'];
        }

        return $permissions;
    }

    /**
     * Process permissions for a user.
     *
     * @param array $permissions The array of permissions to process.
     *
     * @return array The updated array of permissions.
     */
    public function processPermissionsForUser($permissions): array
    {
        $permissions = $this->processPermissionsForGuest($permissions);

        $additional = [
            // models that need permissions added here...
        ];

        foreach ($additional as $resource) {
            $permissions[$resource] = ['all', ...data_get($permissions, $resource, [])];
        }

        return $permissions;
    }

    /**
     * Process permissions for an admin.
     *
     * @param array $permissions The array of permissions to process.
     *
     * @return array The updated array of permissions.
     */
    public function processPermissionsForAdmin(array $permissions): array
    {
        foreach ($this->internal as $resource) {
            if ($resource == Permission::class) {
                continue;
            }

            $permissions[$resource] = ['viewAny', 'view'];
        }

        foreach ($this->external as $resource) {
            $permissions[$resource] = ['all', ...data_get($permissions, $resource, [])];
        }

        return $permissions;
    }

    /**
     * Create custom permissions.
     *
     * @return void
     */
    public function processCustomPermissions(): void
    {
        /** @var PermissionRepository $repo */
        $repo = app(PermissionRepository::class);

        /** @var Role $adminRole */
        $adminRole = Role::findByName(UserRole::Admin->value);

        $adminRole->givePermissionTo(
            ...$repo->create(
                User::class,
                'attachRole',
                'attachAnyRole',
                'detachRole'
            )
        );
        $adminRole->givePermissionTo(
            ...$repo->create(
                Role::class,
                'attachUser',
                'attachAnyUser',
                'detachUser',
            )
        );

        /** @var Role $userRole */
        $userRole = Role::findByName(UserRole::User->value);

        $userRole->givePermissionTo(
            ...$repo->create(User::class, 'update')
        );
    }
}
