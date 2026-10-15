<?php

namespace Core\Http\Middleware;

use Closure;
use Core\Helpers\CurrentOrganization;
use Core\Models\Organization;
use Core\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ResolveOrganization.
 *
 * Sets CurrentOrganization for the request: the organization of the signed-in
 * user, or, for a super admin, the one in the `X-Organization-Id` header. It
 * runs right after authentication (CoreServiceProvider ranks it so), before
 * route model bindings, which find models of the organization. An application
 * puts it, by its alias, after `auth:sanctum` on its routes.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Middleware
 */
class ResolveOrganization
{
    /**
     * Alias of the middleware, which CoreServiceProvider registers.
     *
     * @var string
     */
    public const string ALIAS = 'organization';

    /**
     * Header, with which a super admin acts in another organization.
     *
     * @var string
     */
    public const string HEADER = 'X-Organization-Id';

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        CurrentOrganization::set($this->resolve($request, $user instanceof User ? $user : null));

        return $next($request);
    }

    /**
     * Get id of the organization, in which the request acts: the user's one,
     * or the one in the header, which only a super admin may choose.
     *
     * @param Request $request
     * @param User|null $user
     *
     * @return int|null
     * @throws AccessDeniedHttpException
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    protected function resolve(Request $request, ?User $user): ?int
    {
        $header = (string) $request->header(static::HEADER);

        if ($header === '') {
            return $user?->organization_id;
        }

        if (!ctype_digit($header)) {
            throw new BadRequestHttpException(static::HEADER . ' must be an id of an organization.');
        }

        $id = (int) $header;

        if ($id === $user?->organization_id) {
            return $id;
        }

        if (!$user?->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Only super admins may act in another organization.');
        }

        if (!Organization::query()->whereKey($id)->exists()) {
            throw new NotFoundHttpException('The organization is not found.');
        }

        return $id;
    }
}
