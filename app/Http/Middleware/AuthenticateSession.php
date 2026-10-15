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

        if ($user instanceof User && !$user->canSignIn()) {
            $guards = $this->sessionGuards();
            $guards->each->logoutCurrentDevice();

            $request->session()->flush();

            throw new AuthenticationException('Unauthenticated.', [...$guards->keys()->all(), 'sanctum']);
        }

        return parent::handle($request, $next);
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
