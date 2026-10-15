<?php

namespace Core\Repositories;

use Core\Helpers\Helpers;
use Spatie\Permission\Models\Permission;

/**
 * Class PermissionRepository.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
class PermissionRepository
{
    /**
     * List of actions that can be performed on a resource.
     *
     * @var string[]
     */
    protected array $actions = [
        'view' => 'view',
        'viewAny' => 'viewAny',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
    ];

    /**
     * Create permissions for the given resource.
     *
     * @param string $resource
     * @param string ...$permissions
     *
     * @return array<Permission>
     */
    public function create(string $resource, string ...$permissions): array
    {
        $resource = Helpers::morphOf($resource);

        return array_map(
            function (string $action) use ($resource) {
                /** @var Permission $permission */
                $permission = Permission::query()
                    ->firstOrCreate(['name' => "$action $resource"]);

                return $permission;
            },
            $this->actionsFrom(...$permissions)
        );
    }

    /**
     * Get a list of actions from the given list of permissions.
     *
     * @param string ...$permissions
     *
     * @return string[]
     */
    public function actionsFrom(string ...$permissions): array
    {
        if (in_array('all', $permissions)) {
            $permissions = array_filter(
                $permissions,
                fn (string $permission) => $permission !== 'all'
            );

            $permissions = array_merge($permissions, array_values($this->actions));
        }

        return array_unique($permissions);
    }
}
