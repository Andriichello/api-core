<?php

namespace Core\Http\Requests\Traits;

use Core\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Trait WithObjectBody.
 *
 * For requests whose body is an object of the fields to save. Keeps the
 * body's keys as the client sent them, before `defaults()` and `overrides()`
 * are merged into it, and refuses a JSON body that is not an object.
 *
 * @mixin BaseRequest
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Traits
 */
trait WithObjectBody
{
    /**
     * Top-level keys of the body as the client sent it, before `defaults()`
     * and `overrides()` were merged into it.
     *
     * @var array<int|string>|null
     */
    protected ?array $sentKeys = null;

    /**
     * Get the top-level keys of the body as the client sent it, before
     * `defaults()` and `overrides()` were merged into it.
     *
     * @return array<int|string>
     */
    public function sentKeys(): array
    {
        return $this->sentKeys ?? [];
    }

    /**
     * Get the validator instance for the request, keeping the body's keys
     * before `defaults()` and `overrides()` are merged into it.
     *
     * @return Validator
     */
    protected function getValidatorInstance(): Validator
    {
        $this->sentKeys ??= $this->getInputSource()->keys();

        return parent::getValidatorInstance();
    }

    /**
     * Create a new request instance from the given Laravel request. A request
     * made from one of the same kind keeps its keys as sent: its body already
     * holds the values that `defaults()` and `overrides()` merged into it.
     *
     * @param Request $from
     * @param Request|null $to
     * @param array|null $data
     *
     * @return static
     */
    public static function createFrom(Request $from, $to = null, ?array $data = null): static
    {
        $request = parent::createFrom($from, $to, $data);

        if ($data === null && $from instanceof self && $from->sentKeys !== null) {
            $request->sentKeys = $from->sentKeys;
        }

        return $request;
    }

    /**
     * Refuse a JSON body that is not an object. A list or a single value
     * cannot be mapped onto a record's fields, so saving it would validate
     * nothing and save nothing. An empty list counts as an empty object,
     * since that is how PHP's and R's JSON encoders write an empty map.
     *
     * Reads the keys kept from the body Laravel has already decoded, so a
     * large body is not parsed again. Override it to accept another shape.
     *
     * @return void
     * @throws ValidationException
     */
    protected function ensureObjectBody(): void
    {
        $keys = $this->sentKeys();

        // a list, or a single value, decodes to the keys 0, 1, 2…
        if (!$this->isJson() || empty($keys) || $keys !== range(0, count($keys) - 1)) {
            return;
        }

        throw ValidationException::withMessages([
            'body' => ['The body must be a JSON object of the fields to save, such as {"name": "…"}.'],
        ]);
    }
}
