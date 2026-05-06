<?php

namespace Core\Http\Resources\Interfaces;

use Exception;
use Illuminate\Http\Request;

/**
 * Interface AllowsIncludesInterface.
 *
 * Contains logic for getting the list of relations,
 * which should be included for given request.
 * Throws exception when asked include is not allowed.
 *
 * By default, no includes will be allowed.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources\Interfaces
 */
interface AllowsIncludesInterface
{
    /**
     * Get list of columns that are allowed as
     * includes on request.
     *
     * @return array<int, string>
     */
    public function listOfAllowedIncludes(): array;

    /**
     * Determines if all given columns are allowed as
     * includes on request.
     *
     * @param string|array $includes
     *
     * @return bool
     */
    public function hasAllowedIncludes(string|array $includes): bool;

    /**
     * Get include value. If it's not allowed then
     * an exception will be thrown.
     *
     * @param string $include
     * @param array $all (List of all includes, required for nested includes)
     *
     * @return mixed
     * @throws Exception
     */
    public function getAllowedInclude(string $include, array $all = []): mixed;

    /**
     * Get an array of include keys to values. By default,
     * all allowed includes will be returned. If given includes
     * are not allowed then an exception will be thrown.
     *
     * @param string|array|null $includes
     *
     * @return array
     * @throws Exception
     */
    public function getAllowedIncludes(string|array $includes = null): array;

    /**
     * Get an array of requested includes. If requested includes
     * are not allowed then an exception will be thrown.
     *
     * @param Request $request
     *
     * @return array
     * @throws Exception
     */
    public function getRequestedIncludes(Request $request): array;
}
