<?php

namespace Core\Policies;

use Core\Http\Requests\CrudRequest;
use Core\Http\Requests\Interfaces\WithTargetInterface;
use Core\Models\BaseModel;
use Core\Models\User;
use Core\Policies\Interfaces\ChecksPermissionsInterface;
use Core\Policies\Traits\ChecksPermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class CrudPolicy.
 *
 * Authorizes the ability of a CRUD request. Unless the user is a super
 * admin, the `{ability} {morph}` permission has to be met first, and then
 * the ability's method decides.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Policies
 */
class CrudPolicy extends BasePolicy implements ChecksPermissionsInterface
{
    use ChecksPermissions;

    /**
     * Determine if the request's ability is allowed. A request with a target
     * is checked against its target, which is looked up within the user's
     * index scope, so a model the user cannot see is not found at all.
     *
     * @param CrudRequest $request
     *
     * @return Response|bool
     * @throws ModelNotFoundException
     */
    public function determine(CrudRequest $request): Response|bool
    {
        $ability = $request->getAbility();

        if (!$ability) {
            return true;
        }

        $user = $request->user();
        $before = $this->before($user, $ability);

        if ($before !== null) {
            return $before;
        }

        return $request instanceof WithTargetInterface
            ? $this->$ability($user, $request->targetOrFail($this->getModel()))
            : $this->$ability($user);
    }

    /**
     * Perform pre-authorization checks. Be aware that the ability
     * method won't be called unless null is returned here.
     *
     * @param User|null $user
     * @param string $ability
     *
     * @return Response|bool|null
     */
    public function before(mixed $user, string $ability): Response|bool|null
    {
        if (!$user && !in_array($ability, ['view', 'viewAny'])) {
            return false;
        }

        if ($this->shouldCheckPermissions($user, $ability)) {
            if (!$this->arePermissionsMet($user, $ability)) {
                return false;
            }
        }

        return parent::before($user, $ability);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User|null $user
     * @param BaseModel $model
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function view(mixed $user, mixed $model): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param User|null $user
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User|null $user
     *
     * @return bool
     */
    public function create(mixed $user): bool
    {
        return $this->hasAnyRole($user);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User|null $user
     * @param BaseModel $model
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function update(mixed $user, mixed $model): bool
    {
        return $this->hasAnyRole($user);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User|null $user
     * @param BaseModel $model
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function delete(mixed $user, mixed $model): bool
    {
        return $this->hasAnyRole($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User|null $user
     * @param BaseModel $model
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function forceDelete(mixed $user, mixed $model): bool
    {
        return $this->hasAnyRole($user);
    }

    /**
     * Determine whether the user has any role above a guest.
     *
     * @param User|null $user
     *
     * @return bool
     */
    protected function hasAnyRole(mixed $user): bool
    {
        return $user?->isUser() || $user?->isAdmin() || $user?->isSuperAdmin();
    }
}
