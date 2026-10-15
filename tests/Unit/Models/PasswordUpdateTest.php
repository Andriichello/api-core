<?php

namespace Core\Tests\Unit\Models;

use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class PasswordUpdateTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a password, which is set without stamping `password_updated_at`, as anyone but the person sets
     * it, must be updated, on creation and later.
     *
     * @return void
     */
    public function testPasswordSetByAnyoneElseMustBeUpdated(): void
    {
        $created = User::query()->forceCreate([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]);

        $this->assertTrue($created->fresh()->isPasswordUpdateRequired());

        $updated = User::factory()->create();
        $this->assertFalse($updated->isPasswordUpdateRequired());

        $updated->forceFill(['password' => 'set-by-admin'])->save();

        $this->assertTrue($updated->isPasswordUpdateRequired());
        $this->assertTrue($updated->fresh()->isPasswordUpdateRequired());
    }

    /**
     * Test that a password, which is set along with `password_updated_at`, as the person sets it, is updated, and
     * stays so, when other attributes change.
     *
     * @return void
     */
    public function testPasswordSetByThePersonIsUpdated(): void
    {
        $user = User::factory()->requiringPasswordUpdate()->create();
        $this->assertTrue($user->isPasswordUpdateRequired());

        $user->forceFill(['password' => 'chosen-password', 'password_updated_at' => now()])->save();
        $this->assertFalse($user->fresh()->isPasswordUpdateRequired());

        $user->forceFill(['name' => 'Bob'])->save();
        $this->assertFalse($user->fresh()->isPasswordUpdateRequired());
    }

    /**
     * Test that a linked account is checked by the time, when the password of the main account was updated,
     * even when its own copy differs, or the main account is deleted.
     *
     * @return void
     */
    public function testChecksPasswordUpdateOfMainAccount(): void
    {
        $main = User::factory()->requiringPasswordUpdate()->create();
        $linked = User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'main_account_id' => $main->id,
        ]);

        // Written past the model, so it isn't shared
        User::query()->whereKey($linked->id)->toBase()->update(['password_updated_at' => now()]);

        $this->assertTrue($linked->fresh()->isPasswordUpdateRequired());

        $main->delete();

        $this->assertTrue($linked->fresh()->isPasswordUpdateRequired());
        $this->assertNull($linked->fresh()->passwordUpdatedAt());
    }
}
