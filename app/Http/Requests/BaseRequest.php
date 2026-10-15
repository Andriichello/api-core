<?php

namespace Core\Http\Requests;

use Core\Helpers\CurrentOrganization;
use Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Class BaseRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests
 */
abstract class BaseRequest extends FormRequest
{
    /**
     * Message, which should be displayed on a failed authorization attempt.
     *
     * @var string
     */
    protected string $message = 'You are not authorized to perform this request.';

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException($this->message);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Get form request fields' default values.
     *
     * @return array
     */
    protected function defaults(): array
    {
        return [];
    }

    /**
     * Get form request fields' override values.
     *
     * @return array
     */
    protected function overrides(): array
    {
        return [];
    }

    /**
     * Get id of the organization, in which the request acts: the current one
     * (see ResolveOrganization), or the signed-in user's one, when the route
     * doesn't resolve it. It is never taken from the request's input.
     *
     * @return int|null
     */
    public function orgId(): ?int
    {
        /** @var User|null $user */
        $user = $this->user();

        return CurrentOrganization::id() ?? $user?->orgId();
    }

    /**
     * Get the validator instance for the request.
     *
     * @return Validator
     */
    protected function getValidatorInstance(): Validator
    {
        $defaults = $this->defaults();

        if (!empty($defaults)) {
            $this->mergeIfMissing($defaults);
        }

        $overrides = $this->overrides();

        if (!empty($overrides)) {
            $this->merge($overrides);
        }

        return parent::getValidatorInstance();
    }

    /**
     * Clear all parameters of the request.
     *
     * @return static
     */
    public function clear(): static
    {
        foreach ($this->request->keys() as $key) {
            $this->request->remove($key);
        }

        // Might need to also clear the query
        // foreach ($this->query->keys() as $key) {
        //     $this->query->remove($key);
        // }

        foreach ($this->getInputSource()->keys() as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Create a new request instance from the given Laravel request.
     *
     * @param Request $from
     * @param Request|null $to
     * @param array|null $data
     *
     * @return static
     */
    public static function createFrom(Request $from, $to = null, ?array $data = null): static
    {
        /** @var static $obj */
        $obj = parent::createFrom($from, $to);

        if ($data !== null) {
            // 1. Replace the standard parameter bag
            $obj->request->replace($data);
            // 2. IMPORTANT: Replace the JSON parameter bag.
            // Laravel's FormRequest often uses this source for JSON payloads.
            $obj->setJson(new InputBag($data));
            // 3. Update the raw content and metadata
            $obj->content = json_encode($data);
            $obj->headers->set('Content-Type', 'application/json');
        }

        $obj->setContainer(app());

        // 4. Force a fresh validator instance which applies defaults/overrides
        $obj->setValidator($obj->getValidatorInstance());

        // 5. IMPORTANT: After getValidatorInstance() has applied defaults/overrides via merge(),
        // we need to sync all input bags to ensure get() and all() methods return the modified data.
        // This is necessary when $data was provided, as the bags were replaced before defaults/overrides.
        if ($data !== null || !empty($obj->defaults()) || !empty($obj->overrides())) {
            $allData = $obj->all();
            $obj->request->replace($allData);
            $obj->setJson(new InputBag($allData));
            $obj->content = json_encode($allData);
        }

        $obj->setRedirector(app(Redirector::class));

        return $obj;
    }

    /**
     * Get the client user agent.
     *
     * @param mixed $default
     *
     * @return string|null
     */
    public function userAgent(mixed $default = 'NULL'): ?string
    {
        return Str::of(parent::userAgent() ?? $default)
            ->ascii()
            ->value();
    }
}
