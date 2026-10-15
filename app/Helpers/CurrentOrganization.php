<?php

namespace Core\Helpers;

use Closure;
use Core\Models\Organization;
use Illuminate\Support\Facades\Context;

/**
 * Class CurrentOrganization.
 *
 * The organization, in which the current request or job acts. For a request,
 * ResolveOrganization sets it from the signed-in user. It is kept in Laravel's
 * Context, so the jobs, which the request dispatches, act in it too. Console
 * commands and other code without a request set it with `run()`. Models with
 * BelongsToOrganization see only its records, and none, when it isn't set.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Helpers
 */
class CurrentOrganization
{
    /**
     * Key, under which the Context keeps id of the organization.
     *
     * @var string
     */
    public const string CONTEXT_KEY = 'api-core.organization_id';

    /**
     * Get id of the current organization, or null, when it isn't set.
     *
     * @return int|null
     * @phpstan-impure
     */
    public static function id(): ?int
    {
        $id = Context::get(static::CONTEXT_KEY);

        return $id === null ? null : (int) $id;
    }

    /**
     * Get the current organization, or null, when it isn't set.
     *
     * @return Organization|null
     */
    public static function get(): ?Organization
    {
        $id = static::id();

        return $id === null ? null : Organization::query()->find($id);
    }

    /**
     * Determine if the current organization is set.
     *
     * @return bool
     */
    public static function isSet(): bool
    {
        return static::id() !== null;
    }

    /**
     * Set the current organization, or unset it with null.
     *
     * @param Organization|int|null $organization
     *
     * @return void
     */
    public static function set(Organization|int|null $organization): void
    {
        $id = $organization instanceof Organization ? $organization->getKey() : $organization;

        if ($id === null) {
            Context::forget(static::CONTEXT_KEY);

            return;
        }

        Context::add(static::CONTEXT_KEY, (int) $id);
    }

    /**
     * Run the callback in the organization, and then return to the one, which
     * was current before, even when the callback throws.
     *
     * @template TResult
     *
     * @param Organization|int|null $organization
     * @param Closure(): TResult $callback
     *
     * @return TResult
     */
    public static function run(Organization|int|null $organization, Closure $callback): mixed
    {
        $previous = static::id();

        static::set($organization);

        try {
            return $callback();
        } finally {
            static::set($previous);
        }
    }
}
