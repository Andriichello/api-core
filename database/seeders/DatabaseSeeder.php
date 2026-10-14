<?php

namespace Core\Database\Seeders;

use Core\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Class DatabaseSeeder.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Database\Seeders
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(RolesAndPermissionsSeeder::class);
    }
}
