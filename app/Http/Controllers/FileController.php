<?php

namespace Core\Http\Controllers;

use Core\Http\Controllers\Traits\NestsUnderParent;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\ShowRequest;
use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Files\StoreFileRequest;
use Core\Http\Requests\Files\UpdateFileRequest;
use Core\Http\Resources\FileCollection;
use Core\Http\Resources\FileResource;
use Core\Http\Responses\ApiResponse;
use Core\Models\BaseModel;
use Core\Models\File;
use Core\Models\Interfaces\HasFilesInterface;
use Core\Policies\CrudPolicy;
use Core\Queries\BaseQuery;
use Core\Queries\FileQuery;
use Core\Repositories\FileRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Class FileController.
 *
 * Handles the files of a model (see HasFiles), under which its routes nest
 * them, e.g. `invoices/{invoice}/files/{id}`: it lists, uploads, shows,
 * downloads, renames and deletes them. A controller is made for each model,
 * whose files have routes: it names the model's class and route parameter,
 * and takes the model's policy, which authorizes each request as an ability
 * on the model (see NestsUnderParent), so a user, who can view the model, can
 * list and download its files, and a user, who can update it, can change them.
 *
 * `routes()` registers the routes of the controller's operations.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
abstract class FileController extends CrudController
{
    use NestsUnderParent;

    /**
     * Resource class, which wraps a single model.
     *
     * @var class-string<FileResource>
     */
    protected string $resourceClass = FileResource::class;

    /**
     * Collection class, which wraps a list of models.
     *
     * @var class-string<FileCollection>
     */
    protected string $collectionClass = FileCollection::class;

    /**
     * Operations, which the controller handles,
     * mapped to the classes of their requests.
     *
     * @var array<string, class-string<CrudRequest>>
     */
    protected array $operations = [
        'index' => IndexRequest::class,
        'show' => ShowRequest::class,
        'download' => ShowRequest::class,
        'store' => StoreFileRequest::class,
        'update' => UpdateFileRequest::class,
        'destroy' => DestroyRequest::class,
    ];

    /**
     * Class of the model, whose files the controller handles.
     *
     * @var class-string<BaseModel&HasFilesInterface>
     */
    protected string $fileable;

    /**
     * Collection, to which the routes are limited: they list, find and add
     * only its files. When null, they see the files of every collection, and
     * add them to the default one.
     *
     * @var string|null
     */
    protected ?string $collection = null;

    /**
     * FileController constructor.
     *
     * @param CrudPolicy $policy The policy of the model, whose files the controller handles.
     * @param FileRepository $repository
     */
    public function __construct(CrudPolicy $policy, FileRepository $repository)
    {
        parent::__construct($policy, $repository);
    }

    /**
     * Register the routes of the controller's operations under the given URI,
     * which has the model's route parameter, e.g. `invoices/{invoice}/files`.
     *
     * @param string $uri
     *
     * @return void
     */
    public static function routes(string $uri): void
    {
        static::registerRoutes($uri, [
            'index' => ['get', ''],
            'store' => ['post', ''],
            'show' => ['get', '/{id}'],
            'download' => ['get', '/{id}/download'],
            'update' => ['patch', '/{id}'],
            'destroy' => ['delete', '/{id}'],
        ]);
    }

    /**
     * Handle the `download` operation: the requested file's contents.
     *
     * @param Request $request
     *
     * @return StreamedResponse
     * @throws NotFoundHttpException When the stored file is missing.
     * @throws Throwable
     */
    public function download(Request $request): StreamedResponse
    {
        /** @var ShowRequest $showRequest */
        $showRequest = $this->prepareRequest('download', $request);

        /** @var File $file */
        $file = $this->builder($showRequest)
            ->findOrFail($showRequest->getId());

        if (!$file->isStored()) {
            throw new NotFoundHttpException('The stored file is missing.');
        }

        return $file->download();
    }

    /**
     * Handle the `store` operation: attach the uploaded file to the model.
     *
     * @param StoreRequest $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    protected function handleStore(StoreRequest $request): ApiResponse
    {
        /** @var UploadedFile $upload */
        $upload = $request->file('file');

        $file = $this->fileableOf($request)->attachFile(
            $upload,
            $request->validated('name'),
            $this->collection ?? File::DEFAULT_COLLECTION,
        );

        return $this->asResourceResponse($file)
            ->setMessage('Created')
            ->setStatus(201);
    }

    /**
     * Get the query builder for the request: the files of the model, which
     * the route names, in the controller's collection, if it has one.
     *
     * @param CrudRequest $request
     *
     * @return BaseQuery
     * @throws Throwable
     */
    protected function builder(CrudRequest $request): BaseQuery
    {
        /** @var FileQuery $query */
        $query = $this->repository->builder($request);
        $query->whereMorphedTo('fileable', $this->fileableOf($request));

        if ($this->collection !== null) {
            $query->inCollection($this->collection);
        }

        return $query;
    }

    /**
     * Get class of the model, whose files the controller handles.
     *
     * @return class-string<BaseModel&HasFilesInterface>
     */
    protected function parentClass(): string
    {
        return $this->fileable;
    }

    /**
     * Get the model, whose files the request handles.
     *
     * @param CrudRequest $request
     *
     * @return BaseModel&HasFilesInterface
     * @throws LogicException When the model can't have files.
     */
    protected function fileableOf(CrudRequest $request): BaseModel&HasFilesInterface
    {
        $fileable = $this->parentOf($request);

        if (!$fileable instanceof HasFilesInterface) {
            throw new LogicException($this->fileable . ' must implement ' . HasFilesInterface::class . '.');
        }

        return $fileable;
    }
}
