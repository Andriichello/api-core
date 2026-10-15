<?php

namespace Core\Queries;

use Core\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UserQuery.
 *
 * @method UserQuery scopes($scopes)
 *
 * @method User|null find($id, $columns = ['*'])
 * @method User findOrFail($id, $columns = ['*'])
 * @method User|null first($columns = ['*'])
 * @method User firstOrFail($columns = ['*'])
 * @method User create(array $attributes = [])
 * @method User forceCreate(array $attributes)
 * @method Collection<int, User> get($columns = ['*'])
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries
 */
class UserQuery extends BaseQuery
{
    /**
     * Apply index query conditions for a given user: the person's own accounts
     * and the users of the organization, in which the user acts, or all users
     * for a super admin, who acts in all organizations.
     *
     * @param User|null $user
     * @param array $params
     *
     * @return UserQuery
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function index(?Model $user, array $params = []): UserQuery
    {
        if (!$user) {
            $this->whereRaw('1 = 0');

            return $this;
        }

        if ($user->actsInAllOrganizations()) {
            return $this;
        }

        $orgId = $user->actingOrgId();

        $this->where(function (UserQuery $query) use ($user, $orgId) {
            // Accounts of the same person, linked by the main account, see each other
            $query->where('users.id', $user->mainAccountId())
                ->orWhere('users.main_account_id', $user->mainAccountId());

            // Users without an organization can only see their own accounts
            if ($orgId !== null) {
                $query->orWhere('users.organization_id', $orgId);
            }
        });

        return $this;
    }

    /**
     * Limit the query to the accounts of the person, who owns the account:
     * their main account and the accounts linked to it.
     *
     * @param User $user
     *
     * @return UserQuery
     */
    public function ofPerson(User $user): UserQuery
    {
        return $this->ofPeople($user);
    }

    /**
     * Limit the query to the accounts of the people, who own the accounts:
     * their main accounts and the accounts linked to them.
     *
     * @param User ...$users
     *
     * @return UserQuery
     */
    public function ofPeople(User ...$users): UserQuery
    {
        $mainAccountIds = array_values(array_unique(
            array_map(fn (User $user) => $user->mainAccountId(), $users)
        ));

        $this->where(function (UserQuery $query) use ($mainAccountIds) {
            $query->whereIn('users.id', $mainAccountIds)
                ->orWhereIn('users.main_account_id', $mainAccountIds);
        });

        return $this;
    }
}
