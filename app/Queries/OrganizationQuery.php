<?php

namespace Core\Queries;

use Core\Models\Organization;
use Core\Models\User;
use Illuminate\Http\Request;

/**
 * Class OrganizationQuery.
 *
 * @method OrganizationQuery scopes($scopes)
 *
 * @method Organization|null find($id, $columns = ['*'])
 * @method Organization findOrFail($id, $columns = ['*'])
 * @method Organization|null first($columns = ['*'])
 * @method Organization firstOrFail($columns = ['*'])
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries\Api
 */
class OrganizationQuery extends BaseQuery
{
    /**
     * Apply index query conditions.
     *
     * @param Request $request
     *
     * @return OrganizationQuery
     */
    public function index(Request $request): OrganizationQuery
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

        $this->where(function (OrganizationQuery $query) use ($user) {
            $query->where('organization.id', $user->orgId());
        });

        return $this;
    }
}
