<?php

namespace Core\Tests\Unit\Database;

use Core\Database\Seeders\RolesAndPermissionsSeeder;
use Core\Enums\UserRole;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Class RolesAndPermissionsSeederTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Database
 */
class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function testSeedsRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            UserRole::values(),
            Role::query()->pluck('name')->all()
        );
    }
}
