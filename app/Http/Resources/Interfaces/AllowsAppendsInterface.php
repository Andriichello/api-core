<?php

namespace Core\Http\Resources\Interfaces;

use Exception;
use Illuminate\Http\Request;

/**
 * Interface AllowsAppendsInterface.
 *
 * Contains logic for getting the list of attributes,
 * which should be appended for given request.
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
     * Get list of columns that are allowed as
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
     * Get append value. If it's not allowed then
     * an exception will be thrown.
     *
     * @param string $append
     *
     * @return mixed
     * @throws Exception
     */
    public function getAllowedAppend(string $append): mixed;

    /**
     * Get an array of append keys to values. By default,
     * all allowed appends will be returned. If given appends
     * are not allowed then an exception will be thrown.
     *
     * @param string|array|null $appends
     *
     * @return array
     * @throws Exception
     */
    public function getAllowedAppends(string|array $appends = null): array;

    /**
     * Get an array of requested appends. If requested appends
     * are not allowed then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws Exception
     */
    public function getRequestedAppends(Request $request): array;
}
