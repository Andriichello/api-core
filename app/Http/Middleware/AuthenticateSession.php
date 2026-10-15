<?php

namespace Core\Http\Middleware;

use Closure;
use Core\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession as SanctumAuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AuthenticateSession.
 *
 * Sanctum's middleware, which signs a session out, when the user's password
 * changes, extended to sign it out, when the user can no longer sign in, so
 * a suspended user's session stops working right away, as their tokens do.
 * Sanctum keeps the password hash in the session only after a request, which
 * starts signed in, so it is also kept, when a request signs in, and a session
 * is signed out by a password change even before its next request.
 * AuthServiceProvider binds it in place of Sanctum's one.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Middleware
 */
class AuthenticateSession extends SanctumAuthenticateSession
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->hasSession() ? $request->user() : null;

        if ($user === null) {
            return $this->storePasswordHashOnSignIn($request, $next);
        }

        if ($user instanceof User && !$user->canSignIn()) {
            $guards = $this->sessionGuards();
            $guards->each->logoutCurrentDevice();

            $request->session()->flush();

            throw new AuthenticationException('Unauthenticated.', [...$guards->keys()->all(), 'sanctum']);
        }

        return parent::handle($request, $next);
    }

    /**
     * Handle a request, which isn't signed in, keeping the password hash in its
     * session, when it signs in.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     */
    protected function storePasswordHashOnSignIn(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $guard = $request->hasSession()
            ? $this->getFirstGuardWithUser($this->sessionGuards()->keys()) : null;

        if ($guard !== null) {
            $this->storePasswordHashInSession($request, $guard);
        }

        return $response;
    }

    /**
     * Get the session guards, which Sanctum checks for a user.
     *
     * @return Collection<string, SessionGuard>
     */
    protected function sessionGuards(): Collection
    {
        return Collection::make(Arr::wrap(config('sanctum.guard')))
            ->mapWithKeys(fn (string $guard) => [$guard => $this->auth->guard($guard)])
            ->filter(fn ($guard) => $guard instanceof SessionGuard);
    }
}
