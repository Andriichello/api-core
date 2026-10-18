<?php

namespace Core\Queries;

use Core\Models\AuditLog;
use Core\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AuditLogQuery.
 *
 * @method AuditLogQuery scopes($scopes)
 *
 * @method AuditLog|null find($id, $columns = ['*'])
 * @method AuditLog findOrFail($id, $columns = ['*'])
 * @method AuditLog|null first($columns = ['*'])
 * @method AuditLog firstOrFail($columns = ['*'])
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries
 */
class AuditLogQuery extends BaseQuery
{
    /**
     * Apply index query conditions for a given user: the entries of the
     * organization, in which the user acts, or all of them for a super admin,
     * who acts in all organizations. Other users without an organization see
     * none.
     *
     * @param User|null $user
     * @param array $params
     *
     * @return AuditLogQuery
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function index(?Model $user, array $params = []): AuditLogQuery
    {
        if ($user?->actsInAllOrganizations()) {
            return $this;
        }

        $orgId = $user?->actingOrgId();

        if ($orgId === null) {
            $this->whereRaw('1 = 0');

            return $this;
        }

        $this->where($this->qualifyColumn('organization_id'), $orgId);

        return $this;
    }

    /**
     * Limit the query to the entries of the given model.
     *
     * @param Model $subject
     *
     * @return AuditLogQuery
     */
    public function ofSubject(Model $subject): AuditLogQuery
    {
        $this->whereMorphedTo('subject', $subject);

        return $this;
    }
}
