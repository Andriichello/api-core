<?php

namespace Core\Database\Seeders;

use Core\Repositories\RoleRepository;
use Illuminate\Database\Seeder;

/**
 * Class RolesAndPermissionsSeeder.
 *
 * Syncs the roles and their permissions with `api-core.roles`. It may run
 * again, whenever the roles change: it creates what is missing, gives each
 * role exactly the configured permissions, and keeps the roles of users.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Database\Seeders
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @param RoleRepository $roles
     *
     * @return void
     */
    public function run(RoleRepository $roles): void
    {
        $roles->sync();
    }
}
