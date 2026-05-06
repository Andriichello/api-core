<?php

namespace Core\Queries;

use Core\Models\Organization;
use Core\Models\User;
use Illuminate\Database\Eloquent\Model;

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
     * Apply index query conditions for a given user.
     *
     * @param User|null $user
     * @param array $params
     *
     * @return OrganizationQuery
     */
    public function index(?Model $user, array $params = []): OrganizationQuery
    {
        if (!$user) {
            $this->whereRaw('1 = 0');
            return $this;
        }

        if ($user->hasInternalOrg() && $user->isSuperAdmin()) {
            return $this;
        }

        $this->where(function (OrganizationQuery $query) use ($user) {
            $query->where('organization.id', $user->orgId());
        });

        return $this;
    }
}
