<?php

namespace Core\Repositories;

use Core\Models\User;
use Core\Enums\UserRole;
use Core\Models\BaseModel;

/**
 * Class UserRepository.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
class UserRepository extends CrudRepository
{
    /**
     * Repository's model class.
     *
     * @var BaseModel|string
     */
    protected BaseModel|string $model = User::class;

    /**
     * Assign roles to a given user.
     *
     * @param User $user
     * @param UserRole|string ...$roles
     *
     * @return void
     */
    public function assignRole(User $user, UserRole|string ...$roles): void
    {
        $roles = array_map(
            function (UserRole|string $role) {
                return $role instanceof UserRole
                    ? $role->value : $role;
            },
            $roles
        );

        $roles = array_filter(
            $roles,
            function (string $role) use ($user) {
                return !$user->hasRole($role);
            }
        );

        $user->assignRole($roles);
    }
}
