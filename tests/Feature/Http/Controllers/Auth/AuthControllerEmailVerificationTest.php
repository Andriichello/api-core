<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Requests\Auth\VerifyEmailRequest;
use Core\Models\Organization;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Tests\TestCase;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Uri;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\NewAccessToken;

/**
 * Class AuthControllerEmailVerificationTest.
 *
 * Verifying an email with a link, which is emailed to it.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers\Auth
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthControllerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Error of a link, which is invalid or has expired.
     *
     * @var string
     */
    private const string INVALID_LINK_MESSAGE = 'The verification link is invalid or has expired.';

    /**
     * Organization, in which the users are created by default.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Register the authentication routes and a route for verified users only, set the SPA's email
     * verification page and create the default organization.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('auth')->group(fn () => AuthController::routes());
        Route::middleware(['auth:sanctum', 'verified'])->get('verified-only', fn () => 'OK');

        config(['api-core.auth.email_verification_url' => 'https://spa.test/verify-email']);

        $this->acme = Organization::factory()->create(['slug' => 'acme']);

        Notification::fake();
    }

    /**
     * Create a user with the email, whose password is `password123`, in the default organization. The email
     * isn't verified by default.
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
     * Create an access token for the user.
     *
     * @param User $user
     *
     * @return NewAccessToken
     */
    private function createAccessToken(User $user): NewAccessToken
    {
        return app(AccessTokenRepository::class)->create($user, 'Test');
    }

    /**
     * Authenticate the next requests with a new access token of the user. The guard remembers the user between
     * requests of the same test, so it is forgotten first.
     *
     * @param User $user
     *
     * @return static
     */
    private function signedInAs(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->createAccessToken($user)->plainTextToken);
    }

    /**
     * Send the next requests without signing in.
     *
     * @return static
     */
    private function signedOut(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken();
    }

    /**
     * Request a link to verify the email of the signed-in user.
     *
     * @return TestResponse
     */
    private function sendVerificationEmail(): TestResponse
    {
        return $this->postJson('/auth/email/verification-notification');
    }

    /**
     * Verify an email with the parameters of the link.
     *
     * @param array<string, mixed> $parameters
     *
     * @return TestResponse
     */
    private function verifyEmail(array $parameters): TestResponse
    {
        return $this->postJson('/auth/verify-email', $parameters);
    }

    /**
     * Get the parameters of the verification link, which was emailed to the user.
     *
     * @param User $user
     *
     * @return array<string, mixed>
     */
    private function linkParametersSentTo(User $user): array
    {
        $parameters = [];

        Notification::assertSentTo($user, function (VerifyEmail $notification) use ($user, &$parameters) {
            $parameters = Uri::of($notification->toMail($user)->actionUrl)->query()->all();

            return true;
        });

        return $parameters;
    }

    /**
     * Test that a link to the SPA's email verification page, with the signed parameters in its query, is
     * emailed to the signed-in user.
     *
     * @return void
     */
    public function testEmailsLinkToVerifyEmail(): void
    {
        $user = $this->createUser('bob@example.com');

        $this->signedInAs($user)
            ->sendVerificationEmail()
            ->assertOk()
            ->assertJsonPath('message', 'A link to verify your email has been sent to it.');

        Notification::assertSentTo($user, function (VerifyEmail $notification) use ($user) {
            $url = Uri::of($notification->toMail($user)->actionUrl);

            return $url->scheme() === 'https'
                && $url->host() === 'spa.test'
                && $url->path() === 'verify-email'
                && array_keys($url->query()->all()) === ['id', 'hash', 'expires', 'signature']
                && (int) $url->query()->get('id') === $user->id;
        });
    }

    /**
     * Test that no link is emailed, when the email is verified already.
     *
     * @return void
     */
    public function testDoesNotEmailLinkToVerifiedEmail(): void
    {
        $user = $this->createUser('bob@example.com', null, ['email_verified_at' => now()]);

        $this->signedInAs($user)
            ->sendVerificationEmail()
            ->assertOk()
            ->assertJsonPath('message', 'Your email is already verified.');

        Notification::assertNothingSent();
    }

    /**
     * Test that only a signed-in user gets a link.
     *
     * @return void
     */
    public function testRequiresSignInToEmailLink(): void
    {
        $this->sendVerificationEmail()->assertUnauthorized();

        Notification::assertNothingSent();
    }

    /**
     * Test that the emailed link verifies the email without signing in, firing the Verified event once.
     *
     * @return void
     */
    public function testVerifiesEmailWithLink(): void
    {
        $user = $this->createUser('bob@example.com');

        Event::fake([Verified::class]);

        $this->signedInAs($user)->sendVerificationEmail()->assertOk();
        $parameters = $this->linkParametersSentTo($user);

        $this->signedOut()
            ->verifyEmail($parameters)
            ->assertOk()
            ->assertJsonPath('message', 'Your email has been verified.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->verifyEmail($parameters)
            ->assertOk()
            ->assertJsonPath('message', 'Your email is already verified.');

        Event::assertDispatchedTimes(Verified::class, 1);
        Event::assertDispatched(
            Verified::class,
            fn (Verified $event) => $event->user instanceof User && $event->user->id === $user->id
        );
    }

    /**
     * Test that a link, whose parameters are changed, which has expired, or whose email has changed since, doesn't
     * verify the email.
     *
     * @return void
     */
    public function testRejectsInvalidLinks(): void
    {
        $user = $this->createUser('bob@example.com');
        $other = $this->createUser('alice@example.com');
        $parameters = VerifyEmailRequest::linkParameters($user);

        $invalid = [
            ['signature' => str_repeat('0', 64)],
            ['id' => $other->id],
            ['hash' => sha1('alice@example.com')],
            ['expires' => $parameters['expires'] + 3600],
        ];

        foreach ($invalid as $changes) {
            $this->verifyEmail([...$parameters, ...$changes])
                ->assertForbidden()
                ->assertJsonPath('message', self::INVALID_LINK_MESSAGE);
        }

        $this->travel(61)->minutes();

        $this->verifyEmail($parameters)
            ->assertForbidden()
            ->assertJsonPath('message', self::INVALID_LINK_MESSAGE);

        $this->travelBack();

        $user->forceFill(['email' => 'robert@example.com'])->save();

        $this->verifyEmail($parameters)
            ->assertForbidden()
            ->assertJsonPath('message', self::INVALID_LINK_MESSAGE);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    /**
     * Test that a link, which is signed with a previous key of the application, still works after the key is
     * rotated.
     *
     * @return void
     */
    public function testAcceptsLinksSignedWithPreviousKey(): void
    {
        $user = $this->createUser('bob@example.com');
        $parameters = VerifyEmailRequest::linkParameters($user);

        config([
            'app.previous_keys' => [config('app.key')],
            'app.key' => 'base64:' . base64_encode(Encrypter::generateKey(config('app.cipher'))),
        ]);
        $this->app->forgetInstance('encrypter');

        $this->verifyEmail($parameters)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Test that the parameters of the link are validated.
     *
     * @return void
     */
    public function testValidatesLinkParameters(): void
    {
        $this->verifyEmail([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id', 'hash', 'expires', 'signature']);
    }

    /**
     * Test that a verified email links the account to the person's account with the same verified email, after
     * which it signs in with the person's password, and the tokens, which were issued with its old password,
     * are deleted.
     *
     * @return void
     */
    public function testVerifiedEmailLinksAccountToThePerson(): void
    {
        $main = $this->createUser('bob@example.com', null, ['email_verified_at' => now()]);
        $account = $this->createUser('bob@example.com', Organization::factory()->create(), [
            'password' => 'other-password',
        ]);

        $mainToken = $this->createAccessToken($main);
        $accountToken = $this->createAccessToken($account);

        $this->verifyEmail(VerifyEmailRequest::linkParameters($account))
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Your email has been verified, and the account now signs in with the password of your other accounts.'
            );

        $account->refresh();

        $this->assertSame($main->id, $account->main_account_id);
        $this->assertTrue(Hash::check('password123', $account->password));
        $this->assertModelMissing($accountToken->accessToken);
        $this->assertModelExists($mainToken->accessToken);
    }

    /**
     * Test that a changed email must be verified again: a link is emailed to it, and the link verifies it.
     *
     * @return void
     */
    public function testChangedEmailMustBeVerifiedAgain(): void
    {
        $user = $this->createUser('bob@example.com', null, ['email_verified_at' => now()]);

        $user->forceFill(['email' => 'robert@example.com'])->save();

        $this->signedInAs($user)
            ->getJson('/verified-only')
            ->assertForbidden();

        $this->sendVerificationEmail()->assertOk();

        $this->verifyEmail($this->linkParametersSentTo($user))->assertOk();

        $this->signedInAs($user)
            ->getJson('/verified-only')
            ->assertOk();
    }

    /**
     * Test that the requests for links are throttled for each user's email, and the verifications are throttled
     * too.
     *
     * @return void
     */
    public function testThrottlesEmailVerification(): void
    {
        config(['api-core.auth.rate_limit' => 2]);

        $user = $this->createUser('bob@example.com');
        $parameters = VerifyEmailRequest::linkParameters($user);

        $this->signedInAs($user);
        $this->sendVerificationEmail()->assertOk();
        $this->sendVerificationEmail()->assertOk();
        $this->sendVerificationEmail()->assertTooManyRequests();

        $alice = $this->createUser('alice@example.com');

        $this->signedInAs($alice)
            ->sendVerificationEmail()
            ->assertOk();

        $this->signedOut();
        $this->verifyEmail([...$parameters, 'signature' => 'wrong'])->assertForbidden();
        $this->verifyEmail([...$parameters, 'signature' => 'wrong'])->assertForbidden();
        $this->verifyEmail($parameters)->assertTooManyRequests();

        // The links of other accounts, verified from the same address, count apart
        $this->verifyEmail([...VerifyEmailRequest::linkParameters($alice), 'signature' => 'wrong'])->assertForbidden();
    }
}
