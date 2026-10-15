<?php

namespace Core\Http\Middleware;

use Closure;
use Core\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class RequireUpdatedPassword.
 *
 * Answers 403, until the person sets a new password, when the current one was
 * set by someone else (`User::isPasswordUpdateRequired()`). When a user has
 * switched to the account, it is that user, who is behind the request, so it
 * is their password, which is checked. An application puts it, by its alias,
 * on the routes, which should wait for the new password, which are usually
 * all of them, but the auth routes, with which the person signs in and sets it.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Middleware
 */
class RequireUpdatedPassword
{
    /**
     * Alias of the middleware, which AuthServiceProvider registers.
     *
     * @var string
     */
    public const string ALIAS = 'password.updated';

    /**
     * Message of the response, with which a request is refused.
     *
     * @var string
     */
    public const string MESSAGE = 'Password update is required to use your account.';

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->currentImpersonator() ?? $user)->isPasswordUpdateRequired()) {
            throw new AccessDeniedHttpException(static::MESSAGE);
        }

        return $next($request);
    }
}
