<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class LinksAccountsByEmailTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 */
class LinksAccountsByEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an account with the email, which is verified, in a new organization.
     *
     * @param string $email
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createAccount(string $email, array $attributes = []): User
    {
        $organization = Organization::query()->create([
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
        ]);

        return User::query()->forceCreate([
            'organization_id' => $organization->id,
            'name' => fake()->name(),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => 'password123',
            ...$attributes,
        ]);
    }

    /**
     * Create an account with the email, which isn't verified, in a new organization.
     *
     * @param string $email
     * @param array<string, mixed> $attributes
     *
     * @return User
     */
    private function createUnverifiedAccount(string $email, array $attributes = []): User
    {
        return $this->createAccount($email, ['email_verified_at' => null, ...$attributes]);
    }

    /**
     * Test that a new account is linked to the account with the same verified email.
     *
     * @return void
     */
    public function testLinksNewAccountToAccountWithTheSameVerifiedEmail(): void
    {
        $main = $this->createAccount('bob@example.com');
        $linked = $this->createAccount('bob@example.com');
        $other = $this->createAccount('bob@example.com');

        $this->assertTrue($main->fresh()->isMainAccount());
        $this->assertSame($main->id, $linked->fresh()->main_account_id);
        $this->assertSame($main->id, $other->fresh()->main_account_id);
    }

    /**
     * Test that accounts with different emails aren't linked.
     *
     * @return void
     */
    public function testDoesNotLinkAccountsWithDifferentEmails(): void
    {
        $this->createAccount('bob@example.com');
        $account = $this->createAccount('alice@example.com');

        $this->assertTrue($account->fresh()->isMainAccount());
    }

    /**
     * Test that accounts with unverified emails aren't linked.
     *
     * @return void
     */
    public function testDoesNotLinkUnverifiedAccounts(): void
    {
        $unverified = $this->createUnverifiedAccount('bob@example.com');
        $verified = $this->createAccount('bob@example.com');
        $another = $this->createUnverifiedAccount('bob@example.com');

        $this->assertTrue($unverified->fresh()->isMainAccount());
        $this->assertTrue($verified->fresh()->isMainAccount());
        $this->assertTrue($another->fresh()->isMainAccount());
    }

    /**
     * Test that an account is linked, when its email is verified.
     *
     * @return void
     */
    public function testLinksAccountWhenItsEmailIsVerified(): void
    {
        $main = $this->createAccount('bob@example.com');
        $account = $this->createUnverifiedAccount('bob@example.com');

        $account->markEmailAsVerified();

        $this->assertSame($main->id, $account->fresh()->main_account_id);
    }

    /**
     * Test that an earlier account is linked to a later one, when its email is verified after the later
     * one's.
     *
     * @return void
     */
    public function testLinksEarlierAccountWhenItsEmailIsVerifiedLater(): void
    {
        $earlier = $this->createUnverifiedAccount('bob@example.com');
        $main = $this->createAccount('bob@example.com');

        $earlier->markEmailAsVerified();

        $this->assertSame($main->id, $earlier->fresh()->main_account_id);
        $this->assertTrue($main->fresh()->isMainAccount());
    }

    /**
     * Test that an account isn't linked to itself.
     *
     * @return void
     */
    public function testDoesNotLinkAccountToItself(): void
    {
        $account = $this->createUnverifiedAccount('bob@example.com');

        $account->markEmailAsVerified();
        $account->update(['name' => 'Bob']);

        $this->assertTrue($account->fresh()->isMainAccount());
    }

    /**
     * Test that an account is linked to the main account of the oldest account with the same email.
     *
     * @return void
     */
    public function testLinksToTheMainAccountOfTheOldestAccount(): void
    {
        $main = $this->createAccount('alice@example.com');
        $linked = $this->createAccount('bob@example.com', ['main_account_id' => $main->id]);

        $account = $this->createAccount('bob@example.com');

        $this->assertSame($main->id, $linked->fresh()->main_account_id);
        $this->assertSame($main->id, $account->fresh()->main_account_id);
    }

    /**
     * Test that emails are matched regardless of their case.
     *
     * @return void
     */
    public function testMatchesEmailsRegardlessOfCase(): void
    {
        $main = $this->createAccount('Bob@Example.com');
        $linked = $this->createAccount('bob@EXAMPLE.com');

        $this->assertSame('bob@example.com', $main->fresh()->email);
        $this->assertSame($main->id, $linked->fresh()->main_account_id);
    }

    /**
     * Test that a main account, which is given explicitly, is kept.
     *
     * @return void
     */
    public function testKeepsMainAccountGivenExplicitly(): void
    {
        $this->createAccount('bob@example.com');
        $main = $this->createAccount('alice@example.com');

        $account = $this->createAccount('bob@example.com', ['main_account_id' => $main->id]);

        $this->assertSame($main->id, $account->fresh()->main_account_id);
    }

    /**
     * Test that an account, which is unlinked explicitly, stays unlinked.
     *
     * @return void
     */
    public function testKeepsAccountUnlinkedExplicitly(): void
    {
        $this->createAccount('bob@example.com');
        $account = $this->createAccount('bob@example.com');

        $account->forceFill([
            'main_account_id' => null,
            'email_verified_at' => now()->addMinute(),
        ])->save();

        $this->assertTrue($account->fresh()->isMainAccount());
    }

    /**
     * Test that an account is unlinked, when its email changes.
     *
     * @return void
     */
    public function testUnlinksAccountWhenItsEmailChanges(): void
    {
        $this->createAccount('bob@example.com');
        $account = $this->createAccount('bob@example.com');

        $account->forceFill(['email' => 'robert@example.com'])->save();

        $this->assertTrue($account->fresh()->isMainAccount());
    }

    /**
     * Test that an account is linked by its new email, when the new email is verified.
     *
     * @return void
     */
    public function testLinksAccountAgainByItsNewEmailWhenItIsVerified(): void
    {
        $this->createAccount('bob@example.com');
        $robert = $this->createAccount('robert@example.com');
        $account = $this->createAccount('bob@example.com');

        $account->forceFill([
            'email' => 'robert@example.com',
            'email_verified_at' => now()->addMinute(),
        ])->save();

        $this->assertSame($robert->id, $account->fresh()->main_account_id);
    }

    /**
     * Test that an account isn't linked by its new email with the verification of the old one.
     *
     * @return void
     */
    public function testDoesNotLinkAccountByNewEmailWithVerificationOfTheOldOne(): void
    {
        $this->createAccount('bob@example.com');
        $this->createAccount('robert@example.com');
        $account = $this->createAccount('bob@example.com');

        $account->forceFill(['email' => 'robert@example.com'])->save();
        $account->update(['name' => 'Robert']);

        $this->assertTrue($account->fresh()->isMainAccount());
    }

    /**
     * Test that an account isn't linked by its new email, until the new email is verified.
     *
     * @return void
     */
    public function testDoesNotLinkAccountByNewEmailUntilItIsVerified(): void
    {
        $this->createAccount('bob@example.com');
        $robert = $this->createAccount('robert@example.com');
        $account = $this->createAccount('bob@example.com');

        $account->forceFill(['email' => 'robert@example.com', 'email_verified_at' => null])->save();

        $this->assertTrue($account->fresh()->isMainAccount());

        $account->markEmailAsVerified();

        $this->assertSame($robert->id, $account->fresh()->main_account_id);
    }

    /**
     * Test that linked accounts, including deleted ones, are handed over to the oldest of them, when the
     * email of the main account changes.
     *
     * @return void
     */
    public function testHandsOverLinkedAccountsWhenMainAccountEmailChanges(): void
    {
        $main = $this->createAccount('bob@example.com');
        $first = $this->createAccount('bob@example.com');
        $second = $this->createAccount('bob@example.com');
        $third = $this->createAccount('bob@example.com');

        $third->delete();

        $main->forceFill(['email' => 'robert@example.com'])->save();

        $this->assertTrue($main->fresh()->isMainAccount());
        $this->assertTrue($first->fresh()->isMainAccount());
        $this->assertSame($first->id, $second->fresh()->main_account_id);
        $this->assertSame($first->id, $third->fresh()->main_account_id);
    }

    /**
     * Test that a main account is linked by its new email, after handing over its linked accounts.
     *
     * @return void
     */
    public function testMainAccountWithLinkedAccountsIsLinkedByItsNewEmail(): void
    {
        $robert = $this->createAccount('robert@example.com');
        $main = $this->createAccount('bob@example.com');
        $linked = $this->createAccount('bob@example.com');

        $main->forceFill([
            'email' => 'robert@example.com',
            'email_verified_at' => now()->addMinute(),
        ])->save();

        $this->assertSame($robert->id, $main->fresh()->main_account_id);
        $this->assertTrue($linked->fresh()->isMainAccount());
    }

    /**
     * Test that a main account, which has linked accounts, isn't linked, so accounts don't form chains.
     *
     * @return void
     */
    public function testDoesNotLinkMainAccountWhichHasLinkedAccounts(): void
    {
        $this->createAccount('bob@example.com');
        $main = $this->createUnverifiedAccount('bob@example.com');
        $linked = $this->createUnverifiedAccount('alice@example.com', ['main_account_id' => $main->id]);

        $main->markEmailAsVerified();

        $this->assertTrue($main->fresh()->isMainAccount());
        $this->assertSame($main->id, $linked->fresh()->main_account_id);
    }

    /**
     * Test that linked accounts are handed over to the oldest of them, when the main account is deleted
     * permanently.
     *
     * @return void
     */
    public function testHandsOverLinkedAccountsWhenMainAccountIsDeletedPermanently(): void
    {
        $main = $this->createAccount('bob@example.com');
        $first = $this->createAccount('bob@example.com');
        $second = $this->createAccount('bob@example.com');

        $main->forceDelete();

        $this->assertTrue($first->fresh()->isMainAccount());
        $this->assertSame($first->id, $second->fresh()->main_account_id);
    }

    /**
     * Test that linked accounts stay linked, when the main account is soft deleted.
     *
     * @return void
     */
    public function testKeepsLinkedAccountsWhenMainAccountIsSoftDeleted(): void
    {
        $main = $this->createAccount('bob@example.com');
        $linked = $this->createAccount('bob@example.com');

        $main->delete();

        $this->assertSame($main->id, $linked->fresh()->main_account_id);
    }
}
