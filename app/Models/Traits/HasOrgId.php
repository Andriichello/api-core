<?php

namespace Core\Models\Traits;

use Core\Enums\OrgContext;
use Core\Models\BaseModel;
use Core\Models\Interfaces\HasOrgIdInterface;

/**
 * Trait HasOrgId.
 *
 * @mixin BaseModel
 * @implements HasOrgIdInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Speedgoat\Skeleton\Models\Traits
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
     * Returns the semantic context of the record.
     *
     * @return OrgContext|string
     */
    public function orgContext(): OrgContext|string
    {
        $orgId = $this->orgId();

        if ($orgId > 0) {
            return 'tenant';
        }

        return OrgContext::tryFrom($orgId) ?? 'unknown';
    }

    /**
     * Returns id of the organization, to which the record belongs.
     *
     * @return bool
     */
    public function hasNoOrg(): bool
    {
        return $this->orgContext() === OrgContext::Global;
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
     * Returns true if the record has global organization context.
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
