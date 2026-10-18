<?php

namespace Core\Repositories;

use Core\Http\Resources\FileResource;
use Core\Models\BaseModel;
use Core\Models\File;

/**
 * Class FileRepository.
 *
 * Files are added with HasFiles (`attachFile()`, `attachContents()`), which
 * stores them, rather than with `create()`.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
class FileRepository extends CrudRepository
{
    /**
     * Repository's model class.
     *
     * @var BaseModel|string
     */
    protected BaseModel|string $model = File::class;

    /**
     * The resource class used to validate allowed includes it.
     *
     * @var class-string<FileResource>
     */
    protected string $resourceClass = FileResource::class;

    /**
     * List of filters allowed on request.
     *
     * @var array<int|string, string>
     */
    protected array $allowedFilters = [
        'collection',
        'mime_type',
        'size',
    ];

    /**
     * List of sorts allowed on request.
     *
     * @var string[]
     */
    protected array $allowedSorts = [
        'size',
    ];

    /**
     * List of default sorts to apply
     * when no other sorts are provided.
     *
     * @var array<string, string>
     */
    protected array $defaultSorts = [
        'id' => 'asc',
    ];
}
