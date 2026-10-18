<?php

namespace Core\Tests\Feature\Http\Controllers\Auth;

use Core\Enums\UserRole;
use Core\Http\Controllers\Auth\AuthController;
use Core\Models\Organization;
use Core\Models\User;
use Core\Notifications\InvitationNotification;
use Core\Repositories\AccessTokenRepository;
use Core\Repositories\UserRepository;
use Core\Tests\TestCase;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Uri;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

/**
 * Class AuthControllerInvitationTest.
 *
 * People invited to accounts in Globex by its admin, who accept the invitations with the emailed links.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers\Auth
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuthControllerInvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Organization, to which the people are invited.
     *
     * @var Organization
     */
    private Organization $globex;

    /**
     * Admin of the organization, who invites the people.
     *
     * @var User
     */
    private User $admin;

    /**
     * Register the auth routes, the SPA's page of invitations, the organization and its admin, and fake the
     * notifications.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('auth')->group(fn () => AuthController::routes());

        config(['api-core.auth.invitation_url' => 'https://spa.test/accept-invitation']);

        $this->globex = Organization::factory()->create(['name' => 'Globex']);
        $this->admin = User::factory()->create(['organization_id' => $this->globex->id, 'name' => 'Grace']);

        Role::findOrCreate(UserRole::User->value);
        Notification::fake();
    }

    /**
     * Invite the person with the email to an account in the organization.
     *
     * @param string $email
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function invite(string $email, array $attributes = []): User
    {
        return app(UserRepository::class)->invite(
            ['name' => 'Invited', 'email' => $email, ...$attributes],
            $this->globex->id,
            [UserRole::User],
            $this->admin
        );
    }

    /**
     * Get the parameters of the link in the latest invitation email of the user.
     *
     * @param User $user
     *
     * @return array<string, mixed>
     */
    private function linkOf(User $user): array
    {
        $url = null;

        Notification::assertSentTo($user, InvitationNotification::class, function ($notification) use ($user, &$url) {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        return Uri::of((string) $url)->query()->all();
    }

    /**
     * Accept the invitation with the link's parameters and the password.
     *
     * @param array<string, mixed> $link
     * @param string $password
     *
     * @return TestResponse
     */
    private function accept(array $link, string $password = 'new-password-1'): TestResponse
    {
        return $this->postJson('auth/accept-invitation', [
            ...$link,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    /**
     * Test that the invited account is made in the organization with its roles, but without a password, which
     * anyone knows, even when one is given, and the email links to the SPA's page.
     *
     * @return void
     */
    public function testInvitesPersonToAccountWithoutPassword(): void
    {
        $user = $this->invite('ann@example.com', ['password' => 'known-password']);

        $this->assertSame($this->globex->id, $user->organization_id);
        $this->assertTrue($user->hasRole(UserRole::User->value));
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNull($user->password_updated_at);
        $this->assertFalse(Hash::check('known-password', $user->password));

        $mail = null;

        Notification::assertSentTo($user, InvitationNotification::class, function ($notification) use ($user, &$mail) {
            $mail = $notification->toMail($user);

            return true;
        });

        $this->assertSame('You are invited to Globex', $mail->subject);
        $this->assertSame('Grace invited you to join Globex.', $mail->introLines[0]);
        $this->assertSame('The link expires in 1 week.', $mail->outroLines[0]);
        $this->assertStringStartsWith('https://spa.test/accept-invitation?id=' . $user->id . '&', $mail->actionUrl);
    }

    /**
     * Test that an invitation to an account without an organization, which nobody sent, invites to the app.
     *
     * @return void
     */
    public function testInvitesToTheAppWithoutOrganization(): void
    {
        config(['app.name' => 'Prytax']);

        $user = app(UserRepository::class)->invite(['name' => 'Ann', 'email' => 'ann@example.com'], null);
        $mail = null;

        Notification::assertSentTo($user, InvitationNotification::class, function ($notification) use ($user, &$mail) {
            $mail = $notification->toMail($user);

            return true;
        });

        $this->assertNull($user->organization_id);
        $this->assertSame('You are invited to Prytax', $mail->subject);
        $this->assertSame('You are invited to join Prytax.', $mail->introLines[0]);
    }

    /**
     * Test that accepting verifies the email and sets the chosen password, as updated by the person.
     *
     * @return void
     */
    public function testAcceptingSetsThePassword(): void
    {
        Event::fake([Verified::class]);

        $user = $this->invite('ann@example.com');

        $this->accept($this->linkOf($user))
            ->assertOk()
            ->assertJsonPath('message', 'Your invitation has been accepted, so you can sign in with your password.');

        $user->refresh();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('new-password-1', $user->password));
        $this->assertNotNull($user->password_updated_at);

        Event::assertDispatched(
            Verified::class,
            fn (Verified $event) => $event->user instanceof User && $user->is($event->user)
        );
    }

    /**
     * Test that a link works once, and not after it expires, or with another signature, and that a password,
     * which fails the rules, changes nothing.
     *
     * @return void
     */
    public function testLinkWorksOnceWhileValid(): void
    {
        $user = $this->invite('ann@example.com');
        $link = $this->linkOf($user);

        $this->accept($link, 'short')->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->accept([...$link, 'signature' => str_repeat('0', 64)])->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->accept($link)->assertOk();
        $this->accept($link, 'other-password-1')
            ->assertForbidden()
            ->assertJsonPath('message', 'The invitation link is invalid, has expired or has been used.');

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));

        $other = $this->invite('bob@example.com');
        $expired = $this->linkOf($other);

        $this->travel(config('api-core.auth.invitation_lifetime') + 1)->minutes();

        $this->accept($expired)->assertForbidden();
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    /**
     * Test that a person, who has an account elsewhere, joins it with the invited account, which then shares the
     * chosen password with it, as a reset would, so the tokens of the other account are deleted.
     *
     * @return void
     */
    public function testPersonWithAnotherAccountJoinsIt(): void
    {
        $existing = User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'email' => 'ann@example.com',
            'password' => 'old-password-1',
        ]);
        app(AccessTokenRepository::class)->create($existing, 'Phone');

        $user = $this->invite('ann@example.com');

        $this->accept($this->linkOf($user))->assertOk();

        $user->refresh();

        $this->assertSame($existing->id, $user->main_account_id);
        $this->assertTrue(Hash::check('new-password-1', $user->getAuthPassword()));
        $this->assertTrue(Hash::check('new-password-1', $existing->fresh()->password));
        $this->assertSame(0, $existing->tokens()->count());
    }

    /**
     * Test that a person, who has an account elsewhere, may accept without a password, keeping the password of
     * their other account, whose tokens stay.
     *
     * @return void
     */
    public function testPersonWithAnotherAccountKeepsItsPassword(): void
    {
        $existing = User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'email' => 'ann@example.com',
            'password' => 'old-password-1',
        ]);
        app(AccessTokenRepository::class)->create($existing, 'Phone');

        $user = $this->invite('ann@example.com');

        $this->postJson('auth/accept-invitation', $this->linkOf($user))
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Your invitation has been accepted, so you can sign in with the password of your other accounts.'
            );

        $user->refresh();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame($existing->id, $user->main_account_id);
        $this->assertTrue(Hash::check('old-password-1', $user->getAuthPassword()));
        $this->assertSame(1, $existing->tokens()->count());
    }

    /**
     * Test that a person without other accounts must choose a password, and their email stays unverified until
     * they do.
     *
     * @return void
     */
    public function testPersonWithoutOtherAccountsMustChooseAPassword(): void
    {
        $user = $this->invite('ann@example.com');
        $link = $this->linkOf($user);

        $this->postJson('auth/accept-invitation', $link)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->accept($link)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Test that an invitation can be sent again, with a link, which works.
     *
     * @return void
     */
    public function testInvitationCanBeSentAgain(): void
    {
        $user = $this->invite('ann@example.com');

        app(UserRepository::class)->sendInvitation($user, $this->admin);

        Notification::assertSentToTimes($user, InvitationNotification::class, 2);

        $this->accept($this->linkOf($user))->assertOk();
    }
}
