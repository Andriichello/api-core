<?php

namespace Core\Tests\Unit\Models;

use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class CastsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class CastsTest extends TestCase
{
    use RefreshDatabase;

    public function testMetadataOfUserIsAnArray(): void
    {
        $user = User::query()->forceCreate([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'metadata' => ['theme' => 'dark'],
        ]);

        $this->assertSame(['theme' => 'dark'], $user->fresh()->metadata);
        $this->assertSame('{"theme":"dark"}', DB::table('users')->value('metadata'));
    }

    public function testMetadataOfOrganizationIsAnArray(): void
    {
        $organization = Organization::query()->forceCreate([
            'slug' => 'acme',
            'name' => 'Acme',
            'metadata' => ['plan' => 'pro'],
        ]);

        $this->assertSame(['plan' => 'pro'], $organization->fresh()->metadata);
    }

    public function testTimestampsOfUserAreCarbon(): void
    {
        $user = User::query()->forceCreate([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'suspended_at' => '2026-06-30 12:00:00',
            'last_login_at' => '2026-06-30 13:00:00',
        ])->fresh();

        $this->assertInstanceOf(Carbon::class, $user->suspended_at);
        $this->assertInstanceOf(Carbon::class, $user->last_login_at);
        $this->assertSame('2026-06-30 13:00:00', $user->last_login_at->format('Y-m-d H:i:s'));
    }
}
