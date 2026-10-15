<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Class SharesPasswordAcrossAccountsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 */
class SharesPasswordAcrossAccountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an account with the email, which isn't verified, so it isn't linked by it, in a new organization.
     * Its password is `person-password` by default.
     *
     * @param string $email
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createAccount(string $email, array $attributes = []): User
    {
        return User::factory()
            ->unverified()
            ->create([
                'organization_id' => Organization::factory()->create()->id,
                'email' => $email,
                'password' => 'person-password',
                ...$attributes,
            ]);
    }

    /**
     * Create an account, which is linked to the main account, with its own password.
     *
     * @param User $main
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createLinkedAccount(User $main, array $attributes = []): User
    {
        return $this->createAccount($main->email, [
            'main_account_id' => $main->id,
            'password' => 'own-password',
            ...$attributes,
        ]);
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
     * Test that a password, which is set on any account of a person, is set on all of their accounts, deleted
     * ones included, but not on accounts of other people.
     *
     * @return void
     */
    public function testSharesNewPasswordWithEveryAccountOfThePerson(): void
    {
        $main = $this->createAccount('bob@example.com');
        $first = $this->createLinkedAccount($main);
        $second = $this->createLinkedAccount($main);
        $second->delete();
        $stranger = $this->createAccount('bob@example.com');

        $first->forceFill(['password' => 'new-password'])->save();

        $this->assertPassword('new-password', $main, $first, $second);
        $this->assertPassword('person-password', $stranger);

        $main->forceFill(['password' => 'newer-password'])->save();

        $this->assertPassword('newer-password', $main, $first, $second);
        $this->assertPassword('person-password', $stranger);
    }

    /**
     * Test that an account takes the password of the main account, when it is linked to it on creation,
     * by its verified email, or later.
     *
     * @return void
     */
    public function testLinkedAccountTakesPasswordOfMainAccount(): void
    {
        $main = $this->createAccount('bob@example.com', ['email_verified_at' => now()]);

        $explicit = $this->createLinkedAccount($main);
        $byEmail = $this->createAccount('bob@example.com', [
            'email_verified_at' => now(),
            'password' => 'own-password',
        ]);
        $later = $this->createAccount('alice@example.com', ['password' => 'own-password']);
        $later->forceFill(['main_account_id' => $main->id])->save();

        $this->assertSame($main->id, $byEmail->main_account_id);

        foreach ([$explicit, $byEmail, $later] as $account) {
            $this->assertSame($main->password, $account->password);
            $this->assertSame($main->password, $account->fresh()->password);
        }

        $this->assertPassword('person-password', $main);
    }

    /**
     * Test that an account, which becomes the main one, when the main account is deleted permanently, keeps
     * the person's password.
     *
     * @return void
     */
    public function testAccountWhichBecomesMainKeepsThePersonsPassword(): void
    {
        $main = $this->createAccount('bob@example.com');
        $linked = $this->createLinkedAccount($main);

        $main->forceDelete();

        $this->assertTrue($linked->fresh()->isMainAccount());
        $this->assertTrue(Hash::check('person-password', $linked->fresh()->getAuthPassword()));
    }

    /**
     * Test that an account, which is unlinked, because its email changes, keeps the person's password.
     *
     * @return void
     */
    public function testUnlinkedAccountKeepsThePersonsPassword(): void
    {
        $main = $this->createAccount('bob@example.com');
        $linked = $this->createLinkedAccount($main);

        $linked->forceFill(['email' => 'robert@example.com'])->save();

        $this->assertTrue($linked->fresh()->isMainAccount());
        $this->assertTrue(Hash::check('person-password', $linked->fresh()->getAuthPassword()));
    }

    /**
     * Test that a linked account is authenticated with the password of the main account, even when its own
     * one differs, or the main account is deleted.
     *
     * @return void
     */
    public function testChecksPasswordOfMainAccount(): void
    {
        $main = $this->createAccount('bob@example.com');
        $linked = $this->createLinkedAccount($main);

        // Written past the model, so it isn't shared
        User::query()->whereKey($linked->id)->toBase()->update(['password' => Hash::make('stale-password')]);

        $this->assertTrue(Hash::check('person-password', $linked->fresh()->getAuthPassword()));
        $this->assertFalse(Hash::check('stale-password', $linked->fresh()->getAuthPassword()));

        $main->delete();

        $this->assertTrue(Hash::check('person-password', $linked->fresh()->getAuthPassword()));
    }
}
