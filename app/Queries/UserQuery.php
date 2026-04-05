<?php

namespace Core\Queries;

use Core\Models\User;
use Illuminate\Http\Request;

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
     * Apply index query conditions.
     *
     * @param Request $request
     *
     * @return UserQuery
     */
    public function index(Request $request): UserQuery
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            $this->whereRaw('1 = 0');
            return $this;
        }

        // todo: check if user is a super-admin
        if ($user->hasInternalOrg()) {
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
