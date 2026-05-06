<?php

namespace Core\Tests\Unit\Resources;

use Core\Http\Resources\BaseResource;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

/**
 * Class BaseResourceIncludesTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Resources
 */
class BaseResourceIncludesTest extends TestCase
{
    public function testListOfAllowedIncludesReturnsNamesFromIntKeyedArray(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization', 'roles'];
        };

        $this->assertSame(['organization', 'roles'], $resource->listOfAllowedIncludes());
    }

    public function testListOfAllowedIncludesReturnsNamesFromStringKeyedArray(): void
    {
        $orgClass = get_class(new class(null) extends BaseResource {});

        $resource = new class($orgClass) extends BaseResource
        {
            public function __construct(string $orgClass)
            {
                parent::__construct(null);
                $this->allowedIncludes = ['organization' => $orgClass];
            }
        };

        $this->assertSame(['organization'], $resource->listOfAllowedIncludes());
    }

    public function testHasAllowedIncludesReturnsTrueForAllowedInclude(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertTrue($resource->hasAllowedIncludes('organization'));
    }

    public function testHasAllowedIncludesReturnsFalseForDisallowedInclude(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertFalse($resource->hasAllowedIncludes('roles'));
    }

    public function testHasAllowedIncludesReturnsTrueForNestedIncludeWhenParentIsAllowed(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertTrue($resource->hasAllowedIncludes('organization.users'));
    }

    public function testHasAllowedIncludesReturnsFalseForNestedIncludeWhenParentIsNotAllowed(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertFalse($resource->hasAllowedIncludes('roles.permissions'));
    }

    public function testResourceOfAllowedIncludeReturnsNullForIntKeyedInclude(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertNull($resource->resourceOfAllowedInclude('organization'));
    }

    public function testResourceOfAllowedIncludeReturnsClassForStringKeyedInclude(): void
    {
        $orgClass = get_class(new class(null) extends BaseResource {});

        $resource = new class($orgClass) extends BaseResource
        {
            public function __construct(string $orgClass)
            {
                parent::__construct(null);
                $this->allowedIncludes = ['organization' => $orgClass];
            }
        };

        $this->assertSame($orgClass, $resource->resourceOfAllowedInclude('organization'));
    }

    public function testGetAllowedIncludeReturnsMissingValueWhenRelationNotLoaded(): void
    {
        $user = new User;

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $result = $resource->getAllowedInclude('organization');

        $this->assertInstanceOf(MissingValue::class, $result);
    }

    public function testGetAllowedIncludeReturnsRelationValueWhenLoadedWithNoResourceClass(): void
    {
        $org = new Organization;
        $user = new User;
        $user->setRelation('organization', $org);

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $result = $resource->getAllowedInclude('organization');

        $this->assertSame($org, $result);
    }

    public function testGetAllowedIncludeWrapsRelationInResourceClassWhenProvided(): void
    {
        $orgResourceClass = get_class(new class(null) extends BaseResource {});

        $org = new Organization;
        $user = new User;
        $user->setRelation('organization', $org);

        $resource = new class($user, $orgResourceClass) extends BaseResource
        {
            public function __construct(mixed $user, string $orgResourceClass)
            {
                parent::__construct($user);
                $this->allowedIncludes = ['organization' => $orgResourceClass];
            }
        };

        $result = $resource->getAllowedInclude('organization');

        $this->assertInstanceOf(BaseResource::class, $result);
    }

    public function testGetAllowedIncludeSetsPassedIncludesOnWrappedResource(): void
    {
        $orgResourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['users'];
        });

        $org = new Organization;
        $user = new User;
        $user->setRelation('organization', $org);

        $resource = new class($user, $orgResourceClass) extends BaseResource
        {
            public function __construct(mixed $user, string $orgResourceClass)
            {
                parent::__construct($user);
                $this->allowedIncludes = ['organization' => $orgResourceClass];
            }
        };

        /** @var BaseResource $result */
        $result = $resource->getAllowedInclude('organization', ['organization', 'organization.users']);

        $this->assertInstanceOf(BaseResource::class, $result);
        $this->assertSame(['users'], $result->getPassedIncludes());
    }

    public function testGetAllowedIncludeThrowsExceptionForDisallowedInclude(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'roles' include is not allowed");

        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $resource->getAllowedInclude('roles');
    }

    public function testGetRequestedIncludesReturnsMapWithMissingValueWhenRelationNotLoaded(): void
    {
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $request = Request::create('/', 'GET', ['include' => 'organization']);
        $includes = $resource->getRequestedIncludes($request);

        $this->assertArrayHasKey('organization', $includes);
        $this->assertInstanceOf(MissingValue::class, $includes['organization']);
    }

    public function testGetRequestedIncludesReturnsResourceWhenRelationIsLoaded(): void
    {
        $orgResourceClass = get_class(new class(null) extends BaseResource {});

        $user = new User;
        $user->setRelation('organization', new Organization);

        $resource = new class($user, $orgResourceClass) extends BaseResource
        {
            public function __construct(mixed $user, string $orgResourceClass)
            {
                parent::__construct($user);
                $this->allowedIncludes = ['organization' => $orgResourceClass];
            }
        };

        $request = Request::create('/', 'GET', ['include' => 'organization']);
        $includes = $resource->getRequestedIncludes($request);

        $this->assertArrayHasKey('organization', $includes);
        $this->assertInstanceOf(BaseResource::class, $includes['organization']);
    }

    public function testGetRequestedIncludesThrowsForDisallowedInclude(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'roles' include is not allowed");

        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $resource->getRequestedIncludes(Request::create('/', 'GET', ['include' => 'roles']));
    }

    public function testPassedIncludesOverrideRequestIncludes(): void
    {
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $resource->setPassedIncludes(['organization']);

        $request = Request::create('/', 'GET', ['include' => 'roles']);
        $includes = $resource->getRequestedIncludes($request);

        $this->assertArrayHasKey('organization', $includes);
        $this->assertArrayNotHasKey('roles', $includes);
    }

    public function testToArrayOmitsIncludeKeyWhenRelationNotLoaded(): void
    {
        $resource = new class(new User(['name' => 'Test'])) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $request = Request::create('/', 'GET', ['include' => 'organization']);
        $array = $resource->toArray($request);

        $this->assertArrayNotHasKey('organization', $array);
    }

    public function testToArrayIncludesRelationResourceWhenRelationIsLoaded(): void
    {
        $orgResourceClass = get_class(new class(null) extends BaseResource {});

        $user = new User(['name' => 'Test']);
        $user->setRelation('organization', new Organization(['name' => 'Test Org']));

        $resource = new class($user, $orgResourceClass) extends BaseResource
        {
            public function __construct(mixed $user, string $orgResourceClass)
            {
                parent::__construct($user);
                $this->allowedIncludes = ['organization' => $orgResourceClass];
            }
        };

        $request = Request::create('/', 'GET', ['include' => 'organization']);
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('organization', $array);
        $this->assertInstanceOf(BaseResource::class, $array['organization']);
    }
}
