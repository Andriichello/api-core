<?php

namespace Core\Http\Controllers\Traits;

use Core\Http\Controllers\CrudController;
use Core\Http\Requests\CrudRequest;
use Core\Models\BaseModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use LogicException;

/**
 * Trait NestsUnderParent.
 *
 * For CRUD controllers of what belongs to a model, such as its files or its
 * settings, whose routes nest it under the model, e.g.
 * `invoices/{invoice}/files`. The model is found by its route parameter
 * within the user's index scope, and within the model's own parents, which
 * the route names, e.g. `profiles/{profile}/invoices/{invoice}/files` with
 * `$parents = ['profile']` (the invoice's BelongsTo relation). The
 * controller's policy, which is the model's one, authorizes each request as
 * an ability on the model (see `$abilities`), so a user, who can view the
 * model, can read what belongs to it, and a user, who can update it, can
 * change that.
 *
 * @mixin CrudController
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers\Traits
 */
trait NestsUnderParent
{
    /**
     * Route parameter of the model, under which the routes nest what the
     * controller handles.
     *
     * @var string
     */
    protected string $parameter;

    /**
     * Abilities on the model, which the abilities of the requests need.
     * A request, whose ability isn't listed, is denied.
     *
     * @var array<string, string>
     */
    protected array $abilities = [
        'viewAny' => 'view',
        'view' => 'view',
        'create' => 'update',
        'update' => 'update',
        'delete' => 'update',
        'forceDelete' => 'update',
    ];

    /**
     * Get class of the model, under which the routes nest what the controller
     * handles.
     *
     * @return class-string<BaseModel>
     */
    abstract protected function parentClass(): string;

    /**
     * Register the given routes under the URI for the operations, which the
     * controller handles. They are keyed by the operations, and have the HTTP
     * method and the suffix of the URI.
     *
     * @param string $uri
     * @param array<string, array{0: string, 1: string}> $routes
     *
     * @return void
     */
    protected static function registerRoutes(string $uri, array $routes): void
    {
        $operations = get_class_vars(static::class)['operations'];

        foreach ($routes as $operation => [$method, $suffix]) {
            if (array_key_exists($operation, $operations)) {
                Route::$method($uri . $suffix, [static::class, $operation]);
            }
        }
    }

    /**
     * Authorize the given request as its ability on the model, with the
     * controller's policy.
     *
     * @param CrudRequest $request
     *
     * @return void
     * @throws AuthorizationException
     */
    protected function checkPolicy(CrudRequest $request): void
    {
        $ability = $this->abilities[$request->getAbility()] ?? null;

        if ($ability === null) {
            throw new AuthorizationException;
        }

        $user = $request->user();

        $this->ensureAllowed(
            $this->policy->before($user, $ability)
                ?? $this->policy->$ability($user, $this->parentOf($request))
        );
    }

    /**
     * Find the model, under which the request is nested, by its route
     * parameter, within the user's index scope, and within its own parents,
     * which the route names (see `$parents`, the model's BelongsTo relations),
     * so a model under another parent isn't found.
     *
     * @param CrudRequest $request
     *
     * @return array<string, BaseModel>
     * @throws LogicException When the route has no parameter of the model.
     * @throws ModelNotFoundException
     */
    protected function resolveParents(CrudRequest $request): array
    {
        $value = $request->route($this->parameter);

        if ($value === null) {
            throw new LogicException(static::class . " needs the {{$this->parameter}} route parameter.");
        }

        $class = $this->parentClass();
        $parents = $this->resolveParentsOf(new $class, $request);

        $parents[$this->parameter] = $this->findParent(new $class, $value, $parents, $request);

        return $parents;
    }

    /**
     * Get the model, under which the request is nested.
     *
     * @param CrudRequest $request
     *
     * @return BaseModel
     * @throws LogicException When the model isn't resolved.
     */
    protected function parentOf(CrudRequest $request): BaseModel
    {
        return $request->getParent($this->parameter)
            ?? throw new LogicException("The {{$this->parameter}} model of the request isn't resolved.");
    }
}
