<?php

namespace Core\Tests\Unit\Resources;

use Core\Http\Resources\BaseResource;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Exception;
use Illuminate\Http\Request;

/**
 * Class BaseResourceAppendsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Resources
 */
class BaseResourceAppendsTest extends TestCase
{
    public function testListOfAllowedAppendsReturnsAllowedAppends(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $this->assertSame(['timezone', 'locale'], $resource->listOfAllowedAppends());
    }

    public function testHasAllowedAppendsReturnsTrueForAllowedAppend(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $this->assertTrue($resource->hasAllowedAppends('timezone'));
    }

    public function testHasAllowedAppendsReturnsFalseForDisallowedAppend(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $this->assertFalse($resource->hasAllowedAppends('password'));
    }

    public function testHasAllowedAppendsReturnsTrueForMultipleAllowedAppends(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $this->assertTrue($resource->hasAllowedAppends(['timezone', 'locale']));
    }

    public function testHasAllowedAppendsReturnsFalseWhenOneAppendIsDisallowed(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $this->assertFalse($resource->hasAllowedAppends(['timezone', 'locale']));
    }

    public function testGetAllowedAppendReturnsAttributeValueFromModel(): void
    {
        $user = new User;
        $user->timezone = 'UTC';

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $this->assertSame('UTC', $resource->getAllowedAppend('timezone'));
    }

    public function testGetAllowedAppendThrowsExceptionForDisallowedAppend(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'password' append is not allowed");

        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $resource->getAllowedAppend('password');
    }

    public function testGetAllowedAppendsWithNullReturnsAllAllowedAppendsAsKeyValueMap(): void
    {
        $user = new User;
        $user->timezone = 'UTC';
        $user->locale = 'en';

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $result = $resource->getAllowedAppends();

        $this->assertSame('UTC', $result['timezone']);
        $this->assertSame('en', $result['locale']);
    }

    public function testGetAllowedAppendsWithSpecificSubsetReturnsOnlyThose(): void
    {
        $user = new User;
        $user->timezone = 'UTC';
        $user->locale = 'en';

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $result = $resource->getAllowedAppends(['timezone']);

        $this->assertArrayHasKey('timezone', $result);
        $this->assertArrayNotHasKey('locale', $result);
    }

    public function testGetRequestedAppendsReturnsAppendsFromRequest(): void
    {
        $user = new User;
        $user->timezone = 'America/New_York';

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $request = Request::create('/', 'GET', ['append' => 'timezone']);
        $result = $resource->getRequestedAppends($request);

        $this->assertSame('America/New_York', $result['timezone']);
    }

    public function testGetRequestedAppendsThrowsExceptionForDisallowedAppend(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'password' append is not allowed");

        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $resource->getRequestedAppends(Request::create('/', 'GET', ['append' => 'password']));
    }

    public function testPassedAppendsOverrideRequestAppends(): void
    {
        $user = new User;
        $user->timezone = 'UTC';

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $resource->setPassedAppends(['timezone']);

        $request = Request::create('/', 'GET', ['append' => 'locale']);
        $result = $resource->getRequestedAppends($request);

        $this->assertArrayHasKey('timezone', $result);
        $this->assertArrayNotHasKey('locale', $result);
    }

    public function testPassedAppendsNullFallsBackToRequestAppends(): void
    {
        $user = new User;
        $user->locale = 'en';

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $resource->setPassedAppends(null);

        $request = Request::create('/', 'GET', ['append' => 'locale']);
        $result = $resource->getRequestedAppends($request);

        $this->assertArrayHasKey('locale', $result);
        $this->assertArrayNotHasKey('timezone', $result);
    }

    public function testToArrayAddsAppendedAttributeNotNormallyInOutput(): void
    {
        // A freshly created User with no attributes set returns empty array from toArray().
        // Requesting 'timezone' as an append should add it to the output.
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $request = Request::create('/', 'GET', ['append' => 'timezone']);
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('timezone', $array);
    }

    public function testToArrayDoesNotAddAppendWhenNotRequested(): void
    {
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $array = $resource->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('timezone', $array);
    }

    public function testToArrayAppendsCorrectValueFromModel(): void
    {
        $user = new User;
        $user->timezone = 'Europe/London';

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $request = Request::create('/', 'GET', ['append' => 'timezone']);
        $array = $resource->toArray($request);

        $this->assertSame('Europe/London', $array['timezone']);
    }

    public function testToArrayPropagatesNestedAppendToIncludedResource(): void
    {
        $orgResourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['name'];
        });

        $org = new Organization;
        $org->name = 'Acme Corp';

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

        // 'organization.name' should propagate 'name' as passedAppend to the organization resource
        $request = Request::create('/', 'GET', ['include' => 'organization', 'append' => 'organization.name']);
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('organization', $array);

        /** @var BaseResource $orgResource */
        $orgResource = $array['organization'];
        $this->assertSame(['name'], $orgResource->getPassedAppends());
    }
}
