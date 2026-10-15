<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Http\Controllers\Auth\AuthController;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\NewAccessToken;

/**
 * Class AuthControllerChangePasswordTest.
 *
 * Changing the password of the signed-in user, who knows the current one.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers\Auth
 */
class AuthControllerChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register the authentication routes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('auth')->group(fn () => AuthController::routes());
    }

    /**
     * Create a user, whose password is `password123`, in a new organization.
     *
     * @param string $email
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createUser(string $email, array $attributes = []): User
    {
        return User::factory()
            ->unverified()
            ->create([
                'organization_id' => Organization::factory()->create()->id,
                'email' => $email,
                'password' => 'password123',
                ...$attributes,
            ]);
    }

    /**
     * Create an access token for the user, on which the impersonator is recorded, when it is given.
     *
     * @param User $user
     * @param User|null $impersonator
     *
     * @return NewAccessToken
     */
    private function createAccessToken(User $user, ?User $impersonator = null): NewAccessToken
    {
        return app(AccessTokenRepository::class)->create($user, 'Test', $impersonator);
    }

    /**
     * Change the password with the access token. The new password is `new-password` by default. The guard
     * remembers the user between requests of the same test, so it is forgotten first.
     *
     * @param NewAccessToken $token
     * @param array<string, mixed> $data
     *
     * @return TestResponse
     */
    private function changePassword(NewAccessToken $token, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token->plainTextToken)->postJson('/auth/change-password', [
            'current_password' => 'password123',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            ...$data,
        ]);
    }

    /**
     * Test that the password is changed for the person, on all of their accounts, as updated by them, after
     * which only the new password signs in.
     *
     * @return void
     */
    public function testChangesPasswordOfThePerson(): void
    {
        $main = $this->createUser('bob@example.com', ['password_updated_at' => null]);
        $linked = $this->createUser('robert@example.com', ['main_account_id' => $main->id]);
        $stranger = $this->createUser('alice@example.com');

        $this->changePassword($this->createAccessToken($linked))
            ->assertOk()
            ->assertJsonPath('message', 'Your password has been changed.');

        foreach ([$main, $linked] as $account) {
            $account->refresh();

            $this->assertTrue(Hash::check('new-password', $account->password));
            $this->assertFalse($account->isPasswordUpdateRequired());
        }

        $this->assertTrue(Hash::check('password123', $stranger->fresh()->password));

        $this->app['auth']->forgetGuards();
        $this->withoutToken();

        $this->postJson('/auth/login', ['email' => 'bob@example.com', 'password' => 'password123'])
            ->assertUnprocessable();
        $this->postJson('/auth/login', ['email' => 'bob@example.com', 'password' => 'new-password'])
            ->assertOk();
    }

    /**
     * Test that the person's other tokens, and the tokens, with which they switched to other accounts, are
     * deleted, but the token, with which the password was changed, and the tokens of other people stay.
     *
     * @return void
     */
    public function testSignsOutOtherTokensOfThePerson(): void
    {
        $main = $this->createUser('bob@example.com');
        $linked = $this->createUser('robert@example.com', ['main_account_id' => $main->id]);
        $stranger = $this->createUser('alice@example.com');

        $current = $this->createAccessToken($linked, $main);
        $deleted = [
            $this->createAccessToken($main),
            $this->createAccessToken($linked),
            $this->createAccessToken($stranger, $main),
        ];
        $kept = $this->createAccessToken($stranger);

        $this->changePassword($current)->assertOk();

        foreach ($deleted as $token) {
            $this->assertModelMissing($token->accessToken);
        }

        $this->assertModelExists($current->accessToken);
        $this->assertModelExists($kept->accessToken);

        $this->app['auth']->forgetGuards();

        $this->withToken($current->plainTextToken)
            ->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $linked->id);
    }

    /**
     * Test that the current password must be the person's one, so a user, who switched to the account of
     * another person, can't change it without knowing it.
     *
     * @return void
     */
    public function testRejectsWrongCurrentPassword(): void
    {
        $user = $this->createUser('bob@example.com');
        $other = $this->createUser('alice@example.com', ['password' => 'other-password']);

        $this->changePassword($this->createAccessToken($user), ['current_password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->changePassword($this->createAccessToken($other, $user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertTrue(Hash::check('other-password', $other->fresh()->password));
    }

    /**
     * Test that the new password is confirmed, follows the default rules and differs from the current one.
     *
     * @return void
     */
    public function testValidatesNewPassword(): void
    {
        $user = $this->createUser('bob@example.com', ['password_updated_at' => null]);
        $token = $this->createAccessToken($user);

        $this->changePassword($token, ['password' => null, 'current_password' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password']);

        $invalid = [
            ['password_confirmation' => 'other-password'],
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => 'password123', 'password_confirmation' => 'password123'],
        ];

        foreach ($invalid as $data) {
            $this->changePassword($token, $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['password']);
        }

        $this->assertTrue($user->fresh()->isPasswordUpdateRequired());
    }

    /**
     * Test that only a signed-in user changes the password, and the attempts are throttled.
     *
     * @return void
     */
    public function testRequiresSignInAndThrottles(): void
    {
        $this->postJson('/auth/change-password')->assertUnauthorized();

        config(['api-core.auth.rate_limit' => 2]);

        $token = $this->createAccessToken($this->createUser('bob@example.com'));

        $this->changePassword($token, ['current_password' => 'wrong-password'])->assertUnprocessable();
        $this->changePassword($token, ['current_password' => 'wrong-password'])->assertUnprocessable();
        $this->changePassword($token)->assertTooManyRequests();
    }
}
