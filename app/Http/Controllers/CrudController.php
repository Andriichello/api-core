<?php

namespace Core\Http\Controllers;

use BadMethodCallException;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\ShowRequest;
use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\Crud\UpdateRequest;
use Core\Http\Requests\CrudRequest;
use Core\Http\Responses\ApiResponse;
use Core\Policies\CrudPolicy;
use Core\Queries\BaseQuery;
use Core\Repositories\CrudRepository;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Class CrudController.
 *
 * Handles the `index`, `show`, `store`, `update` and `destroy` operations.
 * Each operation's request is made from the incoming one, authorized by the
 * policy first and validated after that, and then handled by its
 * `handle{Operation}()` method, which can be overridden.
 *
 * @method ApiResponse index(Request $request)
 * @method ApiResponse show(Request $request)
 * @method ApiResponse store(Request $request)
 * @method ApiResponse update(Request $request)
 * @method ApiResponse destroy(Request $request)
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
abstract class CrudController extends ResourceController
{
    /**
     * Operations, which the controller handles,
     * mapped to the classes of their requests.
     *
     * @var array<string, class-string<CrudRequest>>
     */
    protected array $operations = [
        'index' => IndexRequest::class,
        'show' => ShowRequest::class,
        'store' => StoreRequest::class,
        'update' => UpdateRequest::class,
        'destroy' => DestroyRequest::class,
    ];

    /**
     * CrudController constructor.
     *
     * @param CrudPolicy $policy
     * @param CrudRepository $repository
     */
    public function __construct(
        protected CrudPolicy $policy,
        protected CrudRepository $repository
    ) {}

    /**
     * Handle the operation, which has the given name.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function __call($method, $parameters): ApiResponse
    {
        if (!in_array($method, $this->getOperations())) {
            throw new BadMethodCallException(
                sprintf('Method %s::%s does not exist.', static::class, $method)
            );
        }

        $request = Arr::first(
            Arr::wrap($parameters),
            fn ($param) => $param instanceof Request,
        );

        return $this->handle($method, $request ?? request());
    }

    /**
     * Make the operation's request, authorize it, validate
     * it and pass it to the operation's handle method.
     *
     * @param string $operation
     * @param Request $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    protected function handle(string $operation, Request $request): ApiResponse
    {
        $class = $this->getOperationRequest($operation);

        $crudRequest = $class::createFrom($request);

        $this->checkPolicy($crudRequest);

        $crudRequest->validateResolved();

        $method = 'handle' . ucfirst($operation);

        return $this->$method($crudRequest);
    }

    /**
     * Handle the `index` operation: a page of the models.
     *
     * @param IndexRequest $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    protected function handleIndex(IndexRequest $request): ApiResponse
    {
        return $this->asPaginatedResponse($this->builder($request), $request);
    }

    /**
     * Handle the `show` operation: the requested model.
     *
     * @param ShowRequest $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    protected function handleShow(ShowRequest $request): ApiResponse
    {
        $model = $this->builder($request)
            ->findOrFail($request->getId());

        return $this->asResourceResponse($model);
    }

    /**
     * Handle the `store` operation: create a model of the validated data.
     *
     * @param StoreRequest $request
     *
     * @return ApiResponse
     */
    protected function handleStore(StoreRequest $request): ApiResponse
    {
        $model = $this->repository->create($request->validated());

        return $this->asResourceResponse($model)
            ->setMessage('Created')
            ->setStatus(201);
    }

    /**
     * Handle the `update` operation: update the requested
     * model with the validated data.
     *
     * @param UpdateRequest $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    protected function handleUpdate(UpdateRequest $request): ApiResponse
    {
        $model = $this->builder($request)
            ->findOrFail($request->getId());

        if (!$this->repository->update($model, $request->validated())) {
            throw new Exception('Failed to update ' . $model::class, 500);
        }

        return $this->asResourceResponse($model->fresh())
            ->setMessage('Updated');
    }

    /**
     * Handle the `destroy` operation: delete the requested model,
     * or delete it permanently when `force` is asked for. Only then
     * can a soft-deleted model be found, so it can be removed.
     *
     * @param DestroyRequest $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    protected function handleDestroy(DestroyRequest $request): ApiResponse
    {
        $withTrashed = $request->force() && $this->repository->isSoftDeletable();

        $model = $this->builder($request)
            ->when($withTrashed, fn (BaseQuery $query) => $query->withTrashed()) // @phpstan-ignore method.notFound
            ->findOrFail($request->getId());

        $deleted = $request->force()
            ? $this->repository->forceDelete($model)
            : $this->repository->delete($model);

        if (!$deleted) {
            throw new Exception('Failed to delete ' . $model::class, 500);
        }

        return ApiResponse::ok()
            ->setMessage('Deleted');
    }

    /**
     * Authorize the given request with the controller's policy.
     *
     * @param CrudRequest $request
     *
     * @return void
     * @throws AuthorizationException
     */
    protected function checkPolicy(CrudRequest $request): void
    {
        $result = $this->policy->determine($request);

        if ($result === false) {
            throw new AuthorizationException;
        }

        if (!is_bool($result)) {
            $result->authorize();
        }
    }

    /**
     * Get names of the operations, which the controller handles.
     *
     * @return array<int, string>
     */
    public function getOperations(): array
    {
        return array_keys($this->operations);
    }

    /**
     * Get class of the given operation's request.
     *
     * @param string $operation
     *
     * @return class-string<CrudRequest>|null
     */
    public function getOperationRequest(string $operation): ?string
    {
        return $this->operations[$operation] ?? null;
    }

    /**
     * Get the query builder for the request, with its
     * index scope, filters, sorts and includes applied.
     *
     * @param CrudRequest $request
     *
     * @return BaseQuery
     * @throws Throwable
     */
    protected function builder(CrudRequest $request): BaseQuery
    {
        return $this->repository->builder($request);
    }
}
