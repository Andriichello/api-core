<?php

namespace Core\Http\Controllers;

use Core\Http\Middleware\ResolveOrganization;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\ShowRequest;
use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\Crud\UpdateRequest;
use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Users\InviteUserRequest;
use Core\Http\Requests\Users\ResendInvitationRequest;
use Core\Http\Requests\Users\UpdateUserRequest;
use Core\Http\Resources\UserCollection;
use Core\Http\Resources\UserResource;
use Core\Http\Responses\ApiResponse;
use Core\Models\User;
use Core\Policies\UserPolicy;
use Core\Queries\BaseQuery;
use Core\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Class UserController.
 *
 * Manages the users of the organization, in which a request acts: it lists
 * and shows them, invites people to new accounts (see
 * UserRepository::invite()), updates them, deletes them, and emails an
 * invitation again. A manager gives only the roles, which they may assign
 * (their own and the lower ones), and the roles, which they may not, stay as
 * they are. Nobody sets another person's email or password (see
 * UpdateUserRequest), and UserPolicy keeps managers off users, who rank
 * above them.
 *
 * `routes()` registers its routes.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class UserController extends CrudController
{
    /**
     * Resource class, which wraps a single model.
     *
     * @var class-string<UserResource>
     */
    protected string $resourceClass = UserResource::class;

    /**
     * Collection class, which wraps a list of models.
     *
     * @var class-string<UserCollection>
     */
    protected string $collectionClass = UserCollection::class;

    /**
     * Operations, which the controller handles,
     * mapped to the classes of their requests.
     *
     * @var array<string, class-string<CrudRequest>>
     */
    protected array $operations = [
        'index' => IndexRequest::class,
        'show' => ShowRequest::class,
        'store' => InviteUserRequest::class,
        'update' => UpdateUserRequest::class,
        'destroy' => DestroyRequest::class,
        'resendInvitation' => ResendInvitationRequest::class,
    ];

    /**
     * UserController constructor.
     *
     * @param UserPolicy $policy
     * @param UserRepository $users
     */
    public function __construct(UserPolicy $policy, protected UserRepository $users)
    {
        parent::__construct($policy, $users);
    }

    /**
     * Register the routes of the users, which need a signed-in user, and act
     * in the user's organization (see ResolveOrganization).
     *
     * @return void
     */
    public static function routes(): void
    {
        Route::middleware(['auth:sanctum', ResolveOrganization::ALIAS])->group(function () {
            Route::get('users', [static::class, 'index'])->name('users.index');
            Route::post('users', [static::class, 'store'])->name('users.store');
            Route::get('users/{id}', [static::class, 'show'])->name('users.show');
            Route::patch('users/{id}', [static::class, 'update'])->name('users.update');
            Route::delete('users/{id}', [static::class, 'destroy'])->name('users.destroy');
            Route::post('users/{id}/invitation', [static::class, 'resendInvitation'])->name('users.invitation');
        });
    }

    /**
     * Handle the `resendInvitation` operation.
     *
     * @param Request $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    public function resendInvitation(Request $request): ApiResponse
    {
        return $this->handle('resendInvitation', $request);
    }

    /**
     * Handle the `store` operation: invite a person to a new account in the
     * organization, in which the request acts.
     *
     * @param StoreRequest $request
     *
     * @return ApiResponse
     * @throws AuthorizationException
     */
    protected function handleStore(StoreRequest $request): ApiResponse
    {
        $roles = $request->validated('roles', []);
        $this->ensureRolesCanChange($request, $roles, []);

        $user = $this->users->invite(
            Arr::except($request->validated(), ['roles']),
            $request->orgId(),
            $roles,
            $this->managerOf($request)
        );

        return $this->asResourceResponse($user->load('roles'))
            ->setMessage('Invited')
            ->setStatus(201);
    }

    /**
     * Handle the `update` operation: update the requested user, and give them
     * the roles, if they are given, along with the roles, which the manager
     * may not assign.
     *
     * @param UpdateRequest $request
     *
     * @return ApiResponse
     * @throws Throwable
     */
    protected function handleUpdate(UpdateRequest $request): ApiResponse
    {
        /** @var User $user */
        $user = $this->builder($request)->findOrFail($request->getId());
        $attributes = $request->validated();
        $roles = null;

        if (array_key_exists('roles', $attributes)) {
            $assignable = $this->managerOf($request)->availableRoles();
            $current = $user->getRoleNames()->all();

            $this->ensureRolesCanChange(
                $request,
                array_values(array_diff($attributes['roles'], $current)),
                array_values(array_diff(array_intersect($current, $assignable), $attributes['roles']))
            );

            $roles = [...$attributes['roles'], ...array_diff($current, $assignable)];
        }

        DB::transaction(function () use ($user, $attributes, $roles) {
            $this->users->update($user, Arr::except($attributes, ['roles']));

            if ($roles !== null) {
                $user->syncRoles($roles);
            }
        });

        return $this->asResourceResponse($user->fresh()?->load('roles') ?? $user)
            ->setMessage('Updated');
    }

    /**
     * Handle the `resendInvitation` operation: email the invitation to the
     * requested user again, unless they have accepted it.
     *
     * @param ResendInvitationRequest $request
     *
     * @return ApiResponse
     * @throws ConflictHttpException When the invitation has been accepted.
     * @throws Throwable
     */
    protected function handleResendInvitation(ResendInvitationRequest $request): ApiResponse
    {
        /** @var User $user */
        $user = $this->builder($request)->findOrFail($request->getId());

        if ($user->hasVerifiedEmail()) {
            throw new ConflictHttpException('The invitation has been accepted already.');
        }

        $this->users->sendInvitation($user, $this->managerOf($request));

        return ApiResponse::ok()
            ->setMessage('Invitation sent');
    }

    /**
     * Get the query builder for the request, with the users' roles.
     *
     * @param CrudRequest $request
     *
     * @return BaseQuery
     * @throws Throwable
     */
    protected function builder(CrudRequest $request): BaseQuery
    {
        return parent::builder($request)->with('roles');
    }

    /**
     * Make sure the manager may add and remove the given roles.
     *
     * @param CrudRequest $request
     * @param array<int, string> $added
     * @param array<int, string> $removed
     *
     * @return void
     * @throws AuthorizationException
     */
    protected function ensureRolesCanChange(CrudRequest $request, array $added, array $removed): void
    {
        /** @var UserPolicy $policy */
        $policy = $this->policy;

        if (!$policy->changeRoles($this->managerOf($request), $added, $removed)) {
            throw new AuthorizationException('You are not authorized to change the roles of users.');
        }
    }

    /**
     * Get the signed-in user, who manages the users.
     *
     * @param CrudRequest $request
     *
     * @return User
     * @throws AuthorizationException
     */
    protected function managerOf(CrudRequest $request): User
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
