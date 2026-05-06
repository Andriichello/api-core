<?php

namespace Core\Http\Resources\Traits;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Core\Helpers\Helpers;

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
     * an exception will be thrown.
     *
     * @param string $append
     *
     * @return mixed
     * @throws Exception
     */
    public function getAllowedAppend(string $append): mixed
    {
        if (!$this->hasAllowedAppends($append)) {
            $message = "The '$append' append is not allowed on "
                . static::class;

            throw new Exception($message);
        }

        return data_get($this, $append);
    }

    /**
     * Get an array of appending keys to values. By default,
     * all allowed appending will be returned. If given appends
     * are not allowed, then an exception will be thrown.
     *
     * @param string|array|null $appends
     *
     * @return array
     * @throws Exception
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
     * @throws Exception
     */
    public function getRequestedAppends(Request $request): array
    {
        return $this->getAllowedAppends(
            $this->passedAppends ?? Helpers::requestedAppends($request)
        );
    }
}
