<?php

namespace Core\Http\Resources\Traits;

use Core\Helpers\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Trait AllowsAppends.
 *
 * Contains logic for getting the list of attributes,
 * which should be appended for given request.
 * Throws exception when asked append is not allowed.
 *
 * By default, no appends will be allowed.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources\Traits
 */
trait AllowsAppends
{
    /**
     * List of columns that are allowed as
     * appends on request.
     *
     * @var array<int, string>
     */
    protected array $allowedAppends = [];

    /**
     * List of columns that were passed from the parent
     * resource. Needed for nested appending.
     *
     * @var array<int, string>
     */
    protected ?array $passedAppends = null;

    /**
     * List of columns the request asks to append, parsed
     * once by the collection of the resource for all its
     * items. Used instead of parsing the request again,
     * when no appends were passed.
     *
     * @var array<int, string>|null
     */
    protected ?array $appendsOfRequest = null;

    /**
     * Get the list of columns that were passed from the parent
     * resource. Needed for nested appending.
     *
     * @return array|null
     */
    public function getPassedAppends(): ?array
    {
        return $this->passedAppends;
    }

    /**
     * Set the list of columns that were passed from the parent
     * resource. Needed for nested appending.
     *
     * @param array|null $appends
     *
     * @return $this
     */
    public function setPassedAppends(?array $appends): static
    {
        $this->passedAppends = $appends;

        return $this;
    }

    /**
     * Get the list of columns the request asks to append,
     * as the collection of the resource parsed it, or null.
     *
     * @return array|null
     */
    public function getAppendsOfRequest(): ?array
    {
        return $this->appendsOfRequest;
    }

    /**
     * Set the list of columns the request asks to append,
     * parsed once by the collection of the resource, so
     * that none of its items parses the request again.
     *
     * @param array|null $appends
     *
     * @return $this
     */
    public function setAppendsOfRequest(?array $appends): static
    {
        $this->appendsOfRequest = $appends;

        return $this;
    }

    /**
     * Get list of columns that are allowed as
     * appends on request.
     *
     * @return array<int, string>
     */
    public function listOfAllowedAppends(): array
    {
        return $this->allowedAppends ?? [];
    }

    /**
     * Determines if all given columns are allowed as
     * appends on request.
     *
     * @param string|array $appends
     *
     * @return bool
     */
    public function hasAllowedAppends(string|array $appends): bool
    {
        $appends = Arr::wrap($appends);
        $allowed = $this->listOfAllowedAppends();

        foreach ($appends as $append) {
            if (!in_array($append, $allowed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get append value. If it's not allowed, then
     * a validation exception (422) will be thrown.
     *
     * @param string $append
     *
     * @return mixed
     * @throws ValidationException
     */
    public function getAllowedAppend(string $append): mixed
    {
        if (!$this->hasAllowedAppends($append)) {
            throw $this->notAllowedAppend($append);
        }

        return data_get($this, $append);
    }

    /**
     * Get a validation exception (422) for the given append,
     * which is not allowed, naming the appends that are.
     *
     * @param string $append
     *
     * @return ValidationException
     */
    public function notAllowedAppend(string $append): ValidationException
    {
        $allowed = $this->listOfAllowedAppends();

        $message = "The '$append' append is not allowed. " . (empty($allowed)
            ? 'No appends are allowed.'
            : 'Allowed appends: ' . implode(', ', $allowed) . '.');

        return ValidationException::withMessages([
            'append' => [$message],
        ]);
    }

    /**
     * Get an array of appending keys to values. By default,
     * all allowed appending will be returned. If given appends
     * are not allowed, then an exception will be thrown.
     *
     * @param string|array|null $appends
     *
     * @return array
     * @throws ValidationException
     */
    public function getAllowedAppends(string|array|null $appends = null): array
    {
        $appends = $appends !== null ? Arr::wrap($appends)
            : $this->listOfAllowedAppends();

        $values = [];

        foreach ($appends as $append) {
            $values[$append] = $this->getAllowedAppend($append);
        }

        return $values;
    }

    /**
     * Get an array of requested appending. If requested appends
     * are not allowed, then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws ValidationException
     */
    public function getRequestedAppends(Request $request): array
    {
        return $this->getAllowedAppends(
            $this->passedAppends ?? $this->appendsOfRequest ?? Helpers::requestedAppends($request)
        );
    }
}
