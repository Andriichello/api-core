<?php

namespace Core\Http\Controllers\Traits;

use Core\Http\Controllers\CrudController;
use Core\Http\Requests\CrudRequest;
use Core\Models\BaseModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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
 * an ability on the model (see `parentAbilities()`), so a user, who can view the
 * model, can read what belongs to it, and a user, who can update it, can
 * change that. A policy of another model is refused, so the permissions on
 * the model are always checked.
 *
 * @mixin CrudController
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers\Traits
 */
trait NestsUnderParent
{
    /**
     * Get class of the model, under which the routes nest what the controller
     * handles.
     *
     * @return class-string<BaseModel>
     */
    abstract protected function parentClass(): string;

    /**
     * Get the route parameter of the model, under which the routes nest what
     * the controller handles, e.g. `invoice`.
     *
     * @return string
     */
    abstract protected function parentParameter(): string;

    /**
     * Get the abilities on the model, which the abilities of the requests
     * need. A request, whose ability isn't listed, is denied. Methods, unlike
     * the properties of a trait, can be overridden by the class using it.
     *
     * @return array<string, string>
     */
    protected function parentAbilities(): array
    {
        return [
            'viewAny' => 'view',
            'view' => 'view',
            'create' => 'update',
            'update' => 'update',
            'delete' => 'update',
            'forceDelete' => 'update',
        ];
    }

    /**
     * Register the given routes under the URI for the operations, which the
     * controller handles. They are keyed by the operations, and have the HTTP
     * method and the suffix of the URI. Each one is named `{name}.{operation}`,
     * where the name is the URI's without the parameters, unless it is given,
     * e.g. `invoices.files.index` for `invoices/{invoice}/files`.
     *
     * @param string $uri
     * @param array<string, array{0: string, 1: string}> $routes
     * @param string|null $name
     *
     * @return void
     */
    protected static function registerRoutes(string $uri, array $routes, ?string $name = null): void
    {
        $operations = get_class_vars(static::class)['operations'];
        $name ??= Str::of($uri)
            ->replaceMatches('/\{[^}]*\}/', '')
            ->explode('/')
            ->filter()
            ->implode('.');

        foreach ($routes as $operation => [$method, $suffix]) {
            if (array_key_exists($operation, $operations)) {
                Route::$method($uri . $suffix, [static::class, $operation])->name("$name.$operation");
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
     * @throws LogicException When the policy isn't the model's one.
     */
    protected function checkPolicy(CrudRequest $request): void
    {
        $this->ensurePolicyOfParent();

        $ability = $this->parentAbilities()[$request->getAbility()] ?? null;

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
     * Make sure the controller's policy is the model's one, so it checks the
     * permissions on the model. A controller, which doesn't take the model's
     * policy in its constructor, gets a plain CrudPolicy from the container,
     * which has no model, so it would check no permissions.
     *
     * @return void
     * @throws LogicException When the policy isn't the model's one.
     */
    protected function ensurePolicyOfParent(): void
    {
        $model = $this->policy->getModel();

        if ($model === null || !is_a($model, $this->parentClass(), true)) {
            throw new LogicException(
                static::class . ' needs the policy of ' . $this->parentClass() . ', '
                . get_class($this->policy) . ' given.'
            );
        }
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
        $parameter = $this->parentParameter();
        $value = $request->route($parameter);

        if ($value === null) {
            throw new LogicException(static::class . " needs the {{$parameter}} route parameter.");
        }

        $class = $this->parentClass();
        $parents = $this->resolveParentsOf(new $class, $request);

        $parents[$parameter] = $this->findParent(new $class, $value, $parents, $request);

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
        $parameter = $this->parentParameter();

        return $request->getParent($parameter)
            ?? throw new LogicException("The {{$parameter}} model of the request isn't resolved.");
    }
}
