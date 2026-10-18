<?php

namespace Core\Http\Controllers\Auth;

use Core\Http\Controllers\BaseController;
use Core\Http\Requests\Auth\AcceptInvitationRequest;
use Core\Http\Requests\Auth\ChangePasswordRequest;
use Core\Http\Requests\Auth\ForgotPasswordRequest;
use Core\Http\Requests\Auth\LoginRequest;
use Core\Http\Requests\Auth\RefreshRequest;
use Core\Http\Requests\Auth\ResetPasswordRequest;
use Core\Http\Requests\Auth\SwitchAccountRequest;
use Core\Http\Requests\Auth\UpdateMeRequest;
use Core\Http\Requests\Auth\VerifyEmailRequest;
use Core\Http\Resources\MeResource;
use Core\Http\Responses\ApiResponse;
use Core\Models\AccessToken;
use Core\Models\AuditLog;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
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
 * A forgotten password is reset with a link, which `forgot-password` emails.
 * It leads to the SPA's page (`api-core.auth.password_reset_url`), which
 * posts the token from it with the new password to `reset-password`.
 * A signed-in user, who knows the password, changes it with `change-password`.
 *
 * An email is verified with a link, which `email/verification-notification`
 * emails to the signed-in user. It leads to the SPA's page
 * (`api-core.auth.email_verification_url`), which posts the parameters
 * from it to `verify-email`, with or without being signed in.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers\Auth
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthController extends BaseController
{
    /**
     * Name of the rate limiter of the login, refresh, password reset, password
     * change and email verification routes.
     *
     * @var string
     */
    public const string RATE_LIMITER = 'api-core-auth';

    /**
     * Create a new controller instance.
     *
     * @param AccessTokenRepository $tokens
     * @param UserRepository $users
     */
    public function __construct(
        protected AccessTokenRepository $tokens,
        protected UserRepository $users
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
            Route::post('forgot-password', [static::class, 'forgotPassword'])->name('auth.forgot-password');
            Route::post('reset-password', [static::class, 'resetPassword'])->name('auth.reset-password');
            Route::post('verify-email', [static::class, 'verifyEmail'])->name('auth.verify-email');
            Route::post('accept-invitation', [static::class, 'acceptInvitation'])->name('auth.accept-invitation');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [static::class, 'me'])->name('auth.me');
            Route::patch('me', [static::class, 'updateMe'])->name('auth.me.update');
            Route::post('logout', [static::class, 'logout'])->name('auth.logout');
            Route::post('switch', [static::class, 'switchAccount'])->name('auth.switch');
            Route::post('email/verification-notification', [static::class, 'sendVerificationEmail'])
                ->middleware('throttle:' . static::RATE_LIMITER)
                ->name('auth.verification-notification');
            Route::post('change-password', [static::class, 'changePassword'])
                ->middleware('throttle:' . static::RATE_LIMITER)
                ->name('auth.change-password');
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
     * Email a link to reset the password. The response is the same, whether
     * the email belongs to an account or not, and when a link was sent to it
     * shortly before, so it doesn't tell, which emails have accounts.
     *
     * @param ForgotPasswordRequest $request
     *
     * @return ApiResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): ApiResponse
    {
        Password::broker()->sendResetLink($request->credentials());

        return ApiResponse::ok(
            [],
            'If the email belongs to an account, a link to reset the password has been sent to it.'
        );
    }

    /**
     * Reset the password with the token from the email. It is set for every
     * person, who has an account with the email, and their access tokens are
     * deleted. Their sessions are signed out by AuthenticateSession, as the
     * password hash, which it keeps in them, no longer matches.
     *
     * @param ResetPasswordRequest $request
     *
     * @return ApiResponse
     * @throws ValidationException
     */
    public function resetPassword(ResetPasswordRequest $request): ApiResponse
    {
        $status = DB::transaction(fn () => Password::broker()->reset(
            $request->credentials(),
            function (User $user, string $password) {
                $accounts = $this->users->resetPassword($user->getEmailForPasswordReset(), $password);

                $this->tokens->deleteOfPeople($accounts->all());

                $accounts->each(fn (User $account) => event(new PasswordReset($account)));
            }
        ));

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            // An unknown email gets the error of a wrong token, so it doesn't tell, which emails have accounts
            throw ValidationException::withMessages([
                'email' => __(PasswordBroker::INVALID_TOKEN),
            ]);
        }

        return ApiResponse::ok([], __(PasswordBroker::PASSWORD_RESET));
    }

    /**
     * Change the password of the signed-in user, who knows the current one.
     * It is set for the person, on all of their accounts, as updated by them
     * (see RequireUpdatedPassword). Their other tokens, and the tokens, with
     * which they switched to other accounts, are deleted, and their other
     * sessions are signed out by AuthenticateSession, while the token or the
     * session, with which the password was changed, stays signed in.
     *
     * @param ChangePasswordRequest $request
     *
     * @return ApiResponse
     */
    public function changePassword(ChangePasswordRequest $request): ApiResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        DB::transaction(function () use ($request, $user, $token) {
            $this->users->updatePassword($user, $request->string('password')->value());

            $this->tokens->deleteOfPeople([$user], $token instanceof AccessToken ? $token : null);
        });

        // The session stays signed in, also when the person switched it to another account (the impersonator
        // is loaded again, as it was loaded before the password changed)
        $impersonator = $user->isSignedInWithSession() ? $user->currentImpersonator()?->refresh() : null;

        if ($impersonator !== null) {
            $this->storeImpersonatorPasswordHash($request, $impersonator);
        }

        return ApiResponse::ok([], 'Your password has been changed.');
    }

    /**
     * Email a link to verify the email of the signed-in user, unless it is
     * verified already.
     *
     * @param Request $request
     *
     * @return ApiResponse
     */
    public function sendVerificationEmail(Request $request): ApiResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::ok([], 'Your email is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::ok([], 'A link to verify your email has been sent to it.');
    }

    /**
     * Verify the email with the link, which was emailed to it. A verified email
     * links the account to the person's other accounts with it (see
     * LinksAccountsByEmail), and then it takes their password, so the tokens,
     * which were issued with its old password, are deleted.
     *
     * @param VerifyEmailRequest $request
     *
     * @return ApiResponse
     * @throws AuthorizationException
     */
    public function verifyEmail(VerifyEmailRequest $request): ApiResponse
    {
        $account = $request->account();

        if ($account->hasVerifiedEmail()) {
            return ApiResponse::ok([], 'Your email is already verified.');
        }

        $isLinked = DB::transaction(function () use ($account) {
            $account->markEmailAsVerified();

            if (!$account->wasChanged('main_account_id')) {
                return false;
            }

            $this->tokens->deleteOfAccounts($account);

            return true;
        });

        event(new Verified($account));

        return ApiResponse::ok([], $isLinked
            ? 'Your email has been verified, and the account now signs in with the password of your other accounts.'
            : 'Your email has been verified.');
    }

    /**
     * Accept the invitation to an account with the link, which was emailed to
     * it. Its email is verified, which links it to the person's other accounts,
     * if any (see LinksAccountsByEmail). The password, which the person
     * chooses, is set on all of their accounts, as updated by them, as a reset
     * would set it, so their tokens are deleted. A person, whose account is
     * linked, may keep their password instead, by giving none, while a person
     * without other accounts must choose one. The link works once, since it is
     * bound to the account's password.
     *
     * @param AcceptInvitationRequest $request
     *
     * @return ApiResponse
     * @throws AuthorizationException
     * @throws ValidationException When no password is given for an account, which isn't linked.
     */
    public function acceptInvitation(AcceptInvitationRequest $request): ApiResponse
    {
        $account = $request->account();
        $password = $request->validated('password');

        DB::transaction(function () use ($account, $password) {
            $account->markEmailAsVerified();

            if ($password === null && $account->isMainAccount()) {
                throw ValidationException::withMessages([
                    'password' => [__('validation.required', ['attribute' => 'password'])],
                ]);
            }

            if ($password !== null) {
                $this->users->updatePassword($account, $password);
                $this->tokens->deleteOfPeople([$account]);
            }
        });

        event(new Verified($account));

        return ApiResponse::ok([], $password === null
            ? 'Your invitation has been accepted, so you can sign in with the password of your other accounts.'
            : 'Your invitation has been accepted, so you can sign in with your password.');
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
     * Update the signed-in user's own name, timezone or locale.
     *
     * @param UpdateMeRequest $request
     *
     * @return ApiResponse
     */
    public function updateMe(UpdateMeRequest $request): ApiResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->users->update($user, $request->validated());

        return ApiResponse::ok([
            'data' => new MeResource($user),
        ], 'Updated');
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
            AuditLog::recordAuthEvent('switched', $account, ['impersonator_id' => $impersonator?->getKey()], $user);

            return ApiResponse::ok([
                'data' => [
                    'user' => new MeResource($account->withAccessToken(new TransientToken)),
                ],
            ]);
        }

        $token = $this->tokens->create($account, $request->userAgent(), $impersonator);
        AuditLog::recordAuthEvent('switched', $account, ['impersonator_id' => $impersonator?->getKey()], $user);

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
        $request->session()->forget([User::IMPERSONATOR_SESSION_KEY, User::IMPERSONATOR_PASSWORD_SESSION_KEY]);

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
     * Switch the session to the account, recording the impersonator and their
     * password hash in it (AuthenticateSession ends the session, when they
     * can't sign in or change the password), and give the session a new id.
     * Unlike signing in, it doesn't fire the Login event, as switching with
     * tokens doesn't either.
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

        $session->forget([User::IMPERSONATOR_SESSION_KEY, User::IMPERSONATOR_PASSWORD_SESSION_KEY]);

        if ($impersonator !== null) {
            $session->put(User::IMPERSONATOR_SESSION_KEY, $impersonator->getAuthIdentifier());
            $this->storeImpersonatorPasswordHash($request, $impersonator);
        }

        $session->migrate(true);

        $guard->setUser($account);
    }

    /**
     * Keep the password hash of the user, who switched the session to another
     * account, in the session, so AuthenticateSession ends the session, when
     * that user's password changes elsewhere.
     *
     * @param Request $request
     * @param User $impersonator
     *
     * @return void
     */
    protected function storeImpersonatorPasswordHash(Request $request, User $impersonator): void
    {
        $request->session()->put(
            User::IMPERSONATOR_PASSWORD_SESSION_KEY,
            $this->sessionGuard()->hashPasswordForCookie($impersonator->getAuthPassword())
        );
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
