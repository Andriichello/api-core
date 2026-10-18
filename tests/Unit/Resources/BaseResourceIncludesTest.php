<?php

namespace Core\Tests\Unit\Resources;

use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Http\Resources\UserCollection;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Validation\ValidationException;

/**
 * Class BaseResourceIncludesTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Resources
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
class BaseResourceIncludesTest extends TestCase
{
    /**
     * Test that the list of allowed includes gives the names, which the resource lists without resources.
     *
     * @return void
     */
    public function testListOfAllowedIncludesReturnsNamesFromIntKeyedArray(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization', 'roles'];
        };

        $this->assertSame(['organization', 'roles'], $resource->listOfAllowedIncludes());
    }

    /**
     * Test that the list of allowed includes gives the names, which the resource maps to their resources.
     *
     * @return void
     */
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

    /**
     * Test that an include, which the resource allows, is allowed.
     *
     * @return void
     */
    public function testHasAllowedIncludesReturnsTrueForAllowedInclude(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertTrue($resource->hasAllowedIncludes('organization'));
    }

    /**
     * Test that an include, which the resource does not list, is not allowed.
     *
     * @return void
     */
    public function testHasAllowedIncludesReturnsFalseForDisallowedInclude(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertFalse($resource->hasAllowedIncludes('roles'));
    }

    /**
     * Test that nothing can be nested under an include, which has no resource.
     *
     * @return void
     */
    public function testHasAllowedIncludesReturnsFalseForNestedIncludeUnderIncludeWithoutResource(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertFalse($resource->hasAllowedIncludes('organization.users'));
        $this->assertFalse($resource->hasAllowedIncludes(['organization', 'organization.users']));
    }

    /**
     * Test that a nested include is checked against the resource of the include it is under.
     *
     * @return void
     */
    public function testHasAllowedIncludesChecksNestedIncludeAgainstResourceOfItsInclude(): void
    {
        $orgClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['users' => UserCollection::class];
        });

        $resource = new class($orgClass) extends BaseResource
        {
            public function __construct(string $orgClass)
            {
                parent::__construct(null);
                $this->allowedIncludes = ['organization' => $orgClass];
            }
        };

        $this->assertTrue($resource->hasAllowedIncludes(['organization', 'organization.users']));
        $this->assertFalse($resource->hasAllowedIncludes('organization.roles'));
        $this->assertFalse($resource->hasAllowedIncludes('organization.users.roles'));
        $this->assertFalse($resource->hasAllowedIncludes('organization.'));
    }

    /**
     * Test that a nested include under a collection is checked against the resource of its items.
     *
     * @return void
     */
    public function testHasAllowedIncludesChecksNestedIncludeAgainstResourceOfCollectionItems(): void
    {
        $userClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        });

        $collection = new class([]) extends BaseCollection
        {
            /**
             * The resource of the items, which the test sets.
             *
             * @var string|null
             */
            public static ?string $items = null;

            /**
             * Get the resource that this resource collects.
             *
             * @return string|null
             */
            protected function collects(): ?string
            {
                return self::$items;
            }
        };

        $collection::$items = $userClass;

        $resource = new class($collection::class) extends BaseResource
        {
            public function __construct(string $collectionClass)
            {
                parent::__construct(null);
                $this->allowedIncludes = ['users' => $collectionClass];
            }
        };

        $this->assertTrue($resource->hasAllowedIncludes('users.organization'));
        $this->assertFalse($resource->hasAllowedIncludes('users.roles'));
    }

    /**
     * Test that a nested include is not allowed, when the include it is under is not allowed.
     *
     * @return void
     */
    public function testHasAllowedIncludesReturnsFalseForNestedIncludeWhenParentIsNotAllowed(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertFalse($resource->hasAllowedIncludes('roles.permissions'));
    }

    /**
     * Test that an include, which the resource lists without a resource, has no resource.
     *
     * @return void
     */
    public function testResourceOfAllowedIncludeReturnsNullForIntKeyedInclude(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertNull($resource->resourceOfAllowedInclude('organization'));
    }

    /**
     * Test that an include, which the resource maps to a resource, gives the class of that resource.
     *
     * @return void
     */
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

    /**
     * Test that an allowed include gives a missing value, when its relation is not loaded.
     *
     * @return void
     */
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

    /**
     * Test that a relation included without a resource is given as a base resource, with its attributes only.
     *
     * @return void
     */
    public function testGetAllowedIncludeWrapsRelationWithoutResourceClassInBaseResource(): void
    {
        $org = new Organization(['name' => 'Test Org']);
        $org->setRelation('users', new Collection([new User(['name' => 'Other'])]));

        $user = new User;
        $user->setRelation('organization', $org);

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $result = $resource->getAllowedInclude('organization');

        $this->assertInstanceOf(BaseResource::class, $result);
        $this->assertSame($org, $result->resource);

        $array = $result->toArray(Request::create('/', 'GET', ['include' => 'organization']));

        $this->assertSame('Test Org', $array['name']);
        $this->assertArrayNotHasKey('users', $array);
    }

    /**
     * Test that a to-many relation included without a resource is given as a collection of base resources.
     *
     * @return void
     */
    public function testGetAllowedIncludeWrapsToManyRelationWithoutResourceClassInBaseCollection(): void
    {
        $member = new User(['name' => 'Member']);
        $member->setRelation('roles', new Collection);

        $org = new Organization;
        $org->setRelation('users', new Collection([$member]));

        $resource = new class($org) extends BaseResource
        {
            protected array $allowedIncludes = ['users'];
        };

        $result = $resource->getAllowedInclude('users');

        $this->assertInstanceOf(BaseCollection::class, $result);

        $items = $result->toArray(Request::create('/', 'GET', ['include' => 'users']));

        $this->assertCount(1, $items);
        $this->assertSame('Member', $items[0]['name']);
        $this->assertArrayNotHasKey('roles', $items[0]);
    }

    /**
     * Test that an included relation, which is loaded as null, is given as null.
     *
     * @return void
     */
    public function testGetAllowedIncludeReturnsNullForRelationLoadedAsNull(): void
    {
        $user = new User;
        $user->setRelation('organization', null);

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->assertNull($resource->getAllowedInclude('organization'));
    }

    /**
     * Test that a loaded relation is wrapped in the resource, which its include is mapped to.
     *
     * @return void
     */
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

    /**
     * Test that the includes nested under an include are passed to the resource of its relation, without the prefix.
     *
     * @return void
     */
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

    /**
     * Test that getting an include, which is not allowed, throws a validation exception.
     *
     * @return void
     */
    public function testGetAllowedIncludeThrowsExceptionForDisallowedInclude(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("'roles' include is not allowed");

        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $resource->getAllowedInclude('roles');
    }

    /**
     * Test that a requested include, whose relation is not loaded, is given as a missing value.
     *
     * @return void
     */
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

    /**
     * Test that a requested include, whose relation is loaded, is given as the resource of the relation.
     *
     * @return void
     */
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

    /**
     * Test that a request for an include, which is not allowed, throws a validation exception.
     *
     * @return void
     */
    public function testGetRequestedIncludesThrowsForDisallowedInclude(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("'roles' include is not allowed");

        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $resource->getRequestedIncludes(Request::create('/', 'GET', ['include' => 'roles']));
    }

    /**
     * Test that the includes passed to the resource are used instead of the ones, which the request asks for.
     *
     * @return void
     */
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

    /**
     * Test that a requested include is left out of the output, when its relation is not loaded.
     *
     * @return void
     */
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

    /**
     * Test that a requested include is output as the resource of its relation, when the relation is loaded.
     *
     * @return void
     */
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

    /**
     * Test that an include, which is not allowed, answers with 422, whose message names the allowed includes.
     *
     * @return void
     */
    public function testNotAllowedIncludeAnswers422NamingTheAllowedOnes(): void
    {
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        try {
            $resource->getAllowedInclude('roles');
        } catch (ValidationException $exception) {
            $this->assertEquals(
                ["The 'roles' include is not allowed. Allowed includes: organization."],
                $exception->errors()['include']
            );

            return;
        }

        $this->fail('The include did not answer with 422.');
    }

    /**
     * Test that the message of an include, which is not allowed, says that no includes are allowed, when the resource
     * allows none.
     *
     * @return void
     */
    public function testNotAllowedIncludeSaysWhenNoneAreAllowed(): void
    {
        $resource = new class(new User) extends BaseResource {};

        $this->assertEquals(
            ["The 'roles' include is not allowed. No includes are allowed."],
            $resource->notAllowedInclude('roles')->errors()['include']
        );
    }

    /**
     * Test that a nested include, which is not allowed, names the includes allowed where it stops being allowed.
     *
     * @return void
     */
    public function testNotAllowedIncludeNamesTheNestedIncludesThatAreAllowed(): void
    {
        $orgClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['users' => UserCollection::class];
        });

        $resource = new class($orgClass) extends BaseResource
        {
            public function __construct(string $orgClass)
            {
                parent::__construct(null);
                $this->allowedIncludes = ['organization' => $orgClass, 'roles'];
            }
        };

        $this->assertEquals(
            ["The 'organization.owner' include is not allowed. Allowed includes: organization.users."],
            $resource->notAllowedInclude('organization.owner')->errors()['include']
        );

        $this->assertEquals(
            [
                "The 'organization.users.roles' include is not allowed. "
                . "No includes are allowed under 'organization.users'.",
            ],
            $resource->notAllowedInclude('organization.users.roles')->errors()['include']
        );

        $this->assertEquals(
            ["The 'roles.permissions' include is not allowed. No includes are allowed under 'roles'."],
            $resource->notAllowedInclude('roles.permissions')->errors()['include']
        );

        $this->assertEquals(
            ["The 'teams.users' include is not allowed. Allowed includes: organization, roles."],
            $resource->notAllowedInclude('teams.users')->errors()['include']
        );
    }

    /**
     * Test that a model gives its visible attributes only, without the relations it loaded, unless they are requested.
     *
     * @return void
     */
    public function testToArrayLeavesOutLoadedRelationsThatAreNotRequested(): void
    {
        $user = new User(['name' => 'Test', 'password' => 'secret']);
        $user->setRelation('roles', new Collection);
        $user->setRelation('organization', new Organization(['name' => 'Test Org']));

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $array = $resource->toArray(Request::create('/'));

        $this->assertSame('Test', $array['name']);
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('roles', $array);
        $this->assertArrayNotHasKey('organization', $array);

        $array = $resource->toArray(Request::create('/', 'GET', ['include' => 'organization']));

        $this->assertArrayHasKey('organization', $array);
        $this->assertArrayNotHasKey('roles', $array);
    }

    /**
     * Test that rendering refuses an include nested under an include, which has no resource, even when it is loaded.
     *
     * @return void
     */
    public function testToArrayRefusesNestedIncludeUnderIncludeWithoutResource(): void
    {
        $organization = new Organization(['name' => 'Test Org']);
        $organization->setRelation('users', new Collection([new User(['name' => 'Other'])]));

        $user = new User(['name' => 'Test']);
        $user->setRelation('organization', $organization);

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("The 'organization.users' include is not allowed.");

        $resource->toArray(Request::create('/', 'GET', ['include' => 'organization.users']));
    }
}
