<?php

namespace Core\Http\Resources;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

/**
 * Class ResourcePaginator.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class ResourcePaginator extends Paginator
{
    /**
     * Resource collection class.
     *
     * @var class-string<BaseCollection>
     */
    protected string $collectionClass;

    /**
     * Paginator instance, which was passed in the constructor.
     *
     * @var PaginatorContract|LengthAwarePaginator
     */
    protected PaginatorContract|LengthAwarePaginator $paginator;

    /**
     * ResourcePaginator constructor.
     *
     * @param PaginatorContract|LengthAwarePaginator $paginator
     * @param class-string<BaseCollection> $collectionClass
     *
     * @throws Exception
     */
    public function __construct(
        PaginatorContract|LengthAwarePaginator $paginator,
        string $collectionClass
    ) {
        $size = $paginator->perPage();
        $page = $paginator->currentPage();
        $items = $paginator->items();
        $options = $paginator instanceof AbstractPaginator
            ? $paginator->getOptions() : [];

        parent::__construct($items, $size, $page, $options);

        $this->paginator = $paginator;
        $this->collectionClass = $collectionClass;

        if (!is_subclass_of($collectionClass, ResourceCollection::class)) {
            $message = 'Invalid collectionClass.' .
                ' It must be an instance of ResourceCollection.';

            throw new Exception($message);
        }
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     * @throws Exception
     */
    public function toArray(): array
    {
        return [
            'data' => $this->getResourceCollection(),
            'meta' => $this->meta(),
        ];
    }

    /**
     * Get paginator instance, which was passed in the constructor.
     *
     * @return PaginatorContract|LengthAwarePaginator
     */
    public function paginator(): PaginatorContract|LengthAwarePaginator
    {
        return $this->paginator;
    }

    /**
     * Get number of records in the database.
     *
     * @return int|null
     */
    public function total(): ?int
    {
        return $this->paginator instanceof LengthAwarePaginator
            ? $this->paginator->total() : null;
    }

    /**
     * Get last page number.
     *
     * @return int|null
     */
    public function lastPage(): ?int
    {
        return $this->paginator instanceof LengthAwarePaginator
            ? $this->paginator->lastPage() : null;
    }

    /**
     * Get pagination parameter name.
     *
     * @return string
     */
    public function pageParam(): string
    {
        return config('pagination.pagination_parameter');
    }

    /**
     * Get pagination page size parameter name.
     *
     * @return string
     */
    public function sizeParam(): string
    {
        return config('pagination.size_parameter');
    }

    /**
     * Get pagination page number parameter name.
     *
     * @return string
     */
    public function numberParam(): string
    {
        return config('pagination.number_parameter');
    }

    /**
     * Get the URL for a given page number.
     *
     * @param int $page
     *
     * @return string
     */
    public function url($page): string
    {
        $params = request()->query->all();

        data_set($params, $this->pageParam() . '.' . $this->numberParam(), $page);
        data_set($params, $this->pageParam() . '.' . $this->sizeParam(), $this->perPage);

        $path = Str::of($this->path())
            ->rtrim('/');

        return $path . '?' . http_build_query($params);
    }

    /**
     * Get resource collection of items.
     *
     * @return ResourceCollection|BaseCollection
     */
    public function getResourceCollection(): ResourceCollection|BaseCollection
    {
        return new $this->collectionClass($this->items);
    }

    /**
     * Get pagination meta.
     *
     * @return array
     */
    public function meta(): array
    {
        return [
            'from' => $this->firstItem(),
            'to' => $this->lastItem(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'current_page' => $this->currentPage(),
            'last_page' => $this->lastPage(),
            'total' => $this->total(),
            'links' => [
                'first' => $this->url(1),
                'prev' => $this->previousPageUrl(),
                'self' => $this->url($this->currentPage()),
                'next' => $this->nextPageUrl(),
                'last' => $this->lastPage() !== null ? $this->url($this->lastPage()) : null,
            ],
        ];
    }

    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        // A simple paginator knows it without a total, by fetching one more item
        return $this->paginator->hasMorePages();
    }
}
