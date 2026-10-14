<?php

namespace Core\Tests\Unit\Resources;

use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\ResourcePaginator;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class ResourcePaginatorTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Resources
 */
class ResourcePaginatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        for ($i = 1; $i <= 5; $i++) {
            User::query()->forceCreate([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'password' => 'password123',
            ]);
        }
    }

    public function testMetaOfLengthAwarePaginator(): void
    {
        $paginator = new ResourcePaginator(
            User::query()->orderBy('id')->paginate(2, ['*'], 'page', 2),
            BaseCollection::class
        );

        $meta = $paginator->meta();

        $this->assertSame(3, $meta['from']);
        $this->assertSame(4, $meta['to']);
        $this->assertSame(2, $meta['per_page']);
        $this->assertSame(2, $meta['current_page']);
        $this->assertSame(3, $meta['last_page']);
        $this->assertSame(5, $meta['total']);
        $this->assertStringContainsString('page%5Bnumber%5D=1', $meta['links']['prev']);
        $this->assertStringContainsString('page%5Bnumber%5D=3', $meta['links']['next']);
        $this->assertStringContainsString('page%5Bnumber%5D=3', $meta['links']['last']);
    }

    public function testMetaOfSimplePaginator(): void
    {
        $paginator = new ResourcePaginator(
            User::query()->orderBy('id')->simplePaginate(2, ['*'], 'page', 1),
            BaseCollection::class
        );

        $meta = $paginator->meta();

        $this->assertNull($meta['total']);
        $this->assertNull($meta['last_page']);
        $this->assertNull($meta['links']['last']);
        $this->assertNull($meta['links']['prev']);
        $this->assertStringContainsString('page%5Bnumber%5D=2', $meta['links']['next']);
    }

    public function testSimplePaginatorHasNoMorePagesOnTheLastPage(): void
    {
        $paginator = new ResourcePaginator(
            User::query()->orderBy('id')->simplePaginate(2, ['*'], 'page', 3),
            BaseCollection::class
        );

        $this->assertFalse($paginator->hasMorePages());
        $this->assertNull($paginator->meta()['links']['next']);
    }

    public function testToArrayWrapsTheItemsInTheCollection(): void
    {
        $paginator = new ResourcePaginator(
            User::query()->orderBy('id')->paginate(2),
            BaseCollection::class
        );

        $array = $paginator->toArray();

        $this->assertInstanceOf(BaseCollection::class, $array['data']);
        $this->assertCount(2, $array['data']->collection);
        $this->assertArrayHasKey('meta', $array);
    }
}
