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
 * @method Organization create(array $attributes = [])
 * @method Organization forceCreate(array $attributes)
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries
 */
class OrganizationQuery extends BaseQuery
{
    /**
     * Apply index query conditions for a given user: the organization, in
     * which the user acts, or all of them for a super admin, who acts in all
     * organizations.
     *
     * @param User|null $user
     * @param array $params
     *
     * @return OrganizationQuery
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function index(?Model $user, array $params = []): OrganizationQuery
    {
        if (!$user) {
            $this->whereRaw('1 = 0');

            return $this;
        }

        if ($user->actsInAllOrganizations()) {
            return $this;
        }

        $this->where(function (OrganizationQuery $query) use ($user) {
            $query->where('organizations.id', $user->actingOrgId());
        });

        return $this;
    }
}
