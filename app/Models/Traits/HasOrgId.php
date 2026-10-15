<?php

namespace Core\Models\Traits;

use Core\Enums\OrgContext;
use Core\Models\BaseModel;
use Core\Models\Interfaces\HasOrgIdInterface;

/**
 * Trait HasOrgId.
 *
 * @mixin BaseModel
 * @see HasOrgIdInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait HasOrgId
{
    /**
     * Returns id of the organization, to which the record belongs.
     *
     * @return int|null
     */
    public function orgId(): ?int
    {
        return data_get($this, 'organization_id');
    }

    /**
     * Returns the semantic context of the record: `tenant`, when it belongs
     * to an organization, OrgContext::Internal, when it belongs to none, like
     * a super admin, or to the internal one, OrgContext::Global, when it is
     * shared with all tenants, or `unknown`.
     *
     * @return OrgContext|string
     */
    public function orgContext(): OrgContext|string
    {
        $orgId = $this->orgId();

        if ($orgId === null) {
            return OrgContext::Internal;
        }

        if ($orgId > 0) {
            return 'tenant';
        }

        return OrgContext::tryFrom($orgId) ?? 'unknown';
    }

    /**
     * Returns true if the record belongs to no organization.
     *
     * @return bool
     */
    public function hasNoOrg(): bool
    {
        return $this->orgId() === null;
    }

    /**
     * Returns true if the record and a given context belong
     * to the same organization.
     *
     * @param BaseModel|int|null $context
     *
     * @return bool
     */
    public function hasSameOrg(BaseModel|int|null $context): bool
    {
        $orgId = $context instanceof BaseModel
            ? $context->orgId() : $context;

        return $this->orgId() === $orgId;
    }

    /**
     * Returns true if the record belongs to a tenant organization.
     *
     * @return bool
     */
    public function hasTenantOrg(): bool
    {
        return $this->orgContext() === 'tenant';
    }

    /**
     * Returns true if the record has global organization context.
     *
     * @return bool
     */
    public function hasGlobalOrg(): bool
    {
        return $this->orgContext() === OrgContext::Global;
    }

    /**
     * Returns true if the record has internal organization context.
     *
     * @return bool
     */
    public function hasInternalOrg(): bool
    {
        return $this->orgContext() === OrgContext::Internal;
    }
}
