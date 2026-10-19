<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Enums\UserRole;
use Core\Http\Controllers\Auth\AuthController;
use Core\Models\AccessToken;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\PermissionRepository;
use Core\Tests\TestCase;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

/**
 * Class AuthControllerTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers\Auth
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Organization, in which the users are signed in by default.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Register the authentication routes and create the default organization.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('auth')->group(fn () => AuthController::routes());

        $this->acme = $this->createOrganization('acme');
    }

    /**
     * Create an organization with the slug.
     *
     * @param string $slug
     * @param array<string, mixed> $attributes
     *
     * @return Organization
     */
    private function createOrganization(string $slug, array $attributes = []): Organization
    {
        return Organization::query()->forceCreate([
            'slug' => $slug,
            'name' => ucfirst($slug),
            ...$attributes,
        ]);
    }

    /**
     * Create a user with the email, whose password is `password123`.
     *
     * @param string $email
     * @param Organization|null $organization
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createUser(string $email, ?Organization $organization = null, array $attributes = []): User
    {
        return User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => 'Name of ' . $email,
            'email' => $email,
            'password' => 'password123',
            ...$attributes,
        ]);
    }

    /**
     * Send a login request with the credentials, in which the password is `password123` by default.
     *
     * @param array<string, mixed> $credentials
     *
     * @return TestResponse
     */
    private function login(array $credentials): TestResponse
    {
        return $this->postJson('/auth/login', ['password' => 'password123', ...$credentials]);
    }

    /**
     * Authenticate the next request with the access token. The guard remembers the user between requests of
     * the same test, so it is forgotten first.
     *
     * @param string $token
     *
     * @return static
     */
    private function asBearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /**
     * Test that the email and password sign in, issuing an access token and a refresh token.
     *
     * @return void
     */
    public function testLogsInWithEmailAndPassword(): void
    {
        Event::fake([Login::class]);

        $user = $this->createUser('user@example.com', $this->acme);

        $response = $this->login(['email' => 'user@example.com'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.organization.slug', 'acme')
            ->assertJsonStructure(['data' => [
                'access_token', 'expires_at', 'expires_in', 'refresh_token', 'refresh_expires_at',
            ]]);

        $token = AccessToken::findToken($response->json('data.access_token'));

        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertSame($this->acme->id, $token->organization_id);
        $this->assertNull($token->impersonator_id);
        $this->assertEqualsWithDelta(now()->addMinutes(480)->timestamp, $token->expires_at->timestamp, 5);
        $this->assertEqualsWithDelta(now()->addMinutes(20160)->timestamp, $token->refresh_expires_at->timestamp, 5);
        $this->assertNotNull($user->fresh()->last_login_at);

        Event::assertDispatched(Login::class, fn (Login $event) => $event->user->getAuthIdentifier() === $user->id);
    }

    /**
     * Test that the issued access token authenticates requests.
     *
     * @return void
     */
    public function testAccessTokenAuthenticatesRequests(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);
        $token = $this->login(['email' => 'user@example.com'])->json('data.access_token');

        $this->asBearer($token)
            ->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    /**
     * Test that login matches the email regardless of its case.
     *
     * @return void
     */
    public function testMatchesEmailRegardlessOfCase(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);

        $this->login(['email' => 'User@EXAMPLE.com'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * Test that a wrong password, an unknown email or an unknown organization is rejected.
     *
     * @return void
     */
    public function testRejectsWrongCredentials(): void
    {
        $this->createUser('user@example.com', $this->acme);

        $cases = [
            ['email' => 'user@example.com', 'password' => 'wrong-password'],
            ['email' => 'nobody@example.com'],
            ['email' => 'user@example.com', 'organization' => 'missing'],
        ];

        foreach ($cases as $credentials) {
            $this->login($credentials)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['email' => __('auth.failed')]);
        }
    }

    /**
     * Test that the organization is required, when the credentials match accounts of several people.
     *
     * @return void
     */
    public function testRequiresOrganizationWhenEmailBelongsToSeveralPeople(): void
    {
        $this->createUser('user@example.com', $this->acme);
        $other = $this->createUser('user@example.com', $this->createOrganization('beta'));

        $this->login(['email' => 'user@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organization');

        $this->login(['email' => 'user@example.com', 'organization' => 'beta'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $other->id);
    }

    /**
     * Test that login picks the main account, when the credentials match accounts of one person.
     *
     * @return void
     */
    public function testLogsIntoMainAccountWhenEmailBelongsToOnePerson(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $this->createUser('user@example.com', $this->createOrganization('beta'), ['main_account_id' => $main->id]);

        $this->login(['email' => 'user@example.com'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $main->id);
    }

    /**
     * Test that the password of a linked account is checked against its main account, even when its own one
     * differs.
     *
     * @return void
     */
    public function testChecksPasswordOfMainAccount(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $linked = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);

        // Written past the model, so it isn't shared
        User::query()->whereKey($linked->id)->toBase()->update(['password' => Hash::make('stale-password')]);

        $this->login(['email' => 'user@example.com', 'organization' => 'beta'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $linked->id);

        $this->login(['email' => 'user@example.com', 'organization' => 'beta', 'password' => 'stale-password'])
            ->assertUnprocessable();
    }

    /**
     * Test that the organization isn't required, when the password matches only one of the accounts with the
     * email.
     *
     * @return void
     */
    public function testLogsIntoTheOnlyAccountWithMatchingPassword(): void
    {
        $this->createUser('user@example.com', $this->acme, ['password' => 'another-password']);
        $other = $this->createUser('user@example.com', $this->createOrganization('beta'));

        $this->login(['email' => 'user@example.com'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $other->id);
    }

    /**
     * Test that suspended users, and users of suspended or deactivated organizations, cannot log in, and neither
     * can users of deleted organizations, as they are deleted with them.
     *
     * @return void
     */
    public function testRejectsUsersWhoCannotSignIn(): void
    {
        $suspended = $this->createOrganization('beta', ['suspended_at' => now()]);
        $deactivated = $this->createOrganization('gamma', ['deactivated_at' => now()]);

        $this->createUser('suspended@example.com', $this->acme, ['suspended_at' => now()]);
        $this->createUser('suspended-org@example.com', $suspended);
        $this->createUser('deactivated-org@example.com', $deactivated);

        $deleted = $this->createOrganization('delta');
        $this->createUser('deleted-org@example.com', $deleted);
        $deleted->delete();

        foreach (['suspended', 'suspended-org', 'deactivated-org'] as $name) {
            $this->login(['email' => "$name@example.com"])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['email' => 'This account is suspended.']);
        }

        $this->login(['email' => 'deleted-org@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
    }

    /**
     * Test that the access tokens of a user stop working, once the user is suspended.
     *
     * @return void
     */
    public function testStopsAcceptingTokensOfSuspendedUser(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);
        $token = $this->login(['email' => 'user@example.com'])->json('data.access_token');

        $user->forceFill(['suspended_at' => now()])->save();

        $this->asBearer($token)
            ->getJson('/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Test that login attempts are limited by the configured rate limit.
     *
     * @return void
     */
    public function testThrottlesLoginAttempts(): void
    {
        config(['api-core.auth.rate_limit' => 2]);

        $this->createUser('user@example.com', $this->acme);

        $this->login(['email' => 'user@example.com', 'password' => 'wrong'])->assertUnprocessable();
        $this->login(['email' => 'user@example.com', 'password' => 'wrong'])->assertUnprocessable();
        $this->login(['email' => 'user@example.com'])->assertTooManyRequests();
    }

    /**
     * Test that a refresh token is exchanged for a new pair of tokens, and the old access token stops
     * working.
     *
     * @return void
     */
    public function testRefreshesTokens(): void
    {
        $this->createUser('user@example.com', $this->acme);
        $login = $this->login(['email' => 'user@example.com'])->json('data');

        $refreshed = $this->postJson('/auth/refresh', ['refresh_token' => $login['refresh_token']])
            ->assertOk()
            ->assertJsonMissingPath('data.user')
            ->json('data');

        $this->assertNotSame($login['access_token'], $refreshed['access_token']);
        $this->assertNotSame($login['refresh_token'], $refreshed['refresh_token']);

        $this->asBearer($login['access_token'])->getJson('/auth/me')->assertUnauthorized();
        $this->asBearer($refreshed['access_token'])->getJson('/auth/me')->assertOk();
    }

    /**
     * Test that a refresh token can be used only once.
     *
     * @return void
     */
    public function testRefreshTokenWorksOnce(): void
    {
        $this->createUser('user@example.com', $this->acme);
        $refreshToken = $this->login(['email' => 'user@example.com'])->json('data.refresh_token');

        $this->postJson('/auth/refresh', ['refresh_token' => $refreshToken])->assertOk();
        $this->postJson('/auth/refresh', ['refresh_token' => $refreshToken])->assertUnauthorized();
    }

    /**
     * Test that malformed, wrong and expired refresh tokens, and the ones of suspended users, are rejected.
     *
     * @return void
     */
    public function testRejectsInvalidRefreshTokens(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);
        $refreshToken = $this->login(['email' => 'user@example.com'])->json('data.refresh_token');
        [$id] = explode('|', $refreshToken);

        foreach (['invalid', "$id|wrong-secret", '0|' . str_repeat('a', 64), "$id|"] as $invalid) {
            $this->postJson('/auth/refresh', ['refresh_token' => $invalid])->assertUnauthorized();
        }

        $this->travel(20161)->minutes();
        $this->postJson('/auth/refresh', ['refresh_token' => $refreshToken])->assertUnauthorized();

        $this->travelBack();
        $user->forceFill(['suspended_at' => now()])->save();
        $this->postJson('/auth/refresh', ['refresh_token' => $refreshToken])->assertUnauthorized();
    }

    /**
     * Test that logout deletes the token, with which the request was made, along with its refresh token, but
     * keeps the other tokens of the user.
     *
     * @return void
     */
    public function testLogsOutOfTheCurrentTokenOnly(): void
    {
        Event::fake([Logout::class]);

        $user = $this->createUser('user@example.com', $this->acme);
        $first = $this->login(['email' => 'user@example.com'])->json('data');
        $second = $this->login(['email' => 'user@example.com'])->json('data');

        $this->asBearer($first['access_token'])
            ->postJson('/auth/logout')
            ->assertOk();

        $this->asBearer($first['access_token'])->getJson('/auth/me')->assertUnauthorized();
        $this->postJson('/auth/refresh', ['refresh_token' => $first['refresh_token']])->assertUnauthorized();
        $this->asBearer($second['access_token'])->getJson('/auth/me')->assertOk();

        Event::assertDispatched(Logout::class, fn (Logout $event) => $event->user->getAuthIdentifier() === $user->id);
    }

    /**
     * Test that the signed-in user is returned with the organization, roles, permissions and main account.
     *
     * @return void
     */
    public function testReturnsSignedInUser(): void
    {
        Role::findOrCreate(UserRole::User->value)->givePermissionTo(
            app(PermissionRepository::class)->create(User::class, 'view')
        );

        $main = $this->createUser('main@example.com');
        $user = $this->createUser('user@example.com', $this->acme, ['main_account_id' => $main->id]);
        $user->assignRole(UserRole::User->value);

        $token = $this->login(['email' => 'user@example.com'])->json('data.access_token');

        $this->asBearer($token)
            ->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'user@example.com')
            ->assertJsonPath('data.main_account_id', $main->id)
            ->assertJsonPath('data.organization', ['id' => $this->acme->id, 'slug' => 'acme', 'name' => 'Acme'])
            ->assertJsonPath('data.roles', [UserRole::User->value])
            ->assertJsonPath('data.permissions', ['view models.users'])
            ->assertJsonPath('data.impersonator', null)
            ->assertJsonMissingPath('data.password');
    }

    /**
     * Test that the signed-in user updates their own name, timezone and locale.
     *
     * @return void
     */
    public function testUpdatesOwnProfile(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);
        $token = $this->login(['email' => 'user@example.com'])->json('data.access_token');

        $this->asBearer($token)
            ->patchJson('/auth/me', ['name' => 'Renamed', 'timezone' => 'Europe/Kyiv', 'locale' => 'uk'])
            ->assertOk()
            ->assertJsonPath('message', 'Updated')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Renamed');

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertSame('Europe/Kyiv', $user->timezone);
        $this->assertSame('uk', $user->locale);
    }

    /**
     * Test that the own profile's update is validated, and changes neither the email nor the password.
     *
     * @return void
     */
    public function testOwnProfileUpdateIsValidatedAndLeavesEmailAndPassword(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);
        $token = $this->login(['email' => 'user@example.com'])->json('data.access_token');

        $this->asBearer($token)
            ->patchJson('/auth/me', ['timezone' => 'Mars/Olympus', 'name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone', 'name']);

        $this->asBearer($token)
            ->patchJson('/auth/me', ['email' => 'new@example.com', 'password' => 'new-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields');

        $this->assertSame('user@example.com', $user->fresh()->email);
        $this->assertTrue(Hash::check('password123', $user->fresh()->getAuthPassword()));
    }

    /**
     * Test that the `me`, `logout` and `switch` routes require authentication.
     *
     * @return void
     */
    public function testRequiresAuthentication(): void
    {
        $this->getJson('/auth/me')->assertUnauthorized();
        $this->patchJson('/auth/me', ['name' => 'Renamed'])->assertUnauthorized();
        $this->postJson('/auth/logout')->assertUnauthorized();
        $this->postJson('/auth/switch', ['user_id' => 1])->assertUnauthorized();
    }

    /**
     * Test that a main account switches to its linked account, getting a token, on which it is recorded as
     * the impersonator.
     *
     * @return void
     */
    public function testMainAccountSwitchesToLinkedAccount(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $beta = $this->createOrganization('beta');
        $linked = $this->createUser('user@example.com', $beta, ['main_account_id' => $main->id]);

        $token = $this->login(['email' => 'user@example.com'])->json('data.access_token');

        $switched = $this->asBearer($token)
            ->postJson('/auth/switch', ['user_id' => $linked->id])
            ->assertOk()
            ->assertJsonPath('data.user.id', $linked->id)
            ->assertJsonPath('data.user.impersonator.id', $main->id)
            ->assertJsonMissingPath('data.refresh_token')
            ->json('data.access_token');

        $accessToken = AccessToken::findToken($switched);

        $this->assertSame($main->id, $accessToken->impersonator_id);
        $this->assertSame($beta->id, $accessToken->organization_id);

        $this->asBearer($switched)
            ->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $linked->id)
            ->assertJsonPath('data.impersonator.email', 'user@example.com');
    }

    /**
     * Test that a linked account cannot switch to its main account or to accounts of other people.
     *
     * @return void
     */
    public function testCannotSwitchToAccountsOfOthers(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $this->createUser('linked@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);
        $stranger = $this->createUser('stranger@example.com', $this->acme);

        $token = $this->login(['email' => 'linked@example.com'])->json('data.access_token');

        $this->asBearer($token)->postJson('/auth/switch', ['user_id' => $main->id])->assertForbidden();
        $this->asBearer($token)->postJson('/auth/switch', ['user_id' => $stranger->id])->assertForbidden();
    }

    /**
     * Test that a token, which was issued by switching, cannot switch back to the impersonator or on to the
     * impersonator's other accounts, as the client keeps the impersonator's own token for that.
     *
     * @return void
     */
    public function testSwitchedTokenCannotSwitchBackOrOn(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $beta = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);
        $gamma = $this->createUser('user@example.com', $this->createOrganization('gamma'), [
            'main_account_id' => $main->id,
        ]);

        $token = $this->login(['email' => 'user@example.com'])->json('data.access_token');
        $switched = $this->asBearer($token)
            ->postJson('/auth/switch', ['user_id' => $beta->id])
            ->json('data.access_token');

        $this->asBearer($switched)->postJson('/auth/switch', ['user_id' => $main->id])->assertForbidden();
        $this->asBearer($switched)->postJson('/auth/switch', ['user_id' => $gamma->id])->assertForbidden();
    }

    /**
     * Test that a super admin switches to any account.
     *
     * @return void
     */
    public function testSuperAdminSwitchesToAnyAccount(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com');
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $user = $this->createUser('user@example.com', $this->acme);

        $token = $this->login(['email' => 'admin@example.com'])->json('data.access_token');

        $this->asBearer($token)
            ->postJson('/auth/switch', ['user_id' => $user->id])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.impersonator.id', $superAdmin->id);
    }

    /**
     * Test that a token, which a super admin switched to a main account, can't switch on to the linked account,
     * although the main account may: the next token would name the main account as its impersonator, so it would
     * outlive the super admin's access.
     *
     * @return void
     */
    public function testSwitchedTokenCannotSwitchOnAsItsAccount(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com');
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $main = $this->createUser('user@example.com', $this->acme);
        $linked = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
        ]);

        $token = $this->login(['email' => 'admin@example.com'])->json('data.access_token');
        $switched = $this->asBearer($token)
            ->postJson('/auth/switch', ['user_id' => $main->id])
            ->json('data.access_token');

        $this->asBearer($switched)
            ->postJson('/auth/switch', ['user_id' => $linked->id])
            ->assertForbidden()
            ->assertJsonPath('message', 'A switched account may not switch again.');

        $this->assertSame(0, $linked->tokens()->count());
    }

    /**
     * Test that a token, which a super admin switched to another account, works only while the super admin can
     * sign in, as the super admin's own tokens do.
     *
     * @return void
     */
    public function testStopsAcceptingSwitchedTokensOfUsersWhoCannotSignIn(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com');
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $user = $this->createUser('user@example.com', $this->acme);

        $token = $this->login(['email' => 'admin@example.com'])->json('data.access_token');
        $switched = $this->asBearer($token)
            ->postJson('/auth/switch', ['user_id' => $user->id])
            ->json('data.access_token');

        $this->asBearer($switched)->getJson('/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);

        $superAdmin->forceFill(['suspended_at' => now()])->save();
        $this->asBearer($switched)->getJson('/auth/me')->assertUnauthorized();

        $superAdmin->forceFill(['suspended_at' => null])->save();
        $this->asBearer($switched)->getJson('/auth/me')->assertOk();

        $superAdmin->delete();
        $this->asBearer($switched)->getJson('/auth/me')->assertUnauthorized();
    }

    /**
     * Test that switching to the current, a missing or a suspended account is rejected.
     *
     * @return void
     */
    public function testValidatesAccountToSwitchTo(): void
    {
        $main = $this->createUser('user@example.com', $this->acme);
        $suspended = $this->createUser('user@example.com', $this->createOrganization('beta'), [
            'main_account_id' => $main->id,
            'suspended_at' => now(),
        ]);

        $token = $this->login(['email' => 'user@example.com', 'organization' => 'acme'])->json('data.access_token');

        $cases = [
            ['user_id' => $main->id, 'error' => 'You are already signed in to this account.'],
            ['user_id' => 999, 'error' => null],
            ['user_id' => $suspended->id, 'error' => 'This account is suspended.'],
        ];

        foreach ($cases as $case) {
            $response = $this->asBearer($token)
                ->postJson('/auth/switch', ['user_id' => $case['user_id']])
                ->assertUnprocessable();

            if ($case['error'] !== null) {
                $response->assertJsonValidationErrors(['user_id' => $case['error']]);
            }
        }
    }
}
