<?php

namespace Core\Http\Requests\Crud;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Http\Requests\Traits\WithObjectBody;
use Core\Http\Requests\Traits\WithTarget;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Class UpdateRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Crud
 */
class UpdateRequest extends CrudRequest implements WithTargetInterface
{
    use WithObjectBody;
    use WithTarget;

    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = 'update';

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     * @throws ValidationException
     */
    protected function passedValidation(): void
    {
        $this->ensureObjectBody();
        $this->ensureFieldsToUpdate();
    }

    /**
     * Refuse an update in which the client sent none of the fields the rules
     * know, such as `{}` or only unknown fields: applying it would change
     * nothing, yet answer `200 Updated`. Values that `defaults()` or
     * `overrides()` filled in do not count, and neither does `id`, which
     * names the record rather than a field of it.
     *
     * Looks each sent key up among the rules and stops at the first one
     * found, so it costs no more for a large body. Override it to allow an
     * empty update, count other fields or word the error differently.
     *
     * @return void
     * @throws ValidationException
     */
    protected function ensureFieldsToUpdate(): void
    {
        /** @var Validator $validator */
        $validator = $this->validator;

        $sent = array_values(array_diff($this->sentKeys(), ['id']));
        $rules = $validator->getRules();

        foreach ($sent as $key) {
            if (isset($rules[$key])) {
                return;
            }
        }

        // a field validated only through nested rules, such as `points.*.lat`
        $lookup = array_flip($sent);

        foreach (array_keys($rules) as $rule) {
            if (str_contains($rule, '.') && isset($lookup[strstr($rule, '.', true)])) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'fields' => [
                empty($sent)
                    ? 'The request has no fields to update.'
                    : 'None of the given fields can be updated: ' . implode(', ', $sent) . '.',
            ],
        ]);
    }
}
