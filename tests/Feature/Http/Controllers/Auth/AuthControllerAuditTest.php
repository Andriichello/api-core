<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Enums\UserRole;
use Core\Http\Controllers\Auth\AuthController;
use Core\Models\AuditLog;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

/**
 * Class AuthControllerAuditTest.
 *
 * Authentication events, which are recorded in the audit log, as `api-core.audit.auth_events` is on.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers\Auth
 */
class AuthControllerAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Organization of the users.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Register the authentication routes, turn the recording of authentication events on, and create the
     * organization.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('auth')->group(fn () => AuthController::routes());

        config(['api-core.audit.auth_events' => true]);

        $this->acme = Organization::factory()->create();
    }

    /**
     * Create a user with the email in the organization, whose password is `password123`.
     *
     * @param string $email
     * @param Organization|null $organization
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createUser(string $email, ?Organization $organization = null, array $attributes = []): User
    {
        return User::factory()->create([
            'organization_id' => $organization?->id,
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
     * Authenticate the next request with the access token, forgetting the user, which the guard remembers.
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
     * Get the recorded entry of the authentication event.
     *
     * @param string $event
     *
     * @return AuditLog
     */
    private function entryOf(string $event): AuditLog
    {
        /** @var AuditLog $entry */
        $entry = AuditLog::query()
            ->where('source', AuditLog::SOURCE_AUTH)
            ->where('event', $event)
            ->sole();

        return $entry;
    }

    /**
     * Test that a login and a logout are recorded on the user, with the IP address and the user agent.
     *
     * @return void
     */
    public function testRecordsLoginAndLogout(): void
    {
        $user = $this->createUser('user@example.com', $this->acme);

        $token = $this->withHeader('User-Agent', 'Phone')
            ->login(['email' => 'user@example.com'])
            ->json('data.access_token');

        $login = $this->entryOf('login');

        $this->assertTrue($user->is($login->subject));
        $this->assertTrue($user->is($login->causer));
        $this->assertSame($this->acme->id, $login->organization_id);
        $this->assertSame('127.0.0.1', $login->metadata['ip']);
        $this->assertSame('Phone', $login->metadata['user_agent']);

        $this->asBearer($token)->postJson('/auth/logout')->assertOk();

        $this->assertTrue($user->is($this->entryOf('logout')->subject));
    }

    /**
     * Test that failed logins are recorded with the email, but never the password: on the account, which can't
     * sign in, and on nothing, when no account matches.
     *
     * @return void
     */
    public function testRecordsFailedLogins(): void
    {
        $suspended = $this->createUser('suspended@example.com', $this->acme, ['suspended_at' => now()]);

        $this->login(['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertUnprocessable();
        $this->login(['email' => 'suspended@example.com'])->assertUnprocessable();

        /** @var AuditLog $unknown */
        $unknown = AuditLog::query()->where('event', 'login_failed')->whereNull('subject_id')->sole();

        $this->assertNull($unknown->causer_id);
        $this->assertSame('nobody@example.com', $unknown->metadata['email']);
        $this->assertStringNotContainsString('wrong-password', (string) json_encode($unknown->metadata));

        /** @var AuditLog $refused */
        $refused = AuditLog::query()->where('event', 'login_failed')->where('subject_id', $suspended->id)->sole();

        $this->assertSame('suspended@example.com', $refused->metadata['email']);
        $this->assertSame($this->acme->id, $refused->organization_id);
    }

    /**
     * Test that a switch to another account is recorded on that account, as caused by the user, who switched.
     *
     * @return void
     */
    public function testRecordsSwitchToAnotherAccount(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com');
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        $user = $this->createUser('user@example.com', $this->acme);

        $token = $this->login(['email' => 'admin@example.com'])->json('data.access_token');
        $switched = $this->asBearer($token)
            ->postJson('/auth/switch', ['user_id' => $user->id])
            ->assertOk()
            ->json('data.access_token');

        $entry = $this->entryOf('switched');

        $this->assertTrue($user->is($entry->subject));
        $this->assertTrue($superAdmin->is($entry->causer));
        $this->assertSame($superAdmin->id, $entry->metadata['impersonator_id']);

        $this->asBearer($switched)->postJson('/auth/logout')->assertOk();

        $this->assertSame($superAdmin->id, $this->entryOf('logout')->metadata['impersonator_id']);
    }

    /**
     * Test that nothing is recorded, when the recording of authentication events is off.
     *
     * @return void
     */
    public function testRecordsNothingWhenOff(): void
    {
        config(['api-core.audit.auth_events' => false]);

        $this->createUser('user@example.com', $this->acme);

        $this->login(['email' => 'user@example.com'])->assertOk();
        $this->login(['email' => 'user@example.com', 'password' => 'wrong-password'])->assertUnprocessable();

        $this->assertSame(0, AuditLog::query()->count());
    }
}
