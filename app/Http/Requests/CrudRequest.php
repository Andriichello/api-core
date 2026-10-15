<?php

namespace Core\Http\Requests;

use Core\Models\BaseModel;

/**
 * Class CrudRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests
 */
class CrudRequest extends BaseRequest
{
    /**
     * Ability, which should be checked on the request.
     *
     * @var string|null
     */
    protected ?string $ability = null;

    /**
     * Parents, under which the route nests the request's models, by the names
     * of their route parameters.
     *
     * @var array<string, BaseModel>
     */
    protected array $parents = [];

    /**
     * Get ability, which should be checked on the request.
     *
     * @return string|null
     */
    public function getAbility(): ?string
    {
        return $this->ability;
    }

    /**
     * Get the parents, under which the route nests the request's models, by
     * the names of their route parameters (see CrudController::$parents).
     *
     * @return array<string, BaseModel>
     */
    public function getParents(): array
    {
        return $this->parents;
    }

    /**
     * Get the parent of the given route parameter, or null, when the route
     * doesn't nest the models under it.
     *
     * @param string $name
     *
     * @return BaseModel|null
     */
    public function getParent(string $name): ?BaseModel
    {
        return $this->parents[$name] ?? null;
    }

    /**
     * Set the parents, under which the route nests the request's models.
     *
     * @param array<string, BaseModel> $parents
     *
     * @return static
     */
    public function setParents(array $parents): static
    {
        $this->parents = $parents;

        return $this;
    }
}
