<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Http\Controllers\Auth\AuthController;
use Core\Models\AccessToken;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

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
}
