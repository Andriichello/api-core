<?php

namespace Core\Tests\Unit\Http\Controllers;

use Core\Http\Controllers\Interfaces\WithPaginationInterface;
use Core\Http\Controllers\Traits\WithPagination;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

/**
 * Class WithPaginationTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Controllers
 */
class WithPaginationTest extends TestCase
{
    use RefreshDatabase;

    private WithPaginationInterface $paginates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paginates = new class implements WithPaginationInterface
        {
            use WithPagination;
        };
    }

    private function createUsers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            User::query()->forceCreate([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'password' => 'password123',
            ]);
        }
    }

    public function testPageSizeDefaultsWhenMissing(): void
    {
        $this->assertSame(100, $this->paginates->getPageSize(Request::create('/users')));
    }

    public function testPageSizeIsTheRequestedOne(): void
    {
        $request = Request::create('/users', 'GET', ['page' => ['size' => 25]]);

        $this->assertSame(25, $this->paginates->getPageSize($request));
    }

    public function testPageSizeIsCappedAtTheMaximum(): void
    {
        $request = Request::create('/users', 'GET', ['page' => ['size' => 5000]]);

        $this->assertSame(1000, $this->paginates->getPageSize($request));
    }

    public function testPageSizeBelowOneFallsBackToTheDefault(): void
    {
        foreach ([0, -5] as $size) {
            $request = Request::create('/users', 'GET', ['page' => ['size' => $size]]);

            $this->assertSame(100, $this->paginates->getPageSize($request), "page[size]=$size");
        }
    }

    public function testPageNumberIsTheRequestedOne(): void
    {
        $request = Request::create('/users', 'GET', ['page' => ['number' => 3]]);

        $this->assertSame(3, $this->paginates->getPageNumber($request));
    }

    public function testPageNumberDefaultsToOne(): void
    {
        $this->assertSame(1, $this->paginates->getPageNumber(Request::create('/users')));

        foreach ([0, -1, 'abc'] as $number) {
            $request = Request::create('/users', 'GET', ['page' => ['number' => $number]]);

            $this->assertSame(1, $this->paginates->getPageNumber($request), "page[number]=$number");
        }
    }

    public function testPaginateReturnsTheRequestedPage(): void
    {
        $this->createUsers(3);

        $request = Request::create('/users', 'GET', ['page' => ['number' => 2, 'size' => 2]]);

        $paginator = $this->paginates->paginate(User::query()->orderBy('id'), $request);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(2, $paginator->currentPage());
        $this->assertSame(3, $paginator->total());
        $this->assertCount(1, $paginator->items());
    }

    public function testPaginateUsesSimplePaginationWhenConfigured(): void
    {
        config(['pagination.use_simple_pagination' => true]);
        $this->createUsers(3);

        $request = Request::create('/users', 'GET', ['page' => ['size' => 2]]);

        $paginator = $this->paginates->paginate(User::query(), $request);

        $this->assertNotInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testPaginatorLinksKeepTheOtherQueryParameters(): void
    {
        $this->createUsers(3);

        $request = Request::create('/users', 'GET', [
            'filter' => ['name' => ['like' => 'User%']],
            'page' => ['number' => 1, 'size' => 2],
        ]);

        $url = urldecode($this->paginates->paginate(User::query(), $request)->nextPageUrl());

        $this->assertStringContainsString('filter[name][like]=User%', $url);
        $this->assertStringContainsString('page[size]=2', $url);
        $this->assertStringContainsString('page[number]=2', $url);
    }
}
