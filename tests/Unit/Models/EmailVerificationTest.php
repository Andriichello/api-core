<?php

namespace Core\Tests\Unit\Models;

use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class EmailVerificationTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that users must verify their email, so the `verified` middleware and the `Registered` event's
     * listener apply to them.
     *
     * @return void
     */
    public function testUsersMustVerifyEmail(): void
    {
        $this->assertInstanceOf(MustVerifyEmail::class, User::factory()->make());
    }

    /**
     * Test that a new email isn't verified, as the verification of the old one doesn't prove it.
     *
     * @return void
     */
    public function testChangedEmailIsNotVerified(): void
    {
        $user = User::factory()->create(['email' => 'bob@example.com']);

        $user->forceFill(['email' => 'robert@example.com'])->save();

        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Test that a new email is verified, when it is verified along with the change.
     *
     * @return void
     */
    public function testChangedEmailIsVerifiedAlongWithTheChange(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'bob@example.com']);

        $user->forceFill(['email' => 'robert@example.com', 'email_verified_at' => now()])->save();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Test that the email stays verified, when other attributes change, or the email is set again in another
     * case, as it is stored in lowercase.
     *
     * @return void
     */
    public function testEmailStaysVerifiedWhenItDoesNotChange(): void
    {
        $user = User::factory()->create(['email' => 'bob@example.com']);

        $user->forceFill(['name' => 'Bob', 'email' => 'Bob@Example.com'])->save();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
