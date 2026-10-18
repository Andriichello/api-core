<?php

namespace Core\Tests\Unit\Queries;

use Core\Models\File;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class BaseQueryTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Queries
 */
class BaseQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that `whereTrashed()` finds only the soft-deleted models, and none of a model, which can't be
     * soft-deleted.
     *
     * @return void
     */
    public function testWhereTrashedFindsOnlySoftDeletedModels(): void
    {
        $kept = User::factory()->create();
        $trashed = User::factory()->create();
        $trashed->delete();

        $this->assertSame([$trashed->id], User::query()->whereTrashed()->pluck('id')->all());
        $this->assertTrue(User::query()->whereKey($kept->id)->exists());

        File::factory()->create();

        $this->assertFalse(File::query()->whereTrashed()->exists());
    }
}
