<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Http\Controllers\Auth\AuthController;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Tests\TestCase;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Uri;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\NewAccessToken;

/**
 * Class AuthControllerPasswordResetTest.
 *
 * Resetting a forgotten password with a link, which is emailed to the user.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers\Auth
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthControllerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Message, with which every request for a link is answered.
     *
     * @var string
     */
    private const string LINK_MESSAGE
        = 'If the email belongs to an account, a link to reset the password has been sent to it.';

    /**
     * Error of a reset with a wrong or expired token, or an unknown email.
     *
     * @var string
     */
    private const string INVALID_TOKEN_MESSAGE = 'This password reset token is invalid.';

    /**
     * Organization, in which the users are created by default.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Register the authentication routes, set the SPA's password reset page and create the default organization.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('auth')->group(fn () => AuthController::routes());

        config(['api-core.auth.password_reset_url' => 'https://spa.test/reset-password']);

        $this->acme = Organization::factory()->create(['slug' => 'acme']);

        Notification::fake();
    }

    /**
     * Create a user with the email, whose password is `password123`, in the default organization. The email
     * isn't verified, so the accounts aren't linked by it automatically.
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
                'organization_id' => ($organization ?? $this->acme)->id,
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
     * Request a link to reset the password of the email.
     *
     * @param string $email
     *
     * @return TestResponse
     */
    private function forgotPassword(string $email): TestResponse
    {
        return $this->postJson('/auth/forgot-password', ['email' => $email]);
    }

    /**
     * Send a password reset request, in which the new password is `new-password` by default.
     *
     * @param array<string, mixed> $data
     *
     * @return TestResponse
     */
    private function resetPassword(array $data): TestResponse
    {
        return $this->postJson('/auth/reset-password', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            ...$data,
        ]);
    }

    /**
     * Get the token from the password reset link, which was emailed to the user.
     *
     * @param User $user
     *
     * @return string
     */
    private function tokenSentTo(User $user): string
    {
        $token = null;

        Notification::assertSentTo($user, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        return $token;
    }

    /**
     * Assert that the stored password of each account, deleted ones included, is the given one.
     *
     * @param string $password
     * @param User ...$accounts
     *
     * @return void
     */
    private function assertPassword(string $password, User ...$accounts): void
    {
        foreach ($accounts as $account) {
            $this->assertTrue(
                Hash::check($password, $account->fresh()->password),
                "Account $account->id doesn't have the password `$password`."
            );
        }
    }

    /**
     * Test that a link to the SPA's password reset page, with the token and the email in its query, is emailed.
     *
     * @return void
     */
    public function testEmailsLinkToResetPassword(): void
    {
        $user = $this->createUser('bob@example.com');

        $this->forgotPassword('Bob@Example.com')
            ->assertOk()
            ->assertJsonPath('message', self::LINK_MESSAGE);

        Notification::assertSentTo($user, function (ResetPassword $notification) use ($user) {
            $url = Uri::of($notification->toMail($user)->actionUrl);

            return $url->scheme() === 'https'
                && $url->host() === 'spa.test'
                && $url->path() === 'reset-password'
                && $url->query()->all() === ['token' => $notification->token, 'email' => 'bob@example.com'];
        });
    }

    /**
     * Test that the response doesn't tell, whether a link was sent: it is the same for an unknown or deleted
     * account's email, and when a link was sent to the email shortly before.
     *
     * @return void
     */
    public function testRespondsAlikeWhetherOrNotLinkIsSent(): void
    {
        $user = $this->createUser('bob@example.com');
        $this->createUser('alice@example.com')->delete();

        foreach (['carol@example.com', 'alice@example.com', 'bob@example.com', 'bob@example.com'] as $email) {
            $this->forgotPassword($email)
                ->assertOk()
                ->assertJsonPath('message', self::LINK_MESSAGE);
        }

        Notification::assertSentTimes(ResetPassword::class, 1);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    /**
     * Test that the email to send a link to is validated.
     *
     * @return void
     */
    public function testValidatesEmailToSendLinkTo(): void
    {
        $this->postJson('/auth/forgot-password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->forgotPassword('not-an-email')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }

    /**
     * Test that the token from the emailed link resets the password once, after which only the new password
     * signs in.
     *
     * @return void
     */
    public function testResetsPasswordWithTokenFromLink(): void
    {
        $user = $this->createUser('bob@example.com');

        Event::fake([PasswordReset::class]);

        $this->forgotPassword('bob@example.com')->assertOk();
        $token = $this->tokenSentTo($user);

        $this->resetPassword(['email' => 'BOB@example.com', 'token' => $token])
            ->assertOk()
            ->assertJsonPath('message', 'Your password has been reset.');

        Event::assertDispatched(
            PasswordReset::class,
            fn (PasswordReset $event) => $event->user->getAuthIdentifier() === $user->id
        );

        $this->postJson('/auth/login', ['email' => 'bob@example.com', 'password' => 'password123'])
            ->assertUnprocessable();
        $this->postJson('/auth/login', ['email' => 'bob@example.com', 'password' => 'new-password'])
            ->assertOk();

        $this->resetPassword([
            'email' => 'bob@example.com',
            'token' => $token,
            'password' => 'newer-password',
            'password_confirmation' => 'newer-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => self::INVALID_TOKEN_MESSAGE]);

        $this->assertPassword('new-password', $user);
    }

    /**
     * Test that the password of every person, who has an account with the email, is reset on all of their
     * accounts, the ones with other emails and deleted ones included, but not the password of other people.
     *
     * @return void
     */
    public function testResetsPasswordOfEveryPersonWithTheEmail(): void
    {
        $globex = Organization::factory()->create(['slug' => 'globex']);

        $main = $this->createUser('bob@example.com');
        $linked = $this->createUser('robert@example.com', $globex, ['main_account_id' => $main->id]);
        $deleted = $this->createUser('robert@example.com', $this->acme, ['main_account_id' => $main->id]);
        $deleted->delete();
        $other = $this->createUser('bob@example.com', $globex);
        $otherLinked = $this->createUser('bobby@example.com', $this->acme, ['main_account_id' => $other->id]);
        $stranger = $this->createUser('alice@example.com');

        Event::fake([PasswordReset::class]);

        $this->resetPassword(['email' => 'bob@example.com', 'token' => Password::createToken($main)])
            ->assertOk();

        $this->assertPassword('new-password', $main, $linked, $deleted, $other, $otherLinked);
        $this->assertPassword('password123', $stranger);

        Event::assertDispatchedTimes(PasswordReset::class, 2);
    }

    /**
     * Test that a reset deletes the access tokens of every account of the people, whose password it sets, and
     * the tokens, with which they switched to other accounts, but not the tokens of other people.
     *
     * @return void
     */
    public function testResetDeletesAccessTokensOfThePeople(): void
    {
        $globex = Organization::factory()->create(['slug' => 'globex']);

        $main = $this->createUser('bob@example.com');
        $linked = $this->createUser('robert@example.com', $globex, ['main_account_id' => $main->id]);
        $other = $this->createUser('bob@example.com', $globex);
        $stranger = $this->createUser('alice@example.com');

        $deleted = [
            $this->createAccessToken($main),
            $this->createAccessToken($linked),
            $this->createAccessToken($other),
            $this->createAccessToken($stranger, $main),
        ];
        $kept = $this->createAccessToken($stranger);

        $this->resetPassword(['email' => 'bob@example.com', 'token' => Password::createToken($main)])
            ->assertOk();

        foreach ($deleted as $token) {
            $this->assertModelMissing($token->accessToken);
        }

        $this->assertModelExists($kept->accessToken);

        $this->withToken($deleted[0]->plainTextToken)
            ->getJson('/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Test that a wrong or expired token, or an unknown email, doesn't reset the password, and they get the same
     * error, so it doesn't tell, which emails have accounts.
     *
     * @return void
     */
    public function testRejectsInvalidResetTokens(): void
    {
        $user = $this->createUser('bob@example.com');
        $token = Password::createToken($user);

        $invalid = [
            ['email' => 'bob@example.com', 'token' => 'wrong-token'],
            ['email' => 'alice@example.com', 'token' => $token],
        ];

        foreach ($invalid as $data) {
            $this->resetPassword($data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['email' => self::INVALID_TOKEN_MESSAGE]);
        }

        $this->travel(61)->minutes();

        $this->resetPassword(['email' => 'bob@example.com', 'token' => $token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => self::INVALID_TOKEN_MESSAGE]);

        $this->assertPassword('password123', $user);
    }

    /**
     * Test that a reset request is validated, the new password with its confirmation and the default rules.
     *
     * @return void
     */
    public function testValidatesResetRequest(): void
    {
        $user = $this->createUser('bob@example.com');
        $token = Password::createToken($user);

        $this->postJson('/auth/reset-password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'email', 'password']);

        $this->resetPassword(['email' => 'bob@example.com', 'token' => $token, 'password_confirmation' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->resetPassword([
            'email' => 'bob@example.com',
            'token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertPassword('password123', $user);
    }

    /**
     * Test that requests for links and resets are throttled by the rate limit of an email and IP address,
     * which they share, while other emails aren't.
     *
     * @return void
     */
    public function testThrottlesPasswordResetRequests(): void
    {
        config(['api-core.auth.rate_limit' => 2]);

        $this->forgotPassword('bob@example.com')->assertOk();
        $this->forgotPassword('bob@example.com')->assertOk();
        $this->forgotPassword('bob@example.com')->assertTooManyRequests();

        $this->resetPassword(['email' => 'bob@example.com', 'token' => 'token'])
            ->assertTooManyRequests();

        $this->forgotPassword('alice@example.com')->assertOk();
    }
}
