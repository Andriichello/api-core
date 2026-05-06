<?php

namespace Core\Queries;

use Core\Models\User;
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
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries\Api
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
            $query->where('users.id', $user->id)
                ->orWhere(function (UserQuery $query) use ($user) {
                    $query->whereIn('users.organization_id', $user->orgId());
                });
        });

        return $this;
    }
}
