<?php

namespace Core\Http\Controllers\Auth;

use Core\Http\Controllers\BaseController;
use Core\Http\Requests\Auth\LoginRequest;
use Core\Http\Requests\Auth\RefreshRequest;
use Core\Http\Requests\Auth\SwitchAccountRequest;
use Core\Http\Resources\MeResource;
use Core\Http\Responses\ApiResponse;
use Core\Models\AccessToken;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * Class AuthController.
 *
 * Signs users in with Sanctum tokens or, when the request comes from the SPA,
 * with a session. api-core registers no routes, so an application registers
 * these with `AuthController::routes()`, usually under a prefix in
 * `routes/api.php`, where responses are always JSON:
 * `Route::prefix('auth')->group(fn () => AuthController::routes())`.
 *
 * Requests from the SPA have a session, when the application calls
 * `$middleware->statefulApi()` and lists the SPA's domain in Sanctum's
 * stateful domains. The SPA gets a CSRF cookie from `/sanctum/csrf-cookie`
 * first, and then its login, logout and switch work on the session cookie.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers\Auth
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthController extends BaseController
{
    /**
     * Name of the rate limiter of the login and refresh routes.
     *
     * @var string
     */
    public const string RATE_LIMITER = 'api-core-auth';

    /**
     * Create a new controller instance.
     *
     * @param AccessTokenRepository $tokens
     */
    public function __construct(
        protected AccessTokenRepository $tokens
    ) {}

    /**
     * Register the authentication routes, which are handled by this controller.
     *
     * @return void
     */
    public static function routes(): void
    {
        Route::middleware('throttle:' . static::RATE_LIMITER)->group(function () {
            Route::post('login', [static::class, 'login'])->name('auth.login');
            Route::post('refresh', [static::class, 'refresh'])->name('auth.refresh');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [static::class, 'me'])->name('auth.me');
            Route::post('logout', [static::class, 'logout'])->name('auth.logout');
            Route::post('switch', [static::class, 'switchAccount'])->name('auth.switch');
        });
    }

    /**
     * Sign in with an email and password. A request from the SPA signs in to
     * its session, and any other request gets an access token and a refresh
     * token.
     *
     * @param LoginRequest $request
     *
     * @return ApiResponse
     * @throws ValidationException
     */
    public function login(LoginRequest $request): ApiResponse
    {
        $user = $request->authenticate();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($request->hasSession()) {
            $this->loginToSession($request, $user);

            return ApiResponse::ok([
                'data' => [
                    'user' => new MeResource($user->withAccessToken(new TransientToken)),
                ],
            ]);
        }

        event(new Login('sanctum', $user, false));

        $token = $this->tokens->create($user, $request->userAgent());
        $refreshToken = $this->tokens->createRefreshToken($token->accessToken);

        return ApiResponse::ok([
            'data' => $this->tokenData($token, $refreshToken) + [
                'user' => new MeResource($user->withAccessToken($token->accessToken)),
            ],
        ]);
    }

    /**
     * Exchange a refresh token for a new access token and refresh token.
     *
     * @param RefreshRequest $request
     *
     * @return ApiResponse
     * @throws AuthenticationException
     */
    public function refresh(RefreshRequest $request): ApiResponse
    {
        $tokens = $this->tokens->refresh(
            $request->string('refresh_token')->value(),
            $request->userAgent()
        );

        if ($tokens === null) {
            throw new AuthenticationException('The refresh token is invalid or has expired.');
        }

        return ApiResponse::ok([
            'data' => $this->tokenData(...$tokens),
        ]);
    }

    /**
     * Get the signed-in user.
     *
     * @param Request $request
     *
     * @return ApiResponse
     */
    public function me(Request $request): ApiResponse
    {
        return ApiResponse::ok([
            'data' => new MeResource($request->user()),
        ]);
    }

    /**
     * Sign out of the session or, with a token, delete the access token,
     * which the request was made with, along with its refresh token.
     *
     * @param Request $request
     *
     * @return ApiResponse
     */
    public function logout(Request $request): ApiResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSignedInWithSession()) {
            $this->logoutOfSession($request);

            return ApiResponse::ok([], 'Logged out');
        }

        $token = $user->currentAccessToken();

        if ($token instanceof AccessToken) {
            $this->tokens->delete($token);
        }

        event(new Logout('sanctum', $user));

        return ApiResponse::ok([], 'Logged out');
    }

    /**
     * Switch to another account. With a session, the session is switched to
     * it, and switching to the user, who signed in, switches back. Otherwise,
     * an access token is issued for it, on which the current user is recorded
     * as the impersonator. There is no refresh token, so the client returns
     * to its own token, when this one expires.
     *
     * @param SwitchAccountRequest $request
     *
     * @return ApiResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function switchAccount(SwitchAccountRequest $request): ApiResponse
    {
        $account = $request->account();
        $user = $request->originalUser();
        $impersonator = $account->is($user) ? null : $user;

        /** @var User $current */
        $current = $request->user();

        if ($current->isSignedInWithSession()) {
            $this->switchSession($request, $account, $impersonator);

            return ApiResponse::ok([
                'data' => [
                    'user' => new MeResource($account->withAccessToken(new TransientToken)),
                ],
            ]);
        }

        $token = $this->tokens->create($account, $request->userAgent(), $impersonator);

        return ApiResponse::ok([
            'data' => $this->tokenData($token) + [
                'user' => new MeResource($account->withAccessToken($token->accessToken)),
            ],
        ]);
    }

    /**
     * Get the guard, which keeps the signed-in user in the session: the first
     * of the guards, which Sanctum checks before looking for a token.
     *
     * @return SessionGuard
     */
    protected function sessionGuard(): SessionGuard
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard(Arr::first(Arr::wrap(config('sanctum.guard', 'web'))));

        return $guard;
    }

    /**
     * Sign the session in to the user. The guard gives the session a new id,
     * so the id it had before signing in can't be used to take it over.
     *
     * @param Request $request
     * @param User $user
     *
     * @return void
     */
    protected function loginToSession(Request $request, User $user): void
    {
        $request->session()->forget(User::IMPERSONATOR_SESSION_KEY);

        $this->sessionGuard()->login($user);
    }

    /**
     * Sign out of the session, invalidating it along with its CSRF token.
     *
     * @param Request $request
     *
     * @return void
     */
    protected function logoutOfSession(Request $request): void
    {
        $this->sessionGuard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Switch the session to the account, recording the impersonator in it, and
     * give the session a new id. Unlike signing in, it doesn't fire the Login
     * event, as switching with tokens doesn't either.
     *
     * @param Request $request
     * @param User $account
     * @param User|null $impersonator
     *
     * @return void
     */
    protected function switchSession(Request $request, User $account, ?User $impersonator): void
    {
        $guard = $this->sessionGuard();
        $session = $request->session();

        $session->put($guard->getName(), $account->getAuthIdentifier());
        $session->put(User::IMPERSONATOR_SESSION_KEY, $impersonator?->getAuthIdentifier());
        $session->migrate(true);

        $guard->setUser($account);
    }

    /**
     * Get the data of the issued tokens.
     *
     * @param NewAccessToken $token
     * @param string|null $refreshToken
     *
     * @return array<string, mixed>
     */
    protected function tokenData(NewAccessToken $token, ?string $refreshToken = null): array
    {
        /** @var AccessToken $accessToken */
        $accessToken = $token->accessToken;

        $data = [
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $accessToken->expires_at?->toIso8601String(),
            'expires_in' => (int) now()->diffInSeconds($accessToken->expires_at, true),
        ];

        if ($refreshToken !== null) {
            $data['refresh_token'] = $refreshToken;
            $data['refresh_expires_at'] = $accessToken->refresh_expires_at?->toIso8601String();
        }

        return $data;
    }
}
