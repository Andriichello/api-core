<?php

namespace Core\Models\Interfaces;

use Core\Enums\OrgContext;
use Core\Models\BaseModel;

/**
 * Interface HasOrgIdInterface.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Interfaces
 */
interface HasOrgIdInterface
{
    /**
     * Returns id of the organization, to which the record belongs.
     *
     * @return int|null
     */
    public function orgId(): ?int;
    /**
     * Returns the semantic context of the record.
     *
     * @return OrgContext|string
     */
    public function orgContext(): OrgContext|string;

    /**
     * Returns id of the organization, to which the record belongs.
     *
     * @return bool
     */
    public function hasNoOrg(): bool;

    /**
     * Returns true if the record and a given context belong
     * to the same organization.
     *
     * @param BaseModel|int|null $context
     *
     * @return bool
     */
    public function hasSameOrg(BaseModel|int|null $context): bool;

    /**
     * Returns true if the record has global organization context.
     *
     * @return bool
     */
    public function hasTenantOrg(): bool;

    /**
     * Returns true if the record has global organization context.
     *
     * @return bool
     */
    public function hasGlobalOrg(): bool;

    /**
     * Returns true if the record has internal organization context.
     *
     * @return bool
     */
    public function hasInternalOrg(): bool;
}
