<?php

namespace Core\Tests\Unit\Queries;

use Core\Models\File;
use Core\Models\User;
use Core\Queries\BaseQuery;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Collect the ids of the users in each chunk, which `chunkPaginated()` passes to the callback.
     *
     * @param BaseQuery $query
     * @param int $count
     *
     * @return array<int, array<int, int>>
     */
    private function chunksOf(BaseQuery $query, int $count): array
    {
        $chunks = [];

        $query->chunkPaginated(function (Collection $users) use (&$chunks) {
            $chunks[] = $users->pluck('id')->all();
        }, $count);

        return $chunks;
    }

    /**
     * Test that `chunkPaginated()` goes through all the results in chunks, when the query has no limit.
     *
     * @return void
     */
    public function testChunkPaginatedGoesThroughAllResultsWithoutLimit(): void
    {
        $ids = User::factory()->count(5)->create()->modelKeys();

        $this->assertSame(
            [array_slice($ids, 0, 2), array_slice($ids, 2, 2), array_slice($ids, 4, 1)],
            $this->chunksOf(User::query()->orderBy('id'), 2)
        );

        $this->assertSame(
            [array_slice($ids, 0, 5)],
            $this->chunksOf(User::query()->orderBy('id'), 5)
        );
    }

    /**
     * Test that `chunkPaginated()` starts at the offset and stops at the limit, which isn't a multiple of the
     * chunk size.
     *
     * @return void
     */
    public function testChunkPaginatedStopsAtLimit(): void
    {
        $ids = User::factory()->count(10)->create()->modelKeys();

        $this->assertSame(
            [array_slice($ids, 1, 2), array_slice($ids, 3, 2), array_slice($ids, 5, 1)],
            $this->chunksOf(User::query()->orderBy('id')->offset(1)->limit(5), 2)
        );

        $this->assertSame(
            [array_slice($ids, 8, 2)],
            $this->chunksOf(User::query()->orderBy('id')->offset(8)->limit(5), 3)
        );
    }

    /**
     * Test that `chunkPaginated()` passes no empty chunk, and stops, when the callback returns false.
     *
     * @return void
     */
    public function testChunkPaginatedSkipsEmptyChunksAndStopsOnFalse(): void
    {
        $this->assertSame([], $this->chunksOf(User::query(), 2));

        User::factory()->count(5)->create();
        $calls = 0;

        User::query()->orderBy('id')->chunkPaginated(function () use (&$calls) {
            $calls++;

            return false;
        }, 2);

        $this->assertSame(1, $calls);
    }
}
