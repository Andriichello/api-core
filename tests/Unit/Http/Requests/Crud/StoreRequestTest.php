<?php

namespace Core\Tests\Unit\Http\Requests\Crud;

use Core\Http\Requests\Crud\StoreRequest;
use Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class StoreRequestTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Requests\Crud
 */
class StoreRequestTest extends TestCase
{
    /**
     * Make a JSON request with the given body.
     *
     * @param string $body
     *
     * @return Request
     */
    private function jsonRequest(string $body): Request
    {
        return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }

    /**
     * Get the class of a store request that takes a `name` and fills in a
     * missing `organization_id`.
     *
     * @return class-string<StoreRequest>
     */
    private function requestClass(): string
    {
        return get_class(new class extends StoreRequest
        {
            public function rules(): array
            {
                return ['name' => ['sometimes', 'string'], 'organization_id' => ['integer']];
            }

            protected function defaults(): array
            {
                return ['organization_id' => 1];
            }
        });
    }

    /**
     * Test that a JSON object body is taken, with the defaults filled in.
     *
     * @return void
     */
    public function testObjectBodyIsTaken(): void
    {
        $request = $this->requestClass()::createFrom($this->jsonRequest('{"name": "John"}'));
        $request->validateResolved();

        $this->assertEquals(['name' => 'John', 'organization_id' => 1], $request->validated());
    }

    /**
     * Test that a JSON list body answers with 422, even though the defaults merged into it give it a key, which is not
     * a number.
     *
     * @return void
     */
    public function testListBodyAnswers422EvenWithDefaults(): void
    {
        // the defaults merged into it give the body a key that is not a number
        $request = $this->requestClass()::createFrom($this->jsonRequest('[{"name": "John"}]'));

        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body', $e->errors());

            return;
        }

        $this->fail('The request did not answer with 422.');
    }

    /**
     * Test that the shape of a form body is not checked, so a list in it is only left out of the validated data.
     *
     * @return void
     */
    public function testFormBodyIsNotChecked(): void
    {
        $request = $this->requestClass()::createFrom(Request::create('/', 'POST', ['John']));
        $request->validateResolved();

        $this->assertEquals(['organization_id' => 1], $request->validated());
    }

    /**
     * Test that a request, which overrides the check of the body, takes a JSON list body.
     *
     * @return void
     */
    public function testEnsureObjectBodyCanBeOverridden(): void
    {
        $class = get_class(new class extends StoreRequest
        {
            protected function ensureObjectBody(): void
            {
                //
            }
        });

        $request = $class::createFrom($this->jsonRequest('[{"name": "John"}]'));
        $request->validateResolved();

        $this->assertEquals([], $request->validated());
    }
}
