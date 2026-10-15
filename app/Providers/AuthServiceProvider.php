<?php

namespace Core\Providers;

use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Middleware\AuthenticateSession;
use Core\Models\AccessToken;
use Core\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession as SanctumAuthenticateSession;
use Laravel\Sanctum\Sanctum;

/**
 * Class AuthServiceProvider.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Providers
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

        // A token of a user, who can no longer sign in, stops working right away
        Sanctum::authenticateAccessTokensUsing(
            fn (AccessToken $token, bool $isValid) => $isValid
                && (!$token->tokenable instanceof User || $token->tokenable->canSignIn())
        );

        RateLimiter::for(AuthController::RATE_LIMITER, function (Request $request) {
            return Limit::perMinute(config('api-core.auth.rate_limit'))
                ->by(Str::lower($request->string('email')->value()) . '|' . $request->ip());
        });
    }
}
