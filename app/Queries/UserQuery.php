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
     * Apply index query conditions for a given user.
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

        if ($user->hasInternalOrg() && $user->isSuperAdmin()) {
            return $this;
        }

        $this->where(function (UserQuery $query) use ($user) {
            // Accounts of the same person, linked by the main account, see each other
            $query->where('users.id', $user->mainAccountId())
                ->orWhere('users.main_account_id', $user->mainAccountId());

            // Users without an organization can only see their own accounts
            if ($user->orgId() !== null) {
                $query->orWhere('users.organization_id', $user->orgId());
            }
        });

        return $this;
    }
}
