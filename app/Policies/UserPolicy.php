<?php

namespace Core\Policies;

use Core\Models\BaseModel;
use Core\Models\User;

/**
 * Class UserPolicy.
 *
 * Authorizes managing the users of an organization (see UserController).
 * Besides the permissions, a user can't change, delete or restore a user,
 * whose roles rank above theirs, nor delete themselves. Adding roles needs
 * the `attachRole` permission, and removing them `detachRole`.
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
     * Determine whether the user can update the model: not one, who ranks
     * above them.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function update(mixed $user, mixed $model): bool
    {
        return parent::update($user, $model) && !$model->isHigherThan($user);
    }

    /**
     * Determine whether the user can delete the model: not themselves, nor one,
     * who ranks above them.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function delete(mixed $user, mixed $model): bool
    {
        return parent::delete($user, $model) && !$model->is($user) && !$model->isHigherThan($user);
    }

    /**
     * Determine whether the user can permanently delete the model: not
     * themselves, nor one, who ranks above them.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function forceDelete(mixed $user, mixed $model): bool
    {
        return parent::forceDelete($user, $model) && !$model->is($user) && !$model->isHigherThan($user);
    }

    /**
     * Determine whether the user can restore the model: not one, who ranks
     * above them.
     *
     * @param User|null $user
     * @param User $model
     *
     * @return bool
     */
    public function restore(mixed $user, mixed $model): bool
    {
        return parent::restore($user, $model) && !$model->isHigherThan($user);
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
