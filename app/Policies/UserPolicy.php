<?php

namespace Core\Policies;

use Core\Models\BaseModel;
use Core\Models\User;

/**
 * Class UserPolicy.
 *
 * Authorizes managing the users of an organization (see UserController).
 * Besides the permissions, a user can't change, delete or restore a user of
 * another organization than the one they act in, nor one, whose roles rank
 * above theirs, nor delete themselves. The person's own accounts in other
 * organizations can be seen, but not managed with the roles of this one.
 * Adding roles needs the `attachRole` permission, and removing them
 * `detachRole`.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Policies
 */
class UserPolicy extends CrudPolicy
{
    /**
     * Model, which is controlled by the policy.
     *
     * @var BaseModel|string
     */
    protected BaseModel|string $model = User::class;

    /**
     * Determine whether the user can update the model: one, whom they manage.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function update(mixed $user, mixed $model): bool
    {
        return parent::update($user, $model) && $this->manages($user, $model);
    }

    /**
     * Determine whether the user can delete the model: one, whom they manage,
     * but not themselves.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function delete(mixed $user, mixed $model): bool
    {
        return parent::delete($user, $model) && !$model->is($user) && $this->manages($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model: one, whom
     * they manage, but not themselves.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function forceDelete(mixed $user, mixed $model): bool
    {
        return parent::forceDelete($user, $model) && !$model->is($user) && $this->manages($user, $model);
    }

    /**
     * Determine whether the user can restore the model: one, whom they manage.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function restore(mixed $user, mixed $model): bool
    {
        return parent::restore($user, $model) && $this->manages($user, $model);
    }

    /**
     * Determine whether the user manages the model: a user of the organization,
     * in which they act (any, when they act in all organizations), who doesn't
     * rank above them.
     *
     * @param User $user
     * @param User $model
     *
     * @return bool
     */
    protected function manages(User $user, User $model): bool
    {
        if (!$user->actsInAllOrganizations() && $model->orgId() !== $user->actingOrgId()) {
            return false;
        }

        return !$model->isHigherThan($user);
    }

    /**
     * Determine whether the user can add and remove the given roles of a user:
     * adding needs the `attachRole` permission, and removing `detachRole`.
     * Which roles the user may give is the request's to check.
     *
     * @param User $user
     * @param array<int, string> $added
     * @param array<int, string> $removed
     *
     * @return bool
     */
    public function changeRoles(User $user, array $added, array $removed): bool
    {
        if (!$this->shouldCheckPermissions($user)) {
            return true;
        }

        return (empty($added) || $this->arePermissionsMet($user, 'attachRole'))
            && (empty($removed) || $this->arePermissionsMet($user, 'detachRole'));
    }
}
