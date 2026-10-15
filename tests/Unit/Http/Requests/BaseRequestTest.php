<?php

namespace Core\Tests\Unit\Http\Requests;

use Core\Helpers\CurrentOrganization;
use Core\Http\Requests\BaseRequest;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class BaseRequestTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Requests
 */
class BaseRequestTest extends TestCase
{
    /**
     * Test that defaults() method properly sets default values for missing fields.
     *
     * @return void
     */
    public function testDefaultsAreAppliedWhenFieldsAreMissing(): void
    {
        $request = new class extends BaseRequest
        {
            protected function defaults(): array
            {
                return [
                    'status' => 'active',
                    'priority' => 'normal',
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST', ['name' => 'Test']);
        $formRequest = $request::createFrom($laravelRequest);

        $this->assertEquals('Test', $formRequest->input('name'));
        $this->assertEquals('active', $formRequest->input('status'));
        $this->assertEquals('normal', $formRequest->input('priority'));
    }

    /**
     * Test that defaults() does not override existing values.
     *
     * @return void
     */
    public function testDefaultsDoNotOverrideExistingValues(): void
    {
        $request = new class extends BaseRequest
        {
            protected function defaults(): array
            {
                return [
                    'status' => 'active',
                    'priority' => 'normal',
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST', [
            'name' => 'Test',
            'status' => 'inactive',
        ]);
        $formRequest = $request::createFrom($laravelRequest);

        $this->assertEquals('Test', $formRequest->input('name'));
        $this->assertEquals('inactive', $formRequest->input('status'));
        $this->assertEquals('normal', $formRequest->input('priority'));
    }

    /**
     * Test that overrides() method properly overrides all values.
     *
     * @return void
     */
    public function testOverridesReplaceExistingValues(): void
    {
        $request = new class extends BaseRequest
        {
            public function rules(): array
            {
                return [
                    'status' => 'required|string',
                    'user_id' => 'required|integer',
                ];
            }

            protected function overrides(): array
            {
                return [
                    'status' => 'approved',
                    'user_id' => 99,
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST', [
            'name' => 'Test',
            'status' => 'pending',
            'user_id' => 1,
        ]);
        $formRequest = $request::createFrom($laravelRequest);

        $this->assertEquals('Test', $formRequest->input('name'));
        $this->assertEquals('approved', $formRequest->input('status'));
        $this->assertEquals(99, $formRequest->input('user_id'));
    }

    /**
     * Test that both defaults() and overrides() work together.
     *
     * @return void
     */
    public function testDefaultsAndOverridesWorkTogether(): void
    {
        $request = new class extends BaseRequest
        {
            protected function defaults(): array
            {
                return [
                    'status' => 'draft',
                    'priority' => 'low',
                ];
            }

            protected function overrides(): array
            {
                return [
                    'reviewed' => true,
                    'reviewer_id' => 42,
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST', [
            'name' => 'Test',
            'priority' => 'high',
        ]);
        $formRequest = $request::createFrom($laravelRequest);

        $this->assertEquals('Test', $formRequest->input('name'));
        $this->assertEquals('draft', $formRequest->input('status')); // from defaults
        $this->assertEquals('high', $formRequest->input('priority')); // original, not overridden
        $this->assertEquals(true, $formRequest->input('reviewed')); // from overrides
        $this->assertEquals(42, $formRequest->input('reviewer_id')); // from overrides
    }

    /**
     * Test createFrom() with the custom data array applies defaults and overrides.
     *
     * @return void
     */
    public function testCreateFromWithDataAppliesDefaultsAndOverrides(): void
    {
        $request = new class extends BaseRequest
        {
            protected function defaults(): array
            {
                return [
                    'status' => 'active',
                ];
            }

            protected function overrides(): array
            {
                return [
                    'user_id' => 100,
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST');
        $formRequest = $request::createFrom($laravelRequest, null, [
            'name' => 'Custom',
            'user_id' => 1,
        ]);

        $this->assertEquals('Custom', $formRequest->input('name'));
        $this->assertEquals('active', $formRequest->input('status')); // from defaults
        $this->assertEquals(100, $formRequest->input('user_id')); // from overrides, not the provided 1
    }

    /**
     * Test validation works with defaults and overrides.
     *
     * @return void
     */
    public function testValidationWorksWithDefaultsAndOverrides(): void
    {
        $request = new class extends BaseRequest
        {
            public function rules(): array
            {
                return [
                    'name' => 'required|string',
                    'status' => 'required|string|in:active,inactive',
                ];
            }

            protected function defaults(): array
            {
                return [
                    'status' => 'active',
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST', ['name' => 'Test']);
        $formRequest = $request::createFrom($laravelRequest);

        $validated = $formRequest->validated();

        $this->assertEquals('Test', $validated['name']);
        $this->assertEquals('active', $validated['status']);
    }

    /**
     * Test that the input() method returns values with defaults and overrides applied BEFORE validation.
     * This is the key test - defaults/overrides should be in the request immediately after createFrom.
     *
     * @return void
     */
    public function testInputMethodReturnsProcessedValuesBeforeValidation(): void
    {
        $request = new class extends BaseRequest
        {
            protected function defaults(): array
            {
                return [
                    'status' => 'active',
                ];
            }

            protected function overrides(): array
            {
                return [
                    'processed' => true,
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST', ['name' => 'Test']);
        $formRequest = $request::createFrom($laravelRequest);

        // IMPORTANT: Don't call validated() first - check if the data is there immediately
        // The validator was set in createFrom via setValidator(), so defaults/overrides should be applied
        $this->assertEquals('Test', $formRequest->input('name'));
        $this->assertEquals('active', $formRequest->input('status'));
        $this->assertEquals(true, $formRequest->input('processed'));
    }

    /**
     * Test all() method returns all inputs including defaults and overrides.
     *
     * @return void
     */
    public function testAllMethodReturnsAllInputsWithDefaultsAndOverrides(): void
    {
        $request = new class extends BaseRequest
        {
            protected function defaults(): array
            {
                return [
                    'status' => 'active',
                    'priority' => 'medium',
                ];
            }

            protected function overrides(): array
            {
                return [
                    'approved' => true,
                ];
            }
        };

        $laravelRequest = Request::create('/test', 'POST', ['name' => 'Test']);

        $formRequest = $request::createFrom($laravelRequest);

        $all = $formRequest->all();

        $this->assertArrayHasKey('name', $all);
        $this->assertArrayHasKey('status', $all);
        $this->assertArrayHasKey('priority', $all);
        $this->assertArrayHasKey('approved', $all);
        $this->assertEquals('Test', $all['name']);
        $this->assertEquals('active', $all['status']);
        $this->assertEquals('medium', $all['priority']);
        $this->assertEquals(true, $all['approved']);
    }

    /**
     * Test that validated() reuses the validation that ran, rather than
     * running every rule again, database rules included.
     *
     * @return void
     */
    public function testValidatedDoesNotValidateAgain(): void
    {
        $request = new class extends BaseRequest
        {
            public static int $runs = 0;

            public function rules(): array
            {
                return [
                    'name' => [fn () => self::$runs++],
                ];
            }
        };

        $formRequest = $request::createFrom(Request::create('/test', 'POST', ['name' => 'Test']));
        $formRequest->validateResolved();

        $this->assertEquals(['name' => 'Test'], $formRequest->validated());
        $this->assertEquals('Test', $formRequest->validated('name'));
        $this->assertEquals(1, $request::$runs);
    }

    /**
     * Test that validated() validates a request that was not validated
     * yet, and refuses one that is invalid.
     *
     * @return void
     */
    public function testValidatedValidatesWhenNotValidatedYet(): void
    {
        $request = new class extends BaseRequest
        {
            public function rules(): array
            {
                return [
                    'name' => 'required|string',
                ];
            }
        };

        $formRequest = $request::createFrom(Request::create('/test', 'POST', ['name' => 'Test']));
        $this->assertEquals(['name' => 'Test'], $formRequest->validated());

        $this->expectException(ValidationException::class);

        $request::createFrom(Request::create('/test', 'POST', []))->validated();
    }

    /**
     * Test that the request acts in the current organization, or in the user's one, but never in the one of its
     * input.
     *
     * @return void
     */
    public function testOrgIdIsTheCurrentOrganizationOrTheUsersOne(): void
    {
        $request = new class extends BaseRequest {};
        $request = $request::createFrom(Request::create('/test', 'POST', ['organization_id' => 99]));

        $this->assertNull($request->orgId());

        $request->setUserResolver(fn () => User::factory()->make(['organization_id' => 5]));
        $this->assertSame(5, $request->orgId());

        CurrentOrganization::run(7, fn () => $this->assertSame(7, $request->orgId()));
    }
}
