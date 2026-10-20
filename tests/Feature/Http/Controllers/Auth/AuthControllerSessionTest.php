<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Enums\UserRole;
use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Middleware\RequireUpdatedPassword;
use Core\Models\AccessToken;
use Core\Models\AuditLog;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class AuthControllerSessionTest.
 *
 * Signing in from the SPA, whose requests have a session, because the routes
 * run Sanctum's stateful middleware, as `$middleware->statefulApi()` sets up.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers\Auth
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthControllerSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Origin of the SPA, whose domain is one of Sanctum's stateful domains.
     *
     * @var string
     */
    private const string SPA_ORIGIN = 'https://spa.test';

    /**
     * Organization, in which the users are signed in by default.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Register the authentication routes with the stateful middleware and
     * create the default organization. Sessions are kept in the database,
     * so they outlive the session store, which is forgotten between requests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sanctum.stateful' => ['spa.test'],
            'session.driver' => 'database',
        ]);

        Route::prefix('auth')
            ->middleware(EnsureFrontendRequestsAreStateful::class)
            ->group(fn () => AuthController::routes());

        $this->acme = $this->createOrganization('acme');
    }

    /**
     * Create an organization with the slug.
     *
     * @param string $slug
     *
     * @return Organization
     */
    private function createOrganization(string $slug): Organization
    {
        return Organization::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
    }

    /**
     * Create a user with the email, whose password is `password123`. The email isn't verified, so the accounts
     * aren't linked by it automatically.
     *
     * @param string $email
     * @param Organization|null $organization
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createUser(string $email, ?Organization $organization = null, array $attributes = []): User
    {
        return User::factory()
            ->unverified()
            ->create([
                'organization_id' => $organization?->id,
                'email' => $email,
                'password' => 'password123',
                ...$attributes,
            ]);
    }

    /**
     * Send a JSON request from the SPA with the session cookie, which the previous responses set. The session
     * store and the guards keep the signed-in user in memory between requests of the same test, so they are
     * forgotten first, and only the cookie authenticates the request.
     *
     * @param string $method
     * @param string $uri
     * @param array<string, mixed> $data
     *
     * @return TestResponse
     */
    private function spa(string $method, string $uri, array $data = []): TestResponse
    {
        $this->forgetSignedInUser();

        $response = $this->withCredentials()
            ->withHeader('Origin', self::SPA_ORIGIN)
            ->json($method, $uri, $data);

        $cookie = $response->getCookie(config('session.cookie'));

        if ($cookie !== null) {
            $this->withCookie($cookie->getName(), $cookie->getValue());
        }

        return $response;
    }

    /**
     * Forget the session store and the guards, which keep the signed-in user in memory between requests of the
     * same test.
     *
     * @return void
     */
    private function forgetSignedInUser(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    /**
     * Send a login request from the SPA with the credentials, in which the password is `password123` by default.
     *
     * @param array<string, mixed> $credentials
     *
     * @return TestResponse
     */
    private function login(array $credentials): TestResponse
    {
        return $this->spa('POST', '/auth/login', ['password' => 'password123', ...$credentials]);
    }

    /**
     * Reset the user's password with the token from the emailed link, which is opened on another device, so
     * the request has no session.
     *
     * @param User $user
     * @param string $password
     *
     * @return TestResponse
     */
    private function resetPasswordElsewhere(User $user, string $password): TestResponse
    {
        $this->forgetSignedInUser();

        return $this->withoutHeader('Origin')->postJson('/auth/reset-password', [
            'email' => $user->email,
            'token' => Password::createToken($user),
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    /**
     * Get the name of the session guard's remember cookie.
     *
     * @return string
     */
    private function recallerName(): string
    {
        return $this->app['auth']->guard('web')->getRecallerName();
    }

    /**
     * Drop every cookie but the remember one, as a browser does, which kept the remember cookie past the session.
     *
     * @param Cookie $recaller
     *
     * @return void
     */
    private function reopenBrowser(Cookie $recaller): void
    {
        $this->defaultCookies = [];
        $this->withCookie($recaller->getName(), $recaller->getValue());
    }

    /**
     * Get id of the session, which the response's cookie carries.
     *
     * @param TestResponse $response
     *
     * @return string
     */
    private function sessionId(TestResponse $response): string
    {
        return $response->getCookie(config('session.cookie'))->getValue();
    }

    /**
     * Test that the SPA signs in to its session, getting the user without tokens, and the session cookie then
     * authenticates its requests.
     *
     * @return void
     */
    public function testSpaLogsInWithSession(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);

        Event::fake([Login::class]);

        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $this->login(['email' => 'user@example.com'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.organization.slug', 'acme')
            ->assertJsonPath('data.user.impersonator', null)
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.refresh_token');

        $this->spa('GET', '/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertSame(0, AccessToken::query()->count());
        $this->assertNotNull($user->fresh()->last_login_at);

        Event::assertDispatched(
            Login::class,
            fn (Login $event) => $event->guard === 'web' && $event->user->getAuthIdentifier() === $user->id
        );
    }

    /**
     * Test that the session cookie doesn't authenticate requests, which don't come from the SPA, as they have
     * no session.
     *
     * @return void
     */
    public function testSessionWorksOnlyFromSpa(): void
    {
        $this->createUser('user@example.com', $this->acme);
        $this->login(['email' => 'user@example.com'])->assertOk();

        $this->forgetSignedInUser();

        $this->withHeader('Origin', 'https://other.test')
            ->getJson('/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Test that requests, which don't come from the SPA, still sign in with tokens.
     *
     * @return void
     */
    public function testIssuesTokensToRequestsNotFromSpa(): void
    {
        $this->createUser('user@example.com', $this->acme);

        $this->postJson('/auth/login', ['email' => 'user@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->withHeader('Origin', 'https://other.test')
            ->postJson('/auth/login', ['email' => 'user@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertSame(2, AccessToken::query()->count());
    }

    /**
     * Test that signing in and switching accounts give the session a new id, so the one from before can't be
     * used to take it over.
     *
     * @return void
     */
    public function testStartsNewSessionOnLoginAndSwitch(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $linked = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);

        $guest = $this->sessionId($this->spa('GET', '/sanctum/csrf-cookie')->assertNoContent());
        $signedIn = $this->sessionId($this->login(['email' => 'user@example.com'])->assertOk());
        $switched = $this->sessionId($this->spa('POST', '/auth/switch', ['user_id' => $linked->id])->assertOk());

        $this->assertNotSame($guest, $signedIn);
        $this->assertNotSame($signedIn, $switched);

        $this->withCookie(config('session.cookie'), $signedIn)
            ->spa('GET', '/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Test that the SPA signs out, and the session, which it was signed in to, is invalidated.
     *
     * @return void
     */
    public function testSpaLogsOut(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);

        Event::fake([Logout::class]);

        $signedIn = $this->sessionId($this->login(['email' => 'user@example.com'])->assertOk());
        $signedOut = $this->sessionId($this->spa('POST', '/auth/logout')->assertOk());

        $this->assertNotSame($signedIn, $signedOut);
        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $this->withCookie(config('session.cookie'), $signedIn)
            ->spa('GET', '/auth/me')
            ->assertUnauthorized();

        Event::assertDispatched(
            Logout::class,
            fn (Logout $event) => $event->guard === 'web' && $event->user->getAuthIdentifier() === $user->id
        );
    }

    /**
     * Test that the session of a user stops working, once the user is suspended, and stays signed out, after
     * the user is unsuspended.
     *
     * @return void
     */
    public function testSignsOutSessionOfSuspendedUser(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);
        $this->login(['email' => 'user@example.com'])->assertOk();

        $user->forceFill(['suspended_at' => now()])->save();
        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $user->forceFill(['suspended_at' => null])->save();
        $this->spa('GET', '/auth/me')->assertUnauthorized();
    }

    /**
     * Test that a password reset signs out the sessions of the person, the one, which made no request since
     * signing in, and the one, which switched to a linked account, included.
     *
     * @return void
     */
    public function testPasswordResetSignsOutSessions(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $linked = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);

        $this->login(['email' => 'user@example.com'])->assertOk();
        $this->resetPasswordElsewhere($main, 'new-password')->assertOk();
        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $this->login(['email' => 'user@example.com', 'password' => 'new-password'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $linked->id])->assertOk();
        $this->spa('GET', '/auth/me')->assertOk()->assertJsonPath('data.id', $linked->id);
        $this->resetPasswordElsewhere($main, 'newer-password')->assertOk();
        $this->spa('GET', '/auth/me')->assertUnauthorized();
    }

    /**
     * Test that the session, with which the password is changed, stays signed in, whether it is signed in to the
     * main account or switched to a linked one.
     *
     * @return void
     */
    public function testPasswordChangeKeepsCurrentSession(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $linked = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);

        $this->login(['email' => 'user@example.com'])->assertOk();
        $this->spa('GET', '/auth/me')->assertOk();

        $this->spa('POST', '/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->spa('GET', '/auth/me')->assertOk()->assertJsonPath('data.id', $main->id);

        $this->spa('POST', '/auth/switch', ['user_id' => $linked->id])->assertOk();
        $this->spa('POST', '/auth/change-password', [
            'current_password' => 'new-password',
            'password' => 'newer-password',
            'password_confirmation' => 'newer-password',
        ])->assertOk();

        $this->spa('GET', '/auth/me')->assertOk()->assertJsonPath('data.id', $linked->id);
    }

    /**
     * Test that a session, which a super admin switched to another account, is checked by the super admin's
     * password: a user, whose password must be updated, is let through, while the super admin's own requirement
     * applies.
     *
     * @return void
     */
    public function testSwitchedSessionChecksPasswordOfUserWhoSignedIn(): void
    {
        Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum', RequireUpdatedPassword::ALIAS])
            ->get('protected', fn () => 'OK');

        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com');
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $user = $this->createUser('user@example.com', $this->acme, ['password_updated_at' => null]);

        $this->login(['email' => 'admin@example.com'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $user->id])->assertOk();
        $this->spa('GET', '/protected')->assertOk();

        $this->spa('POST', '/auth/switch', ['user_id' => $superAdmin->id])->assertOk();
        User::query()->whereKey($superAdmin->id)->update(['password_updated_at' => null]);
        $this->spa('POST', '/auth/switch', ['user_id' => $user->id])->assertOk();

        $this->spa('GET', '/protected')
            ->assertForbidden()
            ->assertJsonPath('message', RequireUpdatedPassword::MESSAGE);
    }

    /**
     * Test that a session, which a super admin switched to another account, ends, when the super admin is
     * suspended or deleted, as the super admin's own sessions do.
     *
     * @return void
     */
    public function testSwitchedSessionEndsWhenUserWhoSignedInCannotSignIn(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com');
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $user = $this->createUser('user@example.com', $this->acme);

        $this->login(['email' => 'admin@example.com'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $user->id])->assertOk();
        $this->spa('GET', '/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);

        $superAdmin->forceFill(['suspended_at' => now()])->save();
        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $superAdmin->forceFill(['suspended_at' => null])->save();
        $this->login(['email' => 'admin@example.com'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $user->id])->assertOk();

        $superAdmin->delete();
        $this->spa('GET', '/auth/me')->assertUnauthorized();
    }

    /**
     * Test that a session, which a super admin switched to another account, ends, when the super admin's password
     * is reset, while the super admin switching back to their own account checks their password as usual.
     *
     * @return void
     */
    public function testSwitchedSessionEndsWhenPasswordOfUserWhoSignedInIsReset(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com');
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $user = $this->createUser('user@example.com', $this->acme);

        $this->login(['email' => 'admin@example.com'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $user->id])->assertOk();
        $this->resetPasswordElsewhere($superAdmin, 'new-password')->assertOk();
        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $this->login(['email' => 'admin@example.com', 'password' => 'new-password'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $user->id])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $superAdmin->id])->assertOk();
        $this->spa('GET', '/auth/me')->assertOk()->assertJsonPath('data.id', $superAdmin->id);

        $this->resetPasswordElsewhere($superAdmin, 'newer-password')->assertOk();
        $this->spa('GET', '/auth/me')->assertUnauthorized();
    }

    /**
     * Test that the session of a main account switches to its linked accounts and back, keeping the main account
     * as the impersonator, without issuing tokens or firing the Login event.
     *
     * @return void
     */
    public function testSpaSwitchesToLinkedAccountsAndBack(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $beta = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);
        $gamma = $this->createUser('user@example.com', $this->createOrganization('gamma'), [
            'main_account_id' => $main->id,
        ]);

        Event::fake([Login::class]);

        $this->login(['email' => 'user@example.com'])->assertOk();

        $this->spa('POST', '/auth/switch', ['user_id' => $beta->id])
            ->assertOk()
            ->assertJsonPath('data.user.id', $beta->id)
            ->assertJsonPath('data.user.impersonator.id', $main->id)
            ->assertJsonMissingPath('data.access_token');

        $this->spa('GET', '/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $beta->id)
            ->assertJsonPath('data.organization.slug', 'beta')
            ->assertJsonPath('data.impersonator.id', $main->id);

        $this->spa('POST', '/auth/switch', ['user_id' => $gamma->id])
            ->assertOk()
            ->assertJsonPath('data.user.id', $gamma->id)
            ->assertJsonPath('data.user.impersonator.id', $main->id);

        $this->spa('POST', '/auth/switch', ['user_id' => $main->id])
            ->assertOk()
            ->assertJsonPath('data.user.id', $main->id)
            ->assertJsonPath('data.user.impersonator', null);

        $this->spa('GET', '/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $main->id)
            ->assertJsonPath('data.impersonator', null);

        $this->assertSame(0, AccessToken::query()->count());

        Event::assertDispatchedTimes(Login::class, 1);
    }

    /**
     * Test that a session's login, its switch to a linked account and its logout are recorded in the audit log,
     * when authentication events are recorded, with the main account as the one, who switched.
     *
     * @return void
     */
    public function testRecordsAuthEventsOfSession(): void
    {
        config(['api-core.audit.auth_events' => true]);

        $main = $this->createUser('user@example.com', $this->acme);
        $linked = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);

        $this->login(['email' => 'user@example.com'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $linked->id])->assertOk();
        $this->spa('POST', '/auth/logout')->assertOk();

        $entries = AuditLog::query()->where('source', AuditLog::SOURCE_AUTH)->orderBy('id')->get();

        $this->assertSame(['login', 'switched', 'logout'], $entries->pluck('event')->all());
        $this->assertSame([$main->id, $linked->id, $linked->id], $entries->pluck('subject_id')->all());
        $this->assertSame([$main->id, $main->id, $linked->id], $entries->pluck('causer_id')->all());
        $this->assertSame([null, $main->id, $main->id], $entries->pluck('metadata.impersonator_id')->all());
    }

    /**
     * Test that a session, which signed in to a linked account, cannot switch to its main account or to accounts
     * of other people, as the switch is checked for the user, who signed in.
     *
     * @return void
     */
    public function testSessionCannotSwitchToAccountsOfOthers(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);
        $stranger = $this->createUser('stranger@example.com', $this->acme);

        $this->login(['email' => 'user@example.com', 'organization' => 'beta'])->assertOk();

        $this->spa('POST', '/auth/switch', ['user_id' => $main->id])->assertForbidden();
        $this->spa('POST', '/auth/switch', ['user_id' => $stranger->id])->assertForbidden();
    }

    /**
     * Test that signing out of a switched session ends it entirely, and signing in again starts without the
     * impersonator.
     *
     * @return void
     */
    public function testLogoutEndsSwitchedSession(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $linked = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);

        $this->login(['email' => 'user@example.com'])->assertOk();
        $this->spa('POST', '/auth/switch', ['user_id' => $linked->id])->assertOk();

        $this->spa('POST', '/auth/logout')->assertOk();
        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $this->login(['email' => 'user@example.com'])->assertOk();

        $this->spa('GET', '/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $main->id)
            ->assertJsonPath('data.impersonator', null);
    }

    /**
     * Test that a login, which asks to be remembered, signs a new session in from its cookie once the session is
     * gone, while a login, which doesn't ask, sets no such cookie.
     *
     * @return void
     */
    public function testRemembersTheLoginBeyondItsSessionWhenAsked(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);
        $this->login(['email' => 'user@example.com'])->assertOk()->assertCookieMissing($this->recallerName());

        $recaller = $this->login(['email' => 'user@example.com', 'remember' => true])
            ->assertOk()
            ->getCookie($this->recallerName());

        $this->assertNotNull($recaller);
        $this->assertNotNull($user->fresh()->remember_token);
        $this->reopenBrowser($recaller);
        $this->spa('GET', '/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    /**
     * Test that a remembered login ends when the user signs out, and when the password is reset elsewhere.
     *
     * @return void
     */
    public function testRememberedLoginEndsOnLogoutAndPasswordReset(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);

        $recaller = $this->login(['email' => 'user@example.com', 'remember' => true])
            ->assertOk()
            ->getCookie($this->recallerName());
        $this->spa('POST', '/auth/logout')->assertOk();
        $this->reopenBrowser($recaller);
        $this->spa('GET', '/auth/me')->assertUnauthorized();

        $recaller = $this->login(['email' => 'user@example.com', 'remember' => true])
            ->assertOk()
            ->getCookie($this->recallerName());
        $this->resetPasswordElsewhere($user, 'new-password')->assertOk();
        $this->reopenBrowser($recaller);
        $this->spa('GET', '/auth/me')->assertUnauthorized();
    }
}
