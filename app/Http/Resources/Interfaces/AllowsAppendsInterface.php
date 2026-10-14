<?php

namespace Core\Http\Resources\Interfaces;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Interface AllowsAppendsInterface.
 *
 * Contains logic for getting the list of attributes,
 * which should be appended for the given request.
 * Throws exception when asked append is not allowed.
 *
 * By default, no appends will be allowed.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources\Interfaces
 */
interface AllowsAppendsInterface
{
    /**
     * Get the list of columns the request asks to append,
     * as the collection of the resource parsed it, or null.
     *
     * @return array|null
     */
    public function getAppendsOfRequest(): ?array;

    /**
     * Set the list of columns the request asks to append,
     * parsed once by the collection of the resource, so
     * that none of its items parses the request again.
     *
     * @param array|null $appends
     *
     * @return $this
     */
    public function setAppendsOfRequest(?array $appends): static;

    /**
     * Get a list of columns that are allowed as
     * appends on request.
     *
     * @return array<int, string>
     */
    public function listOfAllowedAppends(): array;

    /**
     * Determines if all given columns are allowed as
     * appends on request.
     *
     * @param string|array $appends
     *
     * @return bool
     */
    public function hasAllowedAppends(string|array $appends): bool;

    /**
     * Get append value. If it's not allowed, then
     * an exception will be thrown.
     *
     * @param string $append
     *
     * @return mixed
     * @throws ValidationException
     */
    public function getAllowedAppend(string $append): mixed;

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
    public function getAllowedAppends(string|array|null $appends = null): array;

    /**
     * Get an array of requested appending. If requested appends
     * are not allowed, then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws ValidationException
     */
    public function getRequestedAppends(Request $request): array;
}
