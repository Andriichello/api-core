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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Class AuthController.
 *
 * Signs users in with Sanctum tokens. api-core registers no routes, so an
 * application registers these with `AuthController::routes()`, usually
 * under a prefix: `Route::prefix('auth')->group(fn () => AuthController::routes())`.
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
     * Sign in with an email and password, getting an access token
     * and a refresh token.
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
     * Sign out, deleting the access token, which the request was made with,
     * along with its refresh token.
     *
     * @param Request $request
     *
     * @return ApiResponse
     */
    public function logout(Request $request): ApiResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof AccessToken) {
            $this->tokens->delete($token);
        }

        event(new Logout('sanctum', $user));

        return ApiResponse::ok([], 'Logged out');
    }

    /**
     * Switch to another account, getting an access token for it, on which
     * the current user is recorded as the impersonator. There is no refresh
     * token, so the client returns to its own token, when this one expires.
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
        $token = $this->tokens->create($account, $request->userAgent(), $request->user());

        return ApiResponse::ok([
            'data' => $this->tokenData($token) + [
                'user' => new MeResource($account->withAccessToken($token->accessToken)),
            ],
        ]);
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
