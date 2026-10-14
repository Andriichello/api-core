<?php

namespace Core\Http\Resources;

use Core\Models\AccessToken;
use Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Class MeResource.
 *
 * The signed-in user, with what a client needs to know about the session:
 * the organization, roles and permissions, the main account, and the user,
 * who switched to this account, if the token was issued that way.
 *
 * @property User $resource
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Resources
 */
class MeResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     * @throws Throwable
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $token = $user->currentAccessToken();
        $impersonator = $token instanceof AccessToken ? $token->impersonator : null;

        return array_merge(
            Arr::except(parent::toArray($request), ['organization', 'roles', 'permissions']),
            [
                'main_account_id' => $user->main_account_id,
                'organization' => $user->organization?->only(['id', 'slug', 'name']),
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                'impersonator' => $impersonator?->only(['id', 'organization_id', 'name', 'email']),
            ]
        );
    }
}
