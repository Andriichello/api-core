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
    /**
     * Test that the list of allowed appends is the one, which the resource declares.
     *
     * @return void
     */
    public function testListOfAllowedAppendsReturnsAllowedAppends(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $this->assertSame(['timezone', 'locale'], $resource->listOfAllowedAppends());
    }

    /**
     * Test that an append, which the resource allows, is allowed.
     *
     * @return void
     */
    public function testHasAllowedAppendsReturnsTrueForAllowedAppend(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $this->assertTrue($resource->hasAllowedAppends('timezone'));
    }

    /**
     * Test that an append, which the resource does not list, is not allowed.
     *
     * @return void
     */
    public function testHasAllowedAppendsReturnsFalseForDisallowedAppend(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $this->assertFalse($resource->hasAllowedAppends('password'));
    }

    /**
     * Test that several appends are allowed, when the resource allows each of them.
     *
     * @return void
     */
    public function testHasAllowedAppendsReturnsTrueForMultipleAllowedAppends(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone', 'locale'];
        };

        $this->assertTrue($resource->hasAllowedAppends(['timezone', 'locale']));
    }

    /**
     * Test that several appends are not allowed, when one of them is not allowed.
     *
     * @return void
     */
    public function testHasAllowedAppendsReturnsFalseWhenOneAppendIsDisallowed(): void
    {
        $resource = new class(null) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $this->assertFalse($resource->hasAllowedAppends(['timezone', 'locale']));
    }

    /**
     * Test that an allowed append gives the value of the attribute of the model.
     *
     * @return void
     */
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

    /**
     * Test that getting an append, which is not allowed, throws a validation exception.
     *
     * @return void
     */
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

    /**
     * Test that getting the allowed appends without a list gives the values of all of them, keyed by their names.
     *
     * @return void
     */
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

    /**
     * Test that getting the allowed appends with a list gives only the ones on it.
     *
     * @return void
     */
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

    /**
     * Test that the appends, which the `append` parameter of the request names, are given with their values.
     *
     * @return void
     */
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

    /**
     * Test that a request for an append, which is not allowed, throws a validation exception.
     *
     * @return void
     */
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

    /**
     * Test that the appends passed to the resource are used instead of the ones, which the request asks for.
     *
     * @return void
     */
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

    /**
     * Test that the appends, which the request asks for, are used, when null is passed to the resource.
     *
     * @return void
     */
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

    /**
     * Test that a requested append is added to the output, even for a model, which has no attributes set.
     *
     * @return void
     */
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

    /**
     * Test that an allowed append is left out of the output, when the request does not ask for it.
     *
     * @return void
     */
    public function testToArrayDoesNotAddAppendWhenNotRequested(): void
    {
        $resource = new class(new User) extends BaseResource
        {
            protected array $allowedAppends = ['timezone'];
        };

        $array = $resource->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('timezone', $array);
    }

    /**
     * Test that a requested append is output with the value of the attribute of the model.
     *
     * @return void
     */
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

    /**
     * Test that an append nested under an include is passed to the resource of the included relation, without the
     * prefix.
     *
     * @return void
     */
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

    /**
     * Test that an append, which is not allowed, answers with 422, whose message names the allowed appends.
     *
     * @return void
     */
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

    /**
     * Test that the message of an append, which is not allowed, says that no appends are allowed, when the resource
     * allows none.
     *
     * @return void
     */
    public function testNotAllowedAppendSaysWhenNoneAreAllowed(): void
    {
        $resource = new class(new User) extends BaseResource {};

        $this->assertEquals(
            ["The 'password' append is not allowed. No appends are allowed."],
            $resource->notAllowedAppend('password')->errors()['append']
        );
    }

    /**
     * Test that an `append` parameter, which is not a string, answers with 422, asking for a comma-separated list.
     *
     * @return void
     */
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
