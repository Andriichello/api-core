<?php

namespace Core\Repositories;

use Core\Enums\UserRole;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
     * Find the accounts, into which the email and password sign in. The password
     * is checked against the main account, as all accounts of a person share it.
     * With the slug of an organization, it is the account in that organization only.
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
            ->filter(fn (User $user) => Hash::check($password, $user->getAuthPassword()))
            ->values();
    }

    /**
     * Set the password of every person, who has an account with the email, as
     * whoever controls the email may reset the password of any of them. It is
     * set with one query on all accounts of those people, deleted ones included,
     * as SharesPasswordAcrossAccounts would set it, so no model events are fired.
     *
     * @param string $email
     * @param string $password
     *
     * @return Collection<int, User> The accounts with the email, one for each
     *                               person, whose password is set.
     */
    public function resetPassword(string $email, string $password): Collection
    {
        $accounts = User::query()
            ->where('email', Str::lower($email))
            ->get()
            ->unique(fn (User $user) => $user->mainAccountId())
            ->values();

        User::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->ofPeople(...$accounts)
            ->update(['password' => Hash::make($password)]);

        return $accounts;
    }
}
