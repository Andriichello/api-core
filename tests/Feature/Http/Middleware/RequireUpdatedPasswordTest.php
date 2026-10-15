<?php

namespace Core\Tests\Feature\Http\Middleware;

use Core\Enums\UserRole;
use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Middleware\RequireUpdatedPassword;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

/**
 * Class RequireUpdatedPasswordTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Middleware
 */
class RequireUpdatedPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register the authentication routes and the routes, which require an updated password, by the middleware's
     * alias.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('auth')->group(fn () => AuthController::routes());

        Route::middleware(['auth:sanctum', RequireUpdatedPassword::ALIAS])->get('protected', fn () => 'OK');
        Route::middleware(RequireUpdatedPassword::ALIAS)->get('public', fn () => 'OK');
    }

    /**
     * Create a user, whose password is `password123`, set by the person, unless the attributes say otherwise.
     *
     * @param string $email
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createUser(string $email, array $attributes = []): User
    {
        return User::factory()->create(['email' => $email, 'password' => 'password123', ...$attributes]);
    }

    /**
     * Create a super admin, whose password is set by the person, unless the attributes say otherwise.
     *
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createSuperAdmin(array $attributes = []): User
    {
        Role::findOrCreate(UserRole::SuperAdmin->value);

        $superAdmin = $this->createUser('admin@example.com', $attributes);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        return $superAdmin;
    }

    /**
     * Send a request to the protected route with an access token of the user, on which the impersonator is
     * recorded, when it is given. The guard remembers the user between requests of the same test, so it is
     * forgotten first.
     *
     * @param User $user
     * @param User|null $impersonator
     *
     * @return TestResponse
     */
    private function getProtectedAs(User $user, ?User $impersonator = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        $token = app(AccessTokenRepository::class)->create($user, 'Test', $impersonator);

        return $this->withToken($token->plainTextToken)->getJson('/protected');
    }

    /**
     * Test that a user, whose password was set by someone else, is refused, but may still get themselves, which
     * tells the client to ask for a new password.
     *
     * @return void
     */
    public function testRefusesUserWhosePasswordWasSetBySomeoneElse(): void
    {
        $user = $this->createUser('bob@example.com', ['password_updated_at' => null]);

        $this->getProtectedAs($user)
            ->assertForbidden()
            ->assertJsonPath('message', RequireUpdatedPassword::MESSAGE);

        $this->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('data.is_password_update_required', true)
            ->assertJsonPath('data.password_updated_at', null);
    }

    /**
     * Test that a user, who set the password, and a guest on a route, which doesn't require signing in, are let
     * through.
     *
     * @return void
     */
    public function testLetsThroughUserWhoSetThePasswordAndGuests(): void
    {
        $user = $this->createUser('bob@example.com');

        $this->getProtectedAs($user)->assertOk();
        $this->getJson('/auth/me')->assertJsonPath('data.is_password_update_required', false);

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/public')->assertOk();
    }

    /**
     * Test that resetting the password with the emailed token counts as the person setting it.
     *
     * @return void
     */
    public function testPasswordResetLiftsTheRequirement(): void
    {
        $user = $this->createUser('bob@example.com', ['password_updated_at' => null]);

        $this->postJson('/auth/reset-password', [
            'email' => 'bob@example.com',
            'token' => Password::createToken($user),
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->getProtectedAs($user->fresh())->assertOk();
    }

    /**
     * Test that, when a user has switched to the account, it is the password of that user, who is behind the
     * request, which is checked.
     *
     * @return void
     */
    public function testChecksPasswordOfUserWhoSwitched(): void
    {
        $required = $this->createUser('bob@example.com', ['password_updated_at' => null]);
        $updated = $this->createUser('alice@example.com');

        $this->getProtectedAs($required, $this->createSuperAdmin())->assertOk();

        $superAdmin = User::query()->where('email', 'admin@example.com')->first();
        $superAdmin->forceFill(['password' => 'set-by-admin'])->save();

        $this->getProtectedAs($updated, $superAdmin)
            ->assertForbidden()
            ->assertJsonPath('message', RequireUpdatedPassword::MESSAGE);
    }

    /**
     * Test that a person, who must set a new password, doesn't get around it by switching to their own linked
     * account.
     *
     * @return void
     */
    public function testSwitchingToOwnLinkedAccountKeepsTheRequirement(): void
    {
        $main = $this->createUser('bob@example.com', ['password_updated_at' => null]);
        $linked = $this->createUser('bob@example.com', [
            'organization_id' => Organization::factory()->create()->id,
            'main_account_id' => $main->id,
        ]);

        $this->getProtectedAs($linked, $main)
            ->assertForbidden()
            ->assertJsonPath('message', RequireUpdatedPassword::MESSAGE);
    }
}
