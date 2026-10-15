<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Enums\OrgContext;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Tests\TestCase;

/**
 * Class HasOrgIdTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 */
class HasOrgIdTest extends TestCase
{
    /**
     * Make deprecations fail the tests, so `organization_id` isn't converted the way, which PHP deprecates.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDeprecationHandling();
    }

    /**
     * Make a record with the organization id.
     *
     * @param int|null $organizationId
     *
     * @return BaseModel
     */
    private function record(?int $organizationId): BaseModel
    {
        return (new class extends BaseModel {})->forceFill(['organization_id' => $organizationId]);
    }

    /**
     * Test that a record without an organization, like a super admin, is internal and has no organization.
     *
     * @return void
     */
    public function testRecordWithoutOrganizationIsInternal(): void
    {
        $record = $this->record(null);

        $this->assertSame(OrgContext::Internal, $record->orgContext());
        $this->assertTrue($record->hasNoOrg());
        $this->assertTrue($record->hasInternalOrg());
        $this->assertFalse($record->hasGlobalOrg());
        $this->assertFalse($record->hasTenantOrg());
    }

    /**
     * Test the contexts of records, which have an organization id: a tenant's, the internal and the global one,
     * and an unknown one, none of which is a record without an organization.
     *
     * @return void
     */
    public function testContextsOfRecordsWithOrganizationId(): void
    {
        $contexts = [
            [5, 'tenant', 'hasTenantOrg'],
            [0, OrgContext::Internal, 'hasInternalOrg'],
            [-1, OrgContext::Global, 'hasGlobalOrg'],
            [-5, 'unknown', null],
        ];

        foreach ($contexts as [$organizationId, $context, $check]) {
            $record = $this->record($organizationId);

            $this->assertSame($context, $record->orgContext());
            $this->assertFalse($record->hasNoOrg());

            foreach (['hasTenantOrg', 'hasInternalOrg', 'hasGlobalOrg'] as $method) {
                $this->assertSame($method === $check, $record->$method(), "$method of $organizationId");
            }
        }
    }

    /**
     * Test that an organization is in its own tenant context.
     *
     * @return void
     */
    public function testOrganizationIsItsOwnTenant(): void
    {
        $organization = (new Organization)->forceFill(['id' => 7]);

        $this->assertSame(7, $organization->orgId());
        $this->assertSame('tenant', $organization->orgContext());
    }

    /**
     * Test that records of the same organization, or both without one, have the same organization.
     *
     * @return void
     */
    public function testHasSameOrg(): void
    {
        $record = $this->record(5);

        $this->assertTrue($record->hasSameOrg(5));
        $this->assertTrue($record->hasSameOrg($this->record(5)));
        $this->assertFalse($record->hasSameOrg(6));
        $this->assertFalse($record->hasSameOrg(null));
        $this->assertTrue($this->record(null)->hasSameOrg(null));
    }
}
