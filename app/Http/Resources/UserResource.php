<?php

namespace Core\Http\Resources;

use Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Class UserResource.
 *
 * A user, whom a manager sees: the attributes, with the names of the roles
 * instead of their records.
 *
 * @property User $resource
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class UserResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            Arr::except(parent::toArray($request), ['roles', 'permissions']),
            ['roles' => $this->resource->getRoleNames()->values()->all()]
        );
    }
}
