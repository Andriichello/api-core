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
 * A session, which a user switched to another account, ends too, when that
 * user can't sign in or their password changes.
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

        if ($user instanceof User && (!$user->canSignIn() || !$this->impersonatorCanStaySignedIn($request, $user))) {
            $guards = $this->sessionGuards();
            $guards->each->logoutCurrentDevice();

            $request->session()->flush();

            throw new AuthenticationException('Unauthenticated.', [...$guards->keys()->all(), 'sanctum']);
        }

        return parent::handle($request, $next);
    }

    /**
     * Determine if the user, who switched the session to another account (if it
     * was switched), can still sign in and has the password, which they had,
     * when they switched it. Otherwise, the switched session must end, as their
     * own sessions do.
     *
     * @param Request $request
     * @param User $user
     *
     * @return bool
     */
    protected function impersonatorCanStaySignedIn(Request $request, User $user): bool
    {
        $session = $request->session();
        $impersonatorId = $session->get(User::IMPERSONATOR_SESSION_KEY);

        if ($impersonatorId === null) {
            return true;
        }

        // Looked up through the user, so the rest of the request finds the impersonator without a query
        $impersonator = $user->sessionImpersonator($impersonatorId);
        $passwordHash = $session->get(User::IMPERSONATOR_PASSWORD_SESSION_KEY);
        $guard = $this->sessionGuards()->first();

        return $impersonator instanceof User
            && $impersonator->canSignIn()
            && is_string($passwordHash)
            && $guard instanceof SessionGuard
            && $this->validatePasswordHash($guard, $impersonator->getAuthPassword(), $passwordHash);
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
