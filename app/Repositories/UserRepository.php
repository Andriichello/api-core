<?php

namespace Core\Repositories;

use Core\Enums\UserRole;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    /**
     * Find the accounts, into which the email and password sign in. With the
     * slug of an organization, it is the account in that organization only.
     *
     * @param string $email
     * @param string $password
     * @param string|null $organization
     *
     * @return Collection<int, User>
     */
    public function findByCredentials(string $email, string $password, ?string $organization = null): Collection
    {
        $query = User::query()
            ->where('email', Str::lower($email));

        if ($organization !== null) {
            $query->whereIn(
                'organization_id',
                Organization::query()->select('id')->where('slug', $organization)
            );
        }

        return $query->get()
            ->filter(fn (User $user) => Hash::check($password, $user->password))
            ->values();
    }
}
