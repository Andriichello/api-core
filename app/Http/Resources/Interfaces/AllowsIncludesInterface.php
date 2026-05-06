<?php

namespace Core\Http\Resources\Interfaces;

/**
 * Interface AllowsIncludesInterface.
 *
 * Contains logic for getting the list of relations,
 * which should be included for the given request.
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
     * Get a list of includes that were passed from parent
     * resource. Needed for nested includes.
     *
     * @return array|null
     */
    public function getPassedIncludes(): ?array;

    /**
     * Set a list of includes that are passed from the parent
     * resource. Needed for nested includes.
     *
     * @param array|null $includes
     *
     * @return $this
     */
    public function setPassedIncludes(?array $includes): static;

    /**
     * Get a list of columns that are allowed as
     * includes on request.
     *
     * @return array<int, string>
     */
    public function listOfAllowedIncludes(): array;

    /**
     * Get resource for the given include.
     *
     * @param string $include
     *
     * @return string|null
     */
    public function resourceOfAllowedInclude(string $include): ?string;

    /**
     * Determines if all given columns are allowed as
     * includes on request.
     *
     * @param string|array $includes
     *
     * @return bool
     */
    public function hasAllowedIncludes(string|array $includes): bool;
}
