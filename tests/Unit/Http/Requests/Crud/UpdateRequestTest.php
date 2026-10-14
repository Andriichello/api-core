<?php

namespace Core\Tests\Unit\Http\Requests\Crud;

use Core\Http\Requests\Crud\UpdateRequest;
use Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class UpdateRequestTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Requests\Crud
 */
class UpdateRequestTest extends TestCase
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
        return Request::create('/', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }

    /**
     * Get the class of an update request that takes a `name` and an `email`,
     * lower-cases a `name` it is given and fills in a missing `email`.
     *
     * @return class-string<UpdateRequest>
     */
    private function requestClass(): string
    {
        return get_class(new class extends UpdateRequest
        {
            public function rules(): array
            {
                return ['name' => ['sometimes', 'string'], 'email' => ['sometimes', 'string']];
            }

            protected function defaults(): array
            {
                return ['email' => 'john@example.com'];
            }

            protected function overrides(): array
            {
                $name = $this->input('name');

                return is_string($name) ? ['name' => strtolower($name)] : [];
            }
        });
    }

    /**
     * Get the validation errors that validating the given request throws.
     *
     * @param UpdateRequest $request
     *
     * @return array
     */
    private function errorsOf(UpdateRequest $request): array
    {
        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('The request did not answer with 422.');
    }

    public function testSentFieldIsEnough(): void
    {
        $request = $this->requestClass()::createFrom($this->jsonRequest('{"email": "jane@example.com"}'));
        $request->validateResolved();

        $this->assertEquals(['email'], $request->sentKeys());
        $this->assertEquals(['email' => 'jane@example.com'], $request->validated());
    }

    public function testSentFieldRewrittenByOverridesCounts(): void
    {
        $request = $this->requestClass()::createFrom($this->jsonRequest('{"name": "JOHN"}'));
        $request->validateResolved();

        $this->assertEquals(['name' => 'john', 'email' => 'john@example.com'], $request->validated());
    }

    public function testFieldWithOnlyNestedRulesCounts(): void
    {
        $class = get_class(new class extends UpdateRequest
        {
            public function rules(): array
            {
                return ['points.*.lat' => ['numeric']];
            }
        });

        $request = $class::createFrom($this->jsonRequest('{"points": [{"lat": 1}]}'));
        $request->validateResolved();

        $this->assertEquals(['points' => [['lat' => 1]]], $request->validated());
    }

    public function testIdIsNoField(): void
    {
        $request = $this->requestClass()::createFrom($this->jsonRequest('{"id": 5}'));

        $this->assertEquals(['The request has no fields to update.'], $this->errorsOf($request)['fields']);
    }

    public function testEmptyBodyHasNoFields(): void
    {
        $request = $this->requestClass()::createFrom($this->jsonRequest('{}'));

        $this->assertEquals(['The request has no fields to update.'], $this->errorsOf($request)['fields']);
    }

    public function testUnknownFieldsAreNoFields(): void
    {
        $request = $this->requestClass()::createFrom($this->jsonRequest('{"nickname": "Johnny", "age": 30}'));

        $this->assertEquals(
            ['None of the given fields can be updated: nickname, age.'],
            $this->errorsOf($request)['fields']
        );
    }

    public function testListBodyAnswers422(): void
    {
        $request = $this->requestClass()::createFrom($this->jsonRequest('[{"name": "John"}]'));

        $this->assertArrayHasKey('body', $this->errorsOf($request));
    }

    public function testKeysAsSentOutliveCreateFrom(): void
    {
        // made from the data, then made again from that request, as a controller does
        $first = $this->requestClass()::createFrom($this->jsonRequest('{}'), null, ['id' => 5]);
        $second = $this->requestClass()::createFrom($first);

        $this->assertEquals('john@example.com', $second->input('email'));
        $this->assertEquals(['id'], $second->sentKeys());
        $this->assertEquals(['The request has no fields to update.'], $this->errorsOf($second)['fields']);
    }

    public function testEnsureFieldsToUpdateCanBeOverridden(): void
    {
        $class = get_class(new class extends UpdateRequest
        {
            protected function ensureFieldsToUpdate(): void
            {
                //
            }
        });

        $request = $class::createFrom($this->jsonRequest('{}'));
        $request->validateResolved();

        $this->assertEquals([], $request->validated());
    }
}
