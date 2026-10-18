<?php

namespace Core\Tests\Unit\Resources;

use Core\Http\Resources\BaseResource;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $this->expectException(ValidationException::class);
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
        $this->expectException(ValidationException::class);
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

    /**
     * Test that an append nested under a relation, which is included without a resource, is refused, as it allows none.
     *
     * @return void
     */
    public function testToArrayRefusesNestedAppendUnderIncludeWithoutResource(): void
    {
        $user = new User;
        $user->setRelation('organization', new Organization(['name' => 'Acme Corp']));

        $resource = new class($user) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];
        };

        $request = Request::create('/', 'GET', ['include' => 'organization', 'append' => 'organization.name']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("The 'name' append is not allowed. No appends are allowed.");

        $resource->toArray($request)['organization']->toArray($request);
    }

    public function testNotAllowedAppendAnswers422NamingTheAllowedOnes(): void
    {
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedAppends = ['email'];
        };

        try {
            $resource->getAllowedAppend('password');
        } catch (ValidationException $exception) {
            $this->assertEquals(
                ["The 'password' append is not allowed. Allowed appends: email."],
                $exception->errors()['append']
            );

            return;
        }

        $this->fail('The append did not answer with 422.');
    }

    public function testNotAllowedAppendSaysWhenNoneAreAllowed(): void
    {
        $resource = new class(new User) extends BaseResource {};

        $this->assertEquals(
            ["The 'password' append is not allowed. No appends are allowed."],
            $resource->notAllowedAppend('password')->errors()['append']
        );
    }

    public function testAppendParameterThatIsNotAStringAnswers422(): void
    {
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedAppends = ['email'];
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The append parameter must be a comma-separated list.');

        $resource->toArray(Request::create('/', 'GET', ['append' => ['email']]));
    }
}
