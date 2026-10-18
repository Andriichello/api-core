<?php

namespace Core\Providers;

use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Middleware\AuthenticateSession;
use Core\Http\Middleware\RequireUpdatedPassword;
use Core\Http\Requests\Auth\VerifyEmailRequest;
use Core\Models\AccessToken;
use Core\Models\AuditLog;
use Core\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession as SanctumAuthenticateSession;
use Laravel\Sanctum\Sanctum;

/**
 * Class AuthServiceProvider.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Providers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // A session of a user, who can no longer sign in, stops working right away
        $this->app->bind(SanctumAuthenticateSession::class, AuthenticateSession::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::$personalAccessTokenModel = AccessToken::class;

        $this->app['router']->aliasMiddleware(RequireUpdatedPassword::ALIAS, RequireUpdatedPassword::class);

        // A token of a user, who can no longer sign in, stops working right away,
        // as does a token, which they switched to another account
        Sanctum::authenticateAccessTokensUsing(
            fn (AccessToken $token, bool $isValid) => $isValid
                && (!$token->tokenable instanceof User || $token->tokenable->canSignIn())
                && (!$token->isImpersonated() || $token->impersonator?->canSignIn() === true)
        );

        // Sign-ins, failed ones and sign-outs are recorded in the audit log, when
        // `api-core.audit.auth_events` is on (as are switches, by AuthController)
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                AuditLog::recordAuthEvent('login', $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            AuditLog::recordAuthEvent('login_failed', $event->user instanceof User ? $event->user : null, [
                'email' => $event->credentials['email'] ?? null,
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                AuditLog::recordAuthEvent('logout', $event->user, [
                    'impersonator_id' => $event->user->currentImpersonator()?->getKey(),
                ]);
            }
        });

        // Requests of a signed-in user, which have no email, count for the user's email
        RateLimiter::for(AuthController::RATE_LIMITER, function (Request $request) {
            $email = $request->has('email')
                ? $request->string('email')->value() : (string) $request->user()?->email;

            return Limit::perMinute(config('api-core.auth.rate_limit'))
                ->by(Str::lower($email) . '|' . $request->ip());
        });

        // The link in the password reset email leads to the SPA's page, and
        // an application overrides it by calling this in its own provider
        ResetPassword::createUrlUsing(
            fn (CanResetPassword $user, string $token) => Uri::of(config('api-core.auth.password_reset_url'))
                ->withQuery(['token' => $token, 'email' => $user->getEmailForPasswordReset()])
                ->value()
        );

        // So does the link in the email verification email
        VerifyEmail::createUrlUsing(
            fn (User $user) => Uri::of(config('api-core.auth.email_verification_url'))
                ->withQuery(VerifyEmailRequest::linkParameters($user))
                ->value()
        );
    }
}
