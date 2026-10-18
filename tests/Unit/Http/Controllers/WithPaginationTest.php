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

    /**
     * Class, which paginates queries for the tests.
     *
     * @var WithPaginationInterface
     */
    private WithPaginationInterface $paginates;

    /**
     * Create an anonymous class, which uses the `WithPagination` trait, to paginate queries in the tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->paginates = new class implements WithPaginationInterface
        {
            use WithPagination;
        };
    }

    /**
     * Create the given number of users, which are numbered from one.
     *
     * @param int $count
     *
     * @return void
     */
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

    /**
     * Test that the page size is the default of 100, when the request does not give one.
     *
     * @return void
     */
    public function testPageSizeDefaultsWhenMissing(): void
    {
        $this->assertSame(100, $this->paginates->getPageSize(Request::create('/users')));
    }

    /**
     * Test that the page size is the one, which the request gives in `page[size]`.
     *
     * @return void
     */
    public function testPageSizeIsTheRequestedOne(): void
    {
        $request = Request::create('/users', 'GET', ['page' => ['size' => 25]]);

        $this->assertSame(25, $this->paginates->getPageSize($request));
    }

    /**
     * Test that a requested page size above the maximum is capped at 1000.
     *
     * @return void
     */
    public function testPageSizeIsCappedAtTheMaximum(): void
    {
        $request = Request::create('/users', 'GET', ['page' => ['size' => 5000]]);

        $this->assertSame(1000, $this->paginates->getPageSize($request));
    }

    /**
     * Test that a requested page size of zero or less falls back to the default of 100.
     *
     * @return void
     */
    public function testPageSizeBelowOneFallsBackToTheDefault(): void
    {
        foreach ([0, -5] as $size) {
            $request = Request::create('/users', 'GET', ['page' => ['size' => $size]]);

            $this->assertSame(100, $this->paginates->getPageSize($request), "page[size]=$size");
        }
    }

    /**
     * Test that the page number is the one, which the request gives in `page[number]`.
     *
     * @return void
     */
    public function testPageNumberIsTheRequestedOne(): void
    {
        $request = Request::create('/users', 'GET', ['page' => ['number' => 3]]);

        $this->assertSame(3, $this->paginates->getPageNumber($request));
    }

    /**
     * Test that the page number is one, when the request does not give it, or gives one, which is below one or not a
     * number.
     *
     * @return void
     */
    public function testPageNumberDefaultsToOne(): void
    {
        $this->assertSame(1, $this->paginates->getPageNumber(Request::create('/users')));

        foreach ([0, -1, 'abc'] as $number) {
            $request = Request::create('/users', 'GET', ['page' => ['number' => $number]]);

            $this->assertSame(1, $this->paginates->getPageNumber($request), "page[number]=$number");
        }
    }

    /**
     * Test that a query is paginated into the requested page of a length-aware paginator, which counts the total.
     *
     * @return void
     */
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

    /**
     * Test that simple pagination, which does not count the total, is used, when the config asks for it, and still
     * tells whether there are more pages.
     *
     * @return void
     */
    public function testPaginateUsesSimplePaginationWhenConfigured(): void
    {
        config(['pagination.use_simple_pagination' => true]);
        $this->createUsers(3);

        $request = Request::create('/users', 'GET', ['page' => ['size' => 2]]);

        $paginator = $this->paginates->paginate(User::query(), $request);

        $this->assertNotInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertTrue($paginator->hasMorePages());
    }

    /**
     * Test that the link to the next page keeps the other query parameters, such as the filters and the page size.
     *
     * @return void
     */
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
