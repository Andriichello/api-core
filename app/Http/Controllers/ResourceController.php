<?php

namespace Core\Http\Controllers;

use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Http\Resources\ResourcePaginator;
use Core\Http\Responses\ApiResponse;
use Core\Models\BaseModel;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class ResourceController.
 *
 * Wraps models in the controller's resource and collection
 * classes, and those in API responses.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers
 */
abstract class ResourceController extends BaseController
{
    /**
     * Resource class, which wraps a single model.
     *
     * @var class-string<BaseResource>
     */
    protected string $resourceClass;

    /**
     * Collection class, which wraps a list of models.
     *
     * @var class-string<BaseCollection>
     */
    protected string $collectionClass;

    /**
     * Get resource class, which wraps a single model.
     *
     * @return class-string<BaseResource>
     */
    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * Get collection class, which wraps a list of models.
     *
     * @return class-string<BaseCollection>
     */
    public function getCollectionClass(): string
    {
        return $this->collectionClass;
    }

    /**
     * Wrap the given model in the controller's resource. Includes and
     * appends, when given, are used instead of the request's ones.
     *
     * @param BaseModel|array $model
     * @param array|null $includes
     * @param array|null $appends
     *
     * @return BaseResource
     */
    public function asResource(
        BaseModel|array $model,
        ?array $includes = null,
        ?array $appends = null
    ): BaseResource {
        $class = $this->getResourceClass();

        return (new $class($model))
            ->setPassedIncludes($includes)
            ->setPassedAppends($appends);
    }

    /**
     * Wrap the given models in the controller's collection. Includes and
     * appends, when given, are used instead of the request's ones.
     *
     * @param Collection $models
     * @param array|null $includes
     * @param array|null $appends
     *
     * @return BaseCollection
     */
    public function asCollection(
        Collection $models,
        ?array $includes = null,
        ?array $appends = null
    ): BaseCollection {
        $class = $this->getCollectionClass();

        return (new $class($models))
            ->setPassedIncludes($includes)
            ->setPassedAppends($appends);
    }

    /**
     * Get a response with the given model in the controller's resource.
     *
     * @param BaseModel|array $model
     * @param array|null $includes
     * @param array|null $appends
     *
     * @return ApiResponse
     */
    public function asResourceResponse(
        BaseModel|array $model,
        ?array $includes = null,
        ?array $appends = null
    ): ApiResponse {
        return ApiResponse::ok([
            'data' => $this->asResource($model, $includes, $appends),
        ]);
    }

    /**
     * Get a response with the given models in the controller's collection.
     *
     * @param Collection $models
     * @param array|null $includes
     * @param array|null $appends
     *
     * @return ApiResponse
     */
    public function asCollectionResponse(
        Collection $models,
        ?array $includes = null,
        ?array $appends = null
    ): ApiResponse {
        return ApiResponse::ok([
            'data' => $this->asCollection($models, $includes, $appends),
        ]);
    }

    /**
     * Get a response with the requested page of the given query's models,
     * in the controller's collection, along with the pagination meta.
     *
     * @param Builder|EloquentBuilder $builder
     * @param Request|null $request
     *
     * @return ApiResponse
     * @throws Exception
     */
    public function asPaginatedResponse(Builder|EloquentBuilder $builder, ?Request $request = null): ApiResponse
    {
        $paginator = new ResourcePaginator(
            $this->paginate($builder, $request),
            $this->getCollectionClass()
        );

        return ApiResponse::ok($paginator->toArray());
    }
}
