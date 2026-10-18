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
use Core\Models\BaseModel;
use Core\Policies\CrudPolicy;
use Core\Queries\BaseQuery;
use Core\Repositories\CrudRepository;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
 * Routes may nest the models under their parents (see `$parents`), e.g.
 * `profiles/{profile}/clients/{client}/contracts/{id}`: the operations then
 * see only the models of the parents, and new models belong to them.
 *
 * The operations are methods, so the attributes, which are chained before a
 * route (`Route::middleware(...)->get(...)`), apply to it. Extra operations,
 * which a controller adds to `$operations`, are handled through `__call()`,
 * which Laravel doesn't see as their method, so their routes get attributes
 * only from a group, or with a `Controller@operation` action.
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
     * Route parameters of the models' parents, from the outermost to the
     * innermost, e.g. `['profile', 'client']` for contracts in
     * `profiles/{profile}/clients/{client}/contracts/{id}`. Each one is the
     * name of the model's BelongsTo relation to the parent too. A route may
     * leave some of them out (a shallow one). A parent is found within the
     * user's index scope and within the outer parents, it belongs to.
     *
     * @var array<int, string>
     */
    protected array $parents = [];

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
     * Handle the `index` operation.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function index(Request $request): ApiResponse
    {
        return $this->handle('index', $request);
    }

    /**
     * Handle the `show` operation.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function show(Request $request): ApiResponse
    {
        return $this->handle('show', $request);
    }

    /**
     * Handle the `store` operation.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function store(Request $request): ApiResponse
    {
        return $this->handle('store', $request);
    }

    /**
     * Handle the `update` operation.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function update(Request $request): ApiResponse
    {
        return $this->handle('update', $request);
    }

    /**
     * Handle the `destroy` operation.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function destroy(Request $request): ApiResponse
    {
        return $this->handle('destroy', $request);
    }

    /**
     * Handle an extra operation, which has the given name.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function __call($method, $parameters): ApiResponse
    {
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
     * @throws BadMethodCallException When the controller doesn't handle the operation.
     * @throws Throwable
     */
    protected function handle(string $operation, Request $request): ApiResponse
    {
        $method = 'handle' . ucfirst($operation);

        return $this->$method($this->prepareRequest($operation, $request));
    }

    /**
     * Make the operation's request, with the parents, which its route names,
     * authorize it and validate it.
     *
     * @param string $operation
     * @param Request $request
     *
     * @return CrudRequest
     * @throws BadMethodCallException When the controller doesn't handle the operation.
     * @throws Throwable
     */
    protected function prepareRequest(string $operation, Request $request): CrudRequest
    {
        if (!in_array($operation, $this->getOperations())) {
            throw new BadMethodCallException(
                sprintf('Method %s::%s does not exist.', static::class, $operation)
            );
        }

        $class = $this->getOperationRequest($operation);

        $crudRequest = $class::createFrom($request);
        $crudRequest->setParents($this->resolveParents($crudRequest));

        $this->checkPolicy($crudRequest);

        $crudRequest->validateResolved();

        return $crudRequest;
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
        $model = $this->repository->create($request->validated(), $request->getParents());

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
        $model = $this->builder($request)
            ->when(
                $request->force(),
                fn (BaseQuery $query) => $query->withoutGlobalScope(SoftDeletingScope::class)
            )
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
        $this->ensureAllowed($this->policy->determine($request));
    }

    /**
     * Make sure the given result of a policy allows the request.
     *
     * @param Response|bool $result
     *
     * @return void
     * @throws AuthorizationException
     */
    protected function ensureAllowed(Response|bool $result): void
    {
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
        $query = $this->repository->builder($request);

        foreach ($request->getParents() as $relation => $parent) {
            $query->whereBelongsTo($parent, $relation);
        }

        return $query;
    }

    /**
     * Find the parents, which the request's route names, by their route
     * parameters. Each one is looked up within the user's index scope, and
     * within the outer parents, to which it belongs, so a parent, which the
     * user can't see, or which is under another outer parent, isn't found.
     *
     * @param CrudRequest $request
     *
     * @return array<string, BaseModel>
     * @throws ModelNotFoundException
     */
    protected function resolveParents(CrudRequest $request): array
    {
        $parents = [];
        $model = $this->repository->model();
        $model = is_string($model) ? new $model : $model;

        foreach ($this->parents as $name) {
            $value = $request->route($name);

            if ($value === null) {
                continue;
            }

            /** @var BelongsTo $relation */
            $relation = $model->$name();

            /** @var BaseQuery $query */
            $query = $relation->getRelated()->newQuery();
            $query->index($request->user(), []);

            foreach ($parents as $outerName => $outer) {
                if ($query->getModel()->isRelation($outerName)) {
                    $query->whereBelongsTo($outer, $outerName);
                }
            }

            /** @var BaseModel $parent */
            $parent = $query->findOrFail($value instanceof Model ? $value->getKey() : $value);

            $parents[$name] = $parent;
        }

        return $parents;
    }
}
