<?php

namespace Core\Policies;

use Core\Models\BaseModel;
use Core\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * Class BasePolicy.
 *
 * Abilities that the policy has no method for are
 * denied, unless `determineMissing()` says otherwise.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Policies
 */
abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Model, which is controlled by the policy.
     *
     * @var BaseModel|string
     */
    protected BaseModel|string $model;

    /**
     * Get model, which is controlled by the policy.
     *
     * @return BaseModel|string|null
     */
    public function getModel(): BaseModel|string|null
    {
        return $this->model ?? null;
    }

    /**
     * Perform pre-authorization checks. Be aware that the ability
     * method won't be called unless null is returned here.
     *
     * @param User|null $user
     * @param string $ability
     *
     * @return Response|bool|null
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function before(mixed $user, string $ability): Response|bool|null
    {
        return null;
    }

    /**
     * Determine an ability that the policy has no method for.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return bool
     */
    public function __call(string $name, array $arguments): bool
    {
        return $this->determineMissing($name, ...$arguments);
    }

    /**
     * Determine an ability that the policy has no method for.
     * It is denied, unless overridden.
     *
     * @param string $ability
     * @param mixed ...$args
     *
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function determineMissing(string $ability, mixed ...$args): bool
    {
        return false;
    }
}
